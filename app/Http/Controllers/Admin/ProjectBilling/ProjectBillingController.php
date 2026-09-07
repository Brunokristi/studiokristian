<?php

namespace App\Http\Controllers\Admin\ProjectBilling;

use App\Http\Controllers\Controller;
use App\Models\BillingProduct;
use App\Models\Project;
use App\Models\ProjectBillingItem;
use App\Models\ProjectInvoice;
use App\Models\ProjectSubscription;
use App\Notifications\ProjectInvoiceIssuedNotification;
use App\Services\ProjectBilling\ProjectInvoicePdfService;
use App\Services\ProjectBilling\ProjectInvoiceService;
use App\Services\ProjectBilling\ProjectSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $project->loadMissing('company');

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
            ->get();

        $subscription = ProjectSubscription::query()
            ->where('project_id', $project->id)
            ->whereIn('status', [
                ProjectSubscription::STATUS_ACTIVE,
                ProjectSubscription::STATUS_PAST_DUE,
                ProjectSubscription::STATUS_PAUSED,
                ProjectSubscription::STATUS_DRAFT,
            ])
            ->with('items')
            ->latest('id')
            ->first();

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
                    'billing_email' => $project->company->billing_email,
                    'registration_number' => $project->company->registration_number,
                    'tax_number' => $project->company->tax_number,
                    'vat_number' => $project->company->vat_number,
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
                'recurring_monthly_total' => (int) $items
                    ->where('billing_type', BillingProduct::TYPE_RECURRING)
                    ->where('status', ProjectBillingItem::STATUS_ACTIVE)
                    ->sum(fn (ProjectBillingItem $item) => $item->totalAmount()),
                'next_billing_date' => $subscription?->current_period_end?->toIso8601String(),
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
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
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

    public function updateItem(Request $request, Project $project, ProjectBillingItem $item): JsonResponse
    {
        $this->assertItemBelongsToProject($project, $item);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_amount' => ['sometimes', 'integer', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['sometimes', Rule::in([
                ProjectBillingItem::STATUS_PENDING,
                ProjectBillingItem::STATUS_ACTIVE,
                ProjectBillingItem::STATUS_CANCELED,
            ])],
        ]);

        $item->update($data);

        return response()->json(['data' => $item->fresh('product')]);
    }

    public function destroyItem(Project $project, ProjectBillingItem $item): Response
    {
        $this->assertItemBelongsToProject($project, $item);

        $item->delete();

        return response()->noContent();
    }

    public function storeInvoice(Request $request, Project $project, ProjectInvoiceService $invoices): JsonResponse
    {
        $data = $request->validate([
            'billing_item_ids' => ['required', 'array', 'min:1'],
            'billing_item_ids.*' => ['integer'],
            'payment_method' => ['nullable', Rule::in([
                ProjectInvoice::METHOD_STRIPE_CARD,
                ProjectInvoice::METHOD_STRIPE_HOSTED,
                ProjectInvoice::METHOD_BANK_TRANSFER,
            ])],
            'issue_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
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
                $data['payment_method'] ?? ProjectInvoice::METHOD_STRIPE_HOSTED,
                $data
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $invoice], 201);
    }

    public function sendInvoice(Request $request, Project $project, ProjectInvoice $invoice, ProjectInvoiceService $invoices): JsonResponse
    {
        $this->assertInvoiceBelongsToProject($project, $invoice);

        $available = $this->availableRecipients($project);
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
                'message' => 'This client has no billing email and no contact with an email address. Add one on the client before sending the invoice.',
            ], 422);
        }

        return response()->json([
            'data' => $invoice->fresh(),
            'recipients' => $recipients,
        ]);
    }

    /**
     * Every address the invoice may be sent to: the client's billing email plus
     * the project's and company's contacts.
     */
    private function availableRecipients(Project $project): array
    {
        $project->loadMissing(['company.contacts', 'contacts']);

        $candidates = collect();

        if ($project->company?->billing_email) {
            $candidates->push([
                'email' => $project->company->billing_email,
                'name' => $project->company->name,
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
            'collection_method' => ['nullable', Rule::in(['charge_automatically', 'send_invoice'])],
        ]);

        try {
            $subscription = $subscriptions->start($project, $data['collection_method'] ?? 'send_invoice');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $subscription], 201);
    }

    public function cancelSubscription(Request $request, Project $project, ProjectSubscription $subscription, ProjectSubscriptionService $subscriptions): JsonResponse
    {
        $this->assertSubscriptionBelongsToProject($project, $subscription);

        $atPeriodEnd = $request->boolean('at_period_end', true);

        try {
            $subscription = $subscriptions->cancel($subscription, $atPeriodEnd);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to cancel the subscription.'], 422);
        }

        return response()->json(['data' => $subscription]);
    }

    public function pauseSubscription(Request $request, Project $project, ProjectSubscription $subscription, ProjectSubscriptionService $subscriptions): JsonResponse
    {
        $this->assertSubscriptionBelongsToProject($project, $subscription);

        try {
            $subscription = $subscriptions->pause($subscription, $request->boolean('paused', true));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to update the subscription.'], 422);
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
}
