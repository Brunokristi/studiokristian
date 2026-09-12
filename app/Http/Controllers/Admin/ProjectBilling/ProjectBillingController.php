<?php

namespace App\Http\Controllers\Admin\ProjectBilling;

use App\Http\Controllers\Controller;
use App\Models\BillingProduct;
use App\Models\Project;
use App\Models\ProjectBillingItem;
use App\Models\ProjectInvoice;
use App\Models\ProjectBillingAdjustment;
use App\Models\ProjectSubscription;
use App\Notifications\ProjectInvoiceIssuedNotification;
use App\Notifications\ProjectInvoicePaidNotification;
use App\Services\ProjectBilling\ProjectInvoicePdfService;
use App\Services\ProjectBilling\ProjectInvoiceService;
use App\Services\ProjectBilling\ProjectSubscriptionService;
use App\Services\ProjectBilling\StripeProjectBillingGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class ProjectBillingController extends Controller
{
    /**
     * Full billing picture for one project: recurring services, invoices and totals.
     */
    public function show(Project $project): JsonResponse
    {
        $project->loadMissing('company.billingContact');

        $items = ProjectBillingItem::query()
            ->where('project_id', $project->id)
            ->with('product')
            ->orderBy('billing_type')
            ->orderBy('name')
            ->get();

        $invoices = ProjectInvoice::query()
            ->where('project_id', $project->id)
            ->with('items')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get()
            ->each(fn (ProjectInvoice $invoice) => $invoice->setAttribute(
                'billing_source',
                $invoice->project_subscription_id ? 'subscription' : 'payment'
            ));

        $subscription = ProjectSubscription::query()
            ->where('project_id', $project->id)
            ->whereIn('status', [
                ProjectSubscription::STATUS_ACTIVE,
                ProjectSubscription::STATUS_PAST_DUE,
                ProjectSubscription::STATUS_PAUSED,
                ProjectSubscription::STATUS_DRAFT,
            ])
            ->with(['items', 'invoices'])
            ->latest('id')
            ->first();

        if ($subscription) {
            $subscription->setAttribute(
                'first_payment_received',
                $subscription->invoices->contains(fn (ProjectInvoice $invoice) =>
                    $invoice->payment_status === ProjectInvoice::PAYMENT_PAID
                )
            );
            $subscription->setAttribute(
                'payment_method_saved',
                (bool) $subscription->stripe_default_payment_method_id
            );

            $nextInvoice = $subscription->invoices
                ->whereIn('status', [ProjectInvoice::STATUS_DRAFT, ProjectInvoice::STATUS_OPEN])
                ->sortBy('issue_date')
                ->first();

            $subscription->setAttribute(
                'next_billing_at',
                $subscription->current_period_end?->toIso8601String()
                    ?: $this->dateString($subscription->getRawOriginal('starts_at'))
            );
            $subscription->setAttribute(
                'next_billing_amount',
                $nextInvoice?->amount_due
            );
            $subscription->setAttribute(
                'billing_interval',
                $subscription->items->pluck('interval')->filter()->unique()->count() === 1
                    ? $subscription->items->first()?->interval
                    : 'mixed'
            );
            $subscription->setAttribute(
                'recurring_totals',
                $subscription->items
                    ->where('status', '!=', ProjectBillingItem::STATUS_CANCELED)
                    ->groupBy(fn (ProjectBillingItem $item) => $item->interval ?: 'month')
                    ->map(fn ($group, $interval) => [
                        'interval' => $interval,
                        'amount' => (int) $group->sum(fn (ProjectBillingItem $item) => $item->totalAmount()),
                        'currency' => $group->first()?->currency ?: config('billing.currency'),
                    ])
                    ->values()
            );
            $subscription->setAttribute(
                'billing_email',
                $project->company?->billingContact?->email
            );
            $subscription->setAttribute(
                'payment_method_label',
                $subscription->stripe_default_payment_method_id ? 'Saved in Stripe' : null
            );
        }

        $paid = $invoices->where('status', ProjectInvoice::STATUS_PAID);
        $open = $invoices->where('status', ProjectInvoice::STATUS_OPEN);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->portal_status,
                'company' => $project->company ? [
                    'id' => $project->company->id,
                    'name' => $project->company->name,
                    'address' => $project->company->address,
                    'registration_number' => $project->company->registration_number,
                    'tax_number' => $project->company->tax_number,
                    'vat_number' => $project->company->vat_number,
                    'billing_contact' => $project->company->billingContact ? [
                        'id' => $project->company->billingContact->id,
                        'name' => $project->company->billingContact->name,
                        'email' => $project->company->billingContact->email,
                        'phone' => $project->company->billingContact->phone,
                    ] : null,
                ] : null,
            ],
            'billing_items' => $items,
            'subscription' => $subscription,
            'invoices' => $invoices,
            'recipients' => $this->availableRecipients($project),
            'metrics' => [
                'currency' => config('billing.currency'),
                'total_revenue' => (int) $paid->sum('total'),
                'outstanding' => (int) $open->sum('amount_due'),
                'overdue_count' => $invoices->filter(fn (ProjectInvoice $i) => $i->isOverdue())->count(),
                'unpaid_count' => $open->count(),
                'paid_count' => $paid->count(),
                'recurring_totals' => $items
                    ->where('billing_type', BillingProduct::TYPE_RECURRING)
                    ->where('status', ProjectBillingItem::STATUS_ACTIVE)
                    ->groupBy(fn (ProjectBillingItem $item) => $item->interval ?: 'month')
                    ->map(fn ($group, $interval) => [
                        'interval' => $interval,
                        'amount' => (int) $group->sum(fn (ProjectBillingItem $item) => $item->totalAmount()),
                        'currency' => $group->first()?->currency ?: config('billing.currency'),
                    ])
                    ->values(),
                'next_billing_date' => $subscription?->current_period_end?->toIso8601String()
                    ?: $this->dateString($subscription?->getRawOriginal('starts_at')),
            ],
        ]);
    }

    public function storeItem(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'billing_product_id' => ['nullable', 'integer', 'exists:billing_products,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'billing_type' => ['required', Rule::in(BillingProduct::TYPES)],
            'interval' => ['nullable', Rule::in(BillingProduct::INTERVALS), 'required_if:billing_type,recurring'],
            'interval_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'starts_at' => ['nullable', 'date_format:Y-m-d'],
            'ends_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
        ]);

        $item = ProjectBillingItem::query()->create([
            ...$data,
            'project_id' => $project->id,
            'currency' => strtoupper($data['currency'] ?? config('billing.currency')),
            'quantity' => $data['quantity'] ?? 1,
            'interval_count' => $data['interval_count'] ?? 1,
            'status' => ProjectBillingItem::STATUS_PENDING,
        ]);

        return response()->json(['data' => $item->fresh('product')], 201);
    }

    public function updateItem(
        Request $request,
        Project $project,
        ProjectBillingItem $item,
        ProjectSubscriptionService $subscriptions
    ): JsonResponse
    {
        $this->assertItemBelongsToProject($project, $item);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_amount' => ['sometimes', 'integer', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'starts_at' => ['nullable', 'date_format:Y-m-d'],
            'ends_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
            'status' => ['sometimes', Rule::in([
                ProjectBillingItem::STATUS_PENDING,
                ProjectBillingItem::STATUS_ACTIVE,
                ProjectBillingItem::STATUS_CANCELED,
            ])],
        ]);

        try {
            $item = $subscriptions->updateItem($item, $data);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to update the recurring billing item.'], 422);
        }

        return response()->json(['data' => $item->fresh('product')]);
    }

    public function destroyItem(Project $project, ProjectBillingItem $item): Response|JsonResponse
    {
        $this->assertItemBelongsToProject($project, $item);

        // Removing it locally would not stop Stripe from billing for it.
        $liveSubscription = $item->project_subscription_id
            && ProjectSubscription::query()
                ->whereKey($item->project_subscription_id)
                ->whereIn('status', [
                    ProjectSubscription::STATUS_ACTIVE,
                    ProjectSubscription::STATUS_PAST_DUE,
                    ProjectSubscription::STATUS_PAUSED,
                ])
                ->exists();

        if ($liveSubscription) {
            return response()->json([
                'message' => 'This service is part of active recurring billing. Cancel the recurring billing before removing it.',
            ], 422);
        }

        $item->delete();

        return response()->noContent();
    }

    public function storeInvoice(Request $request, Project $project, ProjectInvoiceService $invoices): JsonResponse
    {
        $data = $request->validate([
            'billing_item_ids' => ['required', 'array', 'min:1'],
            'billing_item_ids.*' => ['integer'],
            'issue_date' => ['nullable', 'date_format:Y-m-d'],
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Scoped by project so another project's items can never be invoiced here.
        $items = ProjectBillingItem::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $data['billing_item_ids'])
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'No billing items found for this project.'], 422);
        }

        try {
            $invoice = $invoices->createOneTimeInvoice(
                $project,
                $items,
                $data
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $invoice], 201);
    }

    public function previewPaymentInvoice(Request $request, Project $project, ProjectInvoiceService $invoices): Response|JsonResponse
    {
        $data = $request->validate([
            'billing_item_ids' => ['required', 'array', 'min:1'],
            'billing_item_ids.*' => ['integer'],
            'issue_date' => ['nullable', 'date_format:Y-m-d'],
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $items = ProjectBillingItem::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $data['billing_item_ids'])
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'No billing items found for this project.'], 422);
        }

        try {
            $contents = $invoices->previewOneTimeInvoice($project, $items, $data);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response($contents, 200, ['Content-Type' => 'application/pdf']);
    }

    public function sendInvoice(Request $request, Project $project, ProjectInvoice $invoice, ProjectInvoiceService $invoices): JsonResponse
    {
        $this->assertInvoiceBelongsToProject($project, $invoice);

        $available = $this->availableRecipients($project, $invoice);
        $allowed = array_column($available, 'email');

        $data = $request->validate([
            'recipients' => ['nullable', 'array'],
            // Restricted to the client's known contacts so invoices can't be sent anywhere.
            'recipients.*' => ['email', Rule::in($allowed)],
        ]);

        $recipients = $invoices->send(
            $invoice,
            array_values(array_unique($data['recipients'] ?? []))
        );

        if (! $recipients) {
            return response()->json([
                'message' => 'This invoice has no recipient email. Select a billing contact with an email address for this client.',
            ], 422);
        }

        return response()->json([
            'data' => $invoice->fresh(),
            'recipients' => $recipients,
        ]);
    }

    /**
     * The invoice's own snapshot address is the canonical recipient; the Company's
     * contacts are offered only as explicit extra recipients.
     */
    private function availableRecipients(Project $project, ?ProjectInvoice $invoice = null): array
    {
        $project->loadMissing(['company.contacts', 'company.billingContact', 'contacts']);

        $candidates = collect();

        if ($invoice?->customer_email) {
            $candidates->push([
                'email' => $invoice->customer_email,
                'name' => $invoice->customer_name ?: $project->company?->name,
                'source' => 'invoice',
            ]);
        }

        if ($project->company?->billingContact?->email) {
            $candidates->push([
                'email' => $project->company->billingContact->email,
                'name' => $project->company->billingContact->name,
                'source' => 'billing',
            ]);
        }

        foreach ($project->contacts as $contact) {
            if ($contact->email) {
                $candidates->push([
                    'email' => $contact->email,
                    'name' => $contact->name,
                    'source' => 'project',
                ]);
            }
        }

        foreach ($project->company?->contacts ?? [] as $contact) {
            if ($contact->email) {
                $candidates->push([
                    'email' => $contact->email,
                    'name' => $contact->name,
                    'source' => 'company',
                ]);
            }
        }

        return $candidates
            ->unique('email')
            ->values()
            ->all();
    }

    /**
     * Settles an invoice paid outside Stripe (bank transfer, cash). Stripe is told
     * it was paid out of band so it stops chasing the customer.
     */
    public function recordPayment(
        Request $request,
        Project $project,
        ProjectInvoice $invoice,
        StripeProjectBillingGateway $stripe
    ): JsonResponse {
        $this->assertInvoiceBelongsToProject($project, $invoice);

        // A draft invoice is still a real document (e.g. Stripe mirroring failed, or the
        // client pays by bank transfer), so anything not already closed can be settled.
        $closed = [
            ProjectInvoice::STATUS_PAID,
            ProjectInvoice::STATUS_VOID,
            ProjectInvoice::STATUS_UNCOLLECTIBLE,
        ];

        if (in_array($invoice->status, $closed, true)) {
            return response()->json([
                'message' => 'This invoice is already settled or closed.',
            ], 422);
        }

        $data = $request->validate([
            'paid_at' => ['nullable', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', Rule::in([
                ProjectInvoice::METHOD_BANK_TRANSFER,
                ProjectInvoice::METHOD_STRIPE_CARD,
                ProjectInvoice::METHOD_STRIPE_HOSTED,
            ])],
        ]);

        $invoice->update([
            'status' => ProjectInvoice::STATUS_PAID,
            'payment_status' => ProjectInvoice::PAYMENT_PAID,
            'payment_method' => $data['payment_method'] ?? ProjectInvoice::METHOD_BANK_TRANSFER,
            'paid_at' => isset($data['paid_at'])
                ? Carbon::parse($data['paid_at'])
                : now(),
            'amount_paid' => $invoice->total,
            'amount_due' => 0,
            'payment_failed_at' => null,
        ]);

        if ($invoice->stripe_invoice_id) {
            try {
                $stripe->payInvoiceOutOfBand($invoice->stripe_invoice_id);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if ($invoice->customer_email) {
            Notification::route('mail', $invoice->customer_email)
                ->notify(new ProjectInvoicePaidNotification($invoice->id));
        }

        return response()->json(['data' => $invoice->fresh()]);
    }

    public function refundInvoice(
        Request $request,
        Project $project,
        ProjectInvoice $invoice,
        StripeProjectBillingGateway $stripe
    ): JsonResponse {
        $this->assertInvoiceBelongsToProject($project, $invoice);

        if ($invoice->status !== ProjectInvoice::STATUS_PAID || ! $invoice->stripe_payment_intent_id) {
            return response()->json([
                'message' => 'Only a paid Stripe invoice with a payment intent can be refunded.',
            ], 422);
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.$invoice->amount_paid],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $adjustment = ProjectBillingAdjustment::query()->create([
            'project_id' => $project->id,
            'company_id' => $invoice->company_id,
            'project_invoice_id' => $invoice->id,
            'project_subscription_id' => $invoice->project_subscription_id,
            'type' => ProjectBillingAdjustment::TYPE_REFUND,
            'status' => ProjectBillingAdjustment::STATUS_PENDING,
            'amount' => $data['amount'],
            'currency' => $invoice->currency,
            'reason' => $data['reason'] ?? null,
        ]);

        try {
            $refund = $stripe->createRefund(
                $invoice->stripe_payment_intent_id,
                $data['amount'],
                $data['reason'] ?? null,
                'project-refund-'.$adjustment->id
            );

            $adjustment->update([
                'status' => ProjectBillingAdjustment::STATUS_SUCCEEDED,
                'stripe_refund_id' => $refund->id,
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $adjustment->update([
                'status' => ProjectBillingAdjustment::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Stripe could not create the refund.'], 422);
        }

        return response()->json(['data' => $adjustment->fresh()]);
    }

    public function createDebitNote(
        Request $request,
        Project $project,
        ProjectInvoiceService $invoices
    ): JsonResponse {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $invoice = $invoices->createDebitNote($project, $data['description'], $data['amount']);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $invoice], 201);
    }

    /**
     * Retries mirroring an invoice into Stripe after a failed push, so it becomes
     * payable online without consuming a new invoice number.
     */
    public function syncInvoiceToStripe(
        Project $project,
        ProjectInvoice $invoice,
        ProjectInvoiceService $invoices
    ): JsonResponse {
        $this->assertInvoiceBelongsToProject($project, $invoice);

        try {
            $invoice = $invoices->syncToStripe($invoice);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if (! $invoice->stripe_invoice_id) {
            return response()->json([
                'message' => 'Stripe did not accept this invoice. Check the client billing details and try again.',
            ], 422);
        }

        return response()->json(['data' => $invoice]);
    }

    public function downloadInvoicePdf(Project $project, ProjectInvoice $invoice, ProjectInvoicePdfService $pdf)
    {
        $this->assertInvoiceBelongsToProject($project, $invoice);

        $disk = Storage::disk(config('billing.invoice.pdf_disk'));

        if (! $invoice->pdf_path || ! $disk->exists($invoice->pdf_path)) {
            $pdf->generate($invoice);
            $invoice->refresh();
        }

        $contents = (string) $disk->get($invoice->pdf_path);

        return response()->streamDownload(
            fn () => print($contents),
            'faktura-'.$invoice->invoice_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function startSubscription(Request $request, Project $project, ProjectSubscriptionService $subscriptions): JsonResponse
    {
        $data = $request->validate([
            'billing_item_ids' => ['nullable', 'array'],
            'billing_item_ids.*' => ['integer'],
            'collection_method' => ['nullable', Rule::in(['charge_automatically', 'send_invoice'])],
            'starts_at' => ['nullable', 'date_format:Y-m-d'],
            'ends_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
        ]);

        try {
            $subscription = $subscriptions->start(
                $project,
                $data['collection_method'] ?? 'send_invoice',
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
                $data['billing_item_ids'] ?? null
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $subscription], 201);
    }

    public function cancelSubscription(Request $request, Project $project, ProjectSubscription $subscription, ProjectSubscriptionService $subscriptions): JsonResponse
    {
        $this->assertSubscriptionBelongsToProject($project, $subscription);

        $request->validate([
            'ends_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $atPeriodEnd = $request->boolean('at_period_end', true);
        $endDate = $request->date('ends_at');

        try {
            $subscription = $endDate
                ? $subscriptions->cancelAt($subscription, $endDate)
                : $subscriptions->cancel($subscription, $atPeriodEnd);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $subscription]);
    }

    public function pauseSubscription(Request $request, Project $project, ProjectSubscription $subscription, ProjectSubscriptionService $subscriptions): JsonResponse
    {
        $this->assertSubscriptionBelongsToProject($project, $subscription);

        $data = $request->validate([
            'paused_at' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ]);

        try {
            $subscription = ! empty($data['paused_at'])
                ? $subscriptions->pauseAt($subscription, Carbon::parse($data['paused_at']))
                : $subscriptions->pause($subscription, $request->boolean('paused', true));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $subscription]);
    }

    private function assertItemBelongsToProject(Project $project, ProjectBillingItem $item): void
    {
        abort_unless($item->project_id === $project->id, 404);
    }

    private function assertInvoiceBelongsToProject(Project $project, ProjectInvoice $invoice): void
    {
        abort_unless($invoice->project_id === $project->id, 404);
    }

    private function assertSubscriptionBelongsToProject(Project $project, ProjectSubscription $subscription): void
    {
        abort_unless($subscription->project_id === $project->id, 404);
    }

    private function dateString(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : null;
    }
}
