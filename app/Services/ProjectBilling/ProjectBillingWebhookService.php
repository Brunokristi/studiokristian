<?php

namespace App\Services\ProjectBilling;

use App\Models\ProjectInvoice;
use App\Models\ProjectBillingAdjustment;
use App\Models\ProjectInvoiceItem;
use App\Models\ProjectSubscription;
use App\Services\ClientAttentionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\StripeObject;
use Throwable;

/**
 * Stripe is the authoritative source of payment state for Custom Project Billing.
 * Every handler is keyed on a Stripe ID so repeated delivery is a no-op update.
 */
class ProjectBillingWebhookService
{
    public function __construct(
        private ProjectInvoiceNumberService $numbers,
        private ProjectInvoicePdfService $pdf,
        private ProjectInvoiceService $invoices,
        private StripeProjectBillingGateway $stripe,
        private ClientAttentionService $attention,
    ) {
    }

    public function process(Event $event): void
    {
        match ($event->type) {
            'invoice.created' => $this->syncExistingInvoice($event->data->object, ProjectInvoice::STATUS_DRAFT),
            'invoice.finalized' => $this->syncExistingInvoice($event->data->object, ProjectInvoice::STATUS_OPEN),
            'invoice.paid' => $this->handleInvoicePaid($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            'invoice.voided' => $this->closeInvoice($event->data->object, ProjectInvoice::STATUS_VOID),
            'invoice.marked_uncollectible' => $this->closeInvoice($event->data->object, ProjectInvoice::STATUS_UNCOLLECTIBLE),
            'customer.subscription.created',
            'customer.subscription.updated' => $this->syncSubscription($event->data->object),
            'customer.subscription.deleted' => $this->syncDeletedSubscription($event->data->object),
            'subscription_schedule.created',
            'subscription_schedule.updated',
            'subscription_schedule.completed',
            'subscription_schedule.released',
            'subscription_schedule.canceled' => $this->syncSchedule($event->data->object),
            'payment_intent.succeeded',
            'payment_intent.payment_failed' => $this->syncPaymentIntent($event->data->object),
            'refund.created',
            'refund.updated',
            'refund.failed' => $this->syncRefund($event->data->object),
            'charge.refunded' => $this->syncChargeRefunds($event->data->object),
            'credit_note.created' => $this->syncCreditNote($event->data->object),
            default => Log::info('Project billing webhook acknowledged without processing.', [
                'stripe_event_id' => $event->id,
                'type' => $event->type,
            ]),
        };
    }

    private function syncRefund(StripeObject $refund): void
    {
        $refundId = $this->id($refund->id ?? null);
        $paymentIntentId = $this->id($refund->payment_intent ?? null);
        $chargeId = $this->id($refund->charge ?? null);

        $adjustment = ProjectBillingAdjustment::query()
            ->where('stripe_refund_id', $refundId)
            ->first();

        $invoice = null;

        if (! $adjustment && $paymentIntentId) {
            $invoice = ProjectInvoice::query()
                ->where('stripe_payment_intent_id', $paymentIntentId)
                ->first();

            $adjustment = $invoice?->adjustments()
                ->where('type', ProjectBillingAdjustment::TYPE_REFUND)
                ->whereIn('status', [
                    ProjectBillingAdjustment::STATUS_PENDING,
                    ProjectBillingAdjustment::STATUS_FAILED,
                ])
                ->latest('id')
                ->first();
        }

        if (! $adjustment && $invoice) {
            $adjustment = ProjectBillingAdjustment::query()->create([
                'project_id' => $invoice->project_id,
                'company_id' => $invoice->company_id,
                'project_invoice_id' => $invoice->id,
                'project_subscription_id' => $invoice->project_subscription_id,
                'type' => ProjectBillingAdjustment::TYPE_REFUND,
                'status' => ProjectBillingAdjustment::STATUS_PENDING,
                'amount' => (int) ($refund->amount ?? 0),
                'currency' => strtoupper((string) ($refund->currency ?? $invoice->currency)),
                'stripe_refund_id' => $refundId,
                'stripe_charge_id' => $chargeId,
            ]);
        }

        if (! $adjustment) {
            return;
        }

        $status = match ($refund->status ?? null) {
            'succeeded' => ProjectBillingAdjustment::STATUS_SUCCEEDED,
            'failed', 'canceled' => ProjectBillingAdjustment::STATUS_FAILED,
            default => ProjectBillingAdjustment::STATUS_PENDING,
        };

        $adjustment->update([
            'stripe_refund_id' => $refundId ?: $adjustment->stripe_refund_id,
            'stripe_charge_id' => $chargeId ?: $adjustment->stripe_charge_id,
            'status' => $status,
            'processed_at' => $status === ProjectBillingAdjustment::STATUS_SUCCEEDED ? now() : null,
            'error_message' => $refund->failure_reason ?? null,
        ]);
    }

    private function syncChargeRefunds(StripeObject $charge): void
    {
        foreach (($charge->refunds->data ?? []) as $refund) {
            if (! isset($refund->payment_intent) && isset($charge->payment_intent)) {
                $refund->payment_intent = $charge->payment_intent;
            }

            if (! isset($refund->charge) && isset($charge->id)) {
                $refund->charge = $charge->id;
            }

            $this->syncRefund($refund);
        }
    }

    private function syncCreditNote(StripeObject $creditNote): void
    {
        $creditNoteId = $this->id($creditNote->id ?? null);
        $stripeInvoiceId = $this->id($creditNote->invoice ?? null);

        if (! $creditNoteId || ! $stripeInvoiceId) {
            return;
        }

        $invoice = ProjectInvoice::query()
            ->where('stripe_invoice_id', $stripeInvoiceId)
            ->first();

        if (! $invoice) {
            return;
        }

        ProjectBillingAdjustment::query()->updateOrCreate(
            ['stripe_credit_note_id' => $creditNoteId],
            [
                'project_id' => $invoice->project_id,
                'company_id' => $invoice->company_id,
                'project_invoice_id' => $invoice->id,
                'project_subscription_id' => $invoice->project_subscription_id,
                'type' => ProjectBillingAdjustment::TYPE_CREDIT,
                'status' => ProjectBillingAdjustment::STATUS_SUCCEEDED,
                'amount' => (int) ($creditNote->total ?? $creditNote->amount ?? 0),
                'currency' => strtoupper((string) ($creditNote->currency ?? $invoice->currency)),
                'stripe_invoice_id' => $stripeInvoiceId,
                'processed_at' => now(),
            ]
        );
    }

    private function syncExistingInvoice(StripeObject $stripeInvoice, string $status): ?ProjectInvoice
    {
        $stripeInvoiceId = $this->id($stripeInvoice->id ?? null);

        if ($stripeInvoiceId && ProjectInvoice::query()->where('stripe_invoice_id', $stripeInvoiceId)->exists()) {
            return $this->syncInvoice($stripeInvoice, $status, false);
        }

        if ($status !== ProjectInvoice::STATUS_OPEN) {
            return null;
        }

        $subscription = $this->subscriptionFor($stripeInvoice);
        $isFirstInvoice = $subscription
            && $subscription->collection_method === 'send_invoice'
            && ! $subscription->invoices()->exists();

        return $isFirstInvoice
            ? $this->syncInvoice($stripeInvoice, $status, true)
            : null;
    }

    /**
     * Creates or updates the local invoice for a Stripe invoice that belongs to this
     * domain - including recurring invoices Stripe generates on its own.
     */
    private function syncInvoice(
        StripeObject $stripeInvoice,
        string $status,
        bool $allowCreate
    ): ?ProjectInvoice
    {
        $stripeInvoiceId = $this->id($stripeInvoice->id ?? null);

        if (! $stripeInvoiceId) {
            return null;
        }

        $invoice = ProjectInvoice::query()
            ->where('stripe_invoice_id', $stripeInvoiceId)
            ->first();

        if (! $invoice && ! $allowCreate) {
            return null;
        }

        $subscription = $this->subscriptionFor($stripeInvoice, $allowCreate && ! $invoice);

        if (! $invoice && $allowCreate && $subscription) {
            $invoice = ProjectInvoice::query()
                ->where('stripe_invoice_id', $stripeInvoiceId)
                ->first();
        }

        if (! $invoice && ! $subscription && ! $this->belongsToDomain($stripeInvoice)) {
            return null;
        }

        $isNew = $invoice === null;

        if ($isNew) {
            $subscription?->loadMissing('project.company');
            $project = $subscription?->project;
            $company = $subscription?->company;

            if (! $project || ! $company) {
                Log::info('Project billing invoice could not be mapped to a project.', [
                    'stripe_invoice_id' => $stripeInvoiceId,
                ]);

                return null;
            }

            $issueDate = $this->timestamp($stripeInvoice->created ?? null) ?: now();

            try {
                $invoice = ProjectInvoice::query()->create(array_merge([
                    'project_id' => $project->id,
                    'company_id' => $company->id,
                    'project_subscription_id' => $subscription->id,
                    'invoice_number' => $this->numbers->next((int) $issueDate->format('Y')),
                    'variable_symbol' => null,
                    'status' => $status,
                    'currency' => strtoupper((string) ($stripeInvoice->currency ?? config('billing.currency'))),
                    'payment_method' => ProjectInvoice::METHOD_STRIPE_CARD,
                    'collection_method' => (string) ($stripeInvoice->collection_method ?? 'charge_automatically'),
                    'stripe_invoice_id' => $stripeInvoiceId,
                    'issue_date' => $issueDate->toDateString(),
                    'delivery_date' => $issueDate->toDateString(),
                    'tax_rate' => 0,
                    'tax_mode' => config('billing.tax.vat_payer')
                        ? ProjectInvoice::TAX_MODE_STANDARD
                        : ProjectInvoice::TAX_MODE_NONE,
                ], $this->invoices->partySnapshot($company)));

                $invoice->update(['variable_symbol' => $invoice->invoice_number]);
                $this->syncLineItems($invoice, $stripeInvoice);
                $this->pushInvoiceNumber($invoice, $stripeInvoice);
            } catch (UniqueConstraintViolationException $exception) {
                $invoice = ProjectInvoice::query()
                    ->where('stripe_invoice_id', $stripeInvoiceId)
                    ->firstOrFail();

                $isNew = false;
            }
        }

        $invoice->update(array_filter([
            'stripe_invoice_id' => $stripeInvoiceId,
            'stripe_customer_id' => $this->id($stripeInvoice->customer ?? null),
            'stripe_subscription_id' => $this->subscriptionId($stripeInvoice),
            'stripe_payment_intent_id' => $this->paymentIntentId($stripeInvoice),
            'project_subscription_id' => $subscription?->id ?? $invoice->project_subscription_id,
            'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url ?? null,
            'subtotal' => (int) ($stripeInvoice->subtotal ?? $invoice->subtotal),
            'tax_amount' => (int) ($stripeInvoice->tax ?? $invoice->tax_amount),
            'total' => (int) ($stripeInvoice->total ?? $invoice->total),
            'amount_paid' => (int) ($stripeInvoice->amount_paid ?? $invoice->amount_paid),
            'amount_due' => (int) ($stripeInvoice->amount_due ?? $invoice->amount_due),
            'due_date' => $this->timestamp($stripeInvoice->due_date ?? null)?->toDateString(),
        ], fn ($value) => $value !== null));

        // Never downgrade a paid/void invoice back to draft on a late duplicate event.
        if (! in_array($invoice->status, [ProjectInvoice::STATUS_PAID, ProjectInvoice::STATUS_VOID, ProjectInvoice::STATUS_UNCOLLECTIBLE], true)) {
            $invoice->update(['status' => $status]);
        }

        if ($isNew) {
            $this->pdf->generate($invoice->fresh('items'));
        }

        $invoice = $invoice->fresh(['items', 'company']);

        if ($invoice->status === ProjectInvoice::STATUS_OPEN && ! $invoice->sent_at) {
            if (! $isNew) {
                $this->pdf->generate($invoice);
            }

            if ($invoice->company && $this->attention->notifyCompany($invoice->company)) {
                $invoice->update(['sent_at' => now()]);
            }
        }

        return $invoice->fresh('items');
    }

    private function handleInvoicePaid(StripeObject $stripeInvoice): void
    {
        $invoice = $this->syncInvoice($stripeInvoice, ProjectInvoice::STATUS_PAID, true);

        if (! $invoice) {
            return;
        }

        $invoice->update([
            'status' => ProjectInvoice::STATUS_PAID,
            'payment_status' => ProjectInvoice::PAYMENT_PAID,
            'paid_at' => $invoice->paid_at ?: now(),
            'payment_failed_at' => null,
            'amount_due' => 0,
        ]);

        $this->syncProcessingFee($invoice, $stripeInvoice);
        $this->syncSubscriptionPaymentState($invoice->subscription, ProjectSubscription::STATUS_ACTIVE);
        $this->enableAutomaticCollection($invoice);

        $invoice->update(['sent_at' => $invoice->sent_at ?: now()]);
    }

    /**
     * After the first successful payment Stripe has saved a default payment method, so the
     * subscription can move from invoice-first collection to automatic charging.
     */
    private function enableAutomaticCollection(ProjectInvoice $invoice): void
    {
        $subscription = $invoice->subscription;

        if (! $subscription?->stripe_subscription_id) {
            return;
        }

        try {
            $stripeSubscription = $this->stripe->retrieveSubscription($subscription->stripe_subscription_id);
            $paymentMethodId = $this->id($stripeSubscription->default_payment_method ?? null);

            if (! $paymentMethodId) {
                return;
            }

            $subscription->update(['stripe_default_payment_method_id' => $paymentMethodId]);

            if ($subscription->collection_method === 'charge_automatically') {
                return;
            }

            $this->stripe->updateSubscription($subscription->stripe_subscription_id, [
                'collection_method' => 'charge_automatically',
            ]);

            $subscription->update(['collection_method' => 'charge_automatically']);
        } catch (Throwable $exception) {
            Log::warning('Unable to switch project subscription to automatic collection.', [
                'project_subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function handleInvoicePaymentFailed(StripeObject $stripeInvoice): void
    {
        $subscription = $this->subscriptionFor($stripeInvoice);
        $this->syncSubscriptionPaymentState($subscription, ProjectSubscription::STATUS_PAST_DUE);

        $invoice = $this->syncInvoice($stripeInvoice, ProjectInvoice::STATUS_OPEN, false);

        if (! $invoice || $invoice->payment_status === ProjectInvoice::PAYMENT_PAID) {
            return;
        }

        $alreadyFailed = $invoice->payment_status === ProjectInvoice::PAYMENT_FAILED;

        $invoice->update([
            'payment_status' => ProjectInvoice::PAYMENT_FAILED,
            'payment_failed_at' => $invoice->payment_failed_at ?: now(),
        ]);

        if (! $alreadyFailed && $invoice->company) {
            $this->attention->notifyCompany($invoice->company);
        }
    }

    private function syncSubscriptionPaymentState(?ProjectSubscription $subscription, string $status): void
    {
        if (! $subscription || $subscription->status === ProjectSubscription::STATUS_CANCELED) {
            return;
        }

        $subscription->update(['status' => $status]);
    }

    private function closeInvoice(StripeObject $stripeInvoice, string $status): void
    {
        $invoice = ProjectInvoice::query()
            ->where('stripe_invoice_id', $this->id($stripeInvoice->id ?? null))
            ->first();

        $invoice?->update([
            'status' => $status,
            'voided_at' => $status === ProjectInvoice::STATUS_VOID ? ($invoice->voided_at ?: now()) : $invoice->voided_at,
            'amount_due' => 0,
        ]);
    }

    private function syncSubscription(StripeObject $stripeSubscription): void
    {
        $subscription = $this->subscriptionByStripeId($this->id($stripeSubscription->id ?? null));

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => $this->mapSubscriptionStatus((string) ($stripeSubscription->status ?? '')),
            'collection_method' => (string) ($stripeSubscription->collection_method ?? $subscription->collection_method),
            'stripe_default_payment_method_id' => $this->id($stripeSubscription->default_payment_method ?? null)
                ?: $subscription->stripe_default_payment_method_id,
            'current_period_start' => $this->subscriptionPeriodStart($stripeSubscription)
                ?: $subscription->current_period_start,
            'current_period_end' => $this->subscriptionPeriodEnd($stripeSubscription)
                ?: $subscription->current_period_end,
            'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
            'canceled_at' => $this->timestamp($stripeSubscription->canceled_at ?? null),
        ]);

        if ($subscription->stripe_schedule_id) {
            $this->syncScheduleObject($subscription, $this->stripe->retrieveSchedule($subscription->stripe_schedule_id));
        }
    }

    private function subscriptionPeriodStart(StripeObject $subscription): ?Carbon
    {
        $timestamp = $subscription->current_period_start ?? collect($subscription->items->data ?? [])
            ->pluck('current_period_start')
            ->filter()
            ->min();

        return $this->timestamp($timestamp);
    }

    private function subscriptionPeriodEnd(StripeObject $subscription): ?Carbon
    {
        $timestamp = $subscription->current_period_end ?? collect($subscription->items->data ?? [])
            ->pluck('current_period_end')
            ->filter()
            ->max();

        return $this->timestamp($timestamp);
    }

    private function syncSchedule(StripeObject $schedule): void
    {
        $stripeSubscriptionId = $this->id($schedule->subscription ?? null);

        if (! $stripeSubscriptionId) {
            return;
        }

        $subscription = $this->subscriptionByStripeId($stripeSubscriptionId);

        if ($subscription) {
            $subscription->update(['stripe_schedule_id' => $this->id($schedule->id ?? null)]);
            $this->syncScheduleObject($subscription, $schedule);
        }
    }

    private function syncScheduleObject(ProjectSubscription $subscription, StripeObject $schedule): void
    {
        $phase = $schedule->current_phase ?? null;

        $phase ??= collect($schedule->phases ?? [])->first(function ($candidate): bool {
            $start = (int) ($candidate->start_date ?? 0);
            $end = (int) ($candidate->end_date ?? PHP_INT_MAX);

            return $start <= time() && time() < $end;
        });

        if (! $phase && $schedule->status === 'active') {
            $phase = $schedule->phases[0] ?? null;
        }

        $currentStart = $this->timestamp($phase->start_date ?? null);
        $currentEnd = $this->timestamp($phase->end_date ?? null);

        $subscription->update([
            'current_period_start' => $currentStart ?: $subscription->current_period_start,
            'current_period_end' => $currentEnd ?: $subscription->current_period_end,
            'status' => $schedule->status === 'canceled'
                ? ProjectSubscription::STATUS_CANCELED
                : ($schedule->status === 'not_started'
                    ? ProjectSubscription::STATUS_DRAFT
                    : $subscription->status),
            'ended_at' => $schedule->status === 'canceled' ? ($subscription->ended_at ?: now()) : $subscription->ended_at,
        ]);
    }

    private function syncDeletedSubscription(StripeObject $stripeSubscription): void
    {
        $subscription = $this->subscriptionByStripeId($this->id($stripeSubscription->id ?? null));

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => ProjectSubscription::STATUS_CANCELED,
            'cancel_at_period_end' => false,
            'canceled_at' => $this->timestamp($stripeSubscription->canceled_at ?? null) ?: now(),
            'ended_at' => $this->timestamp($stripeSubscription->ended_at ?? null) ?: now(),
        ]);

        $subscription->items()->update(['status' => 'canceled']);
    }

    private function syncPaymentIntent(StripeObject $intent): void
    {
        $invoiceId = $this->id($intent->invoice ?? null);

        if (! $invoiceId) {
            return;
        }

        ProjectInvoice::query()
            ->where('stripe_invoice_id', $invoiceId)
            ->update(['stripe_payment_intent_id' => $this->id($intent->id ?? null)]);
    }

    /**
     * Stripe fees are recorded separately - they are a cost, never a customer discount.
     */
    private function syncProcessingFee(ProjectInvoice $invoice, StripeObject $stripeInvoice): void
    {
        $chargeId = $this->id($stripeInvoice->charge ?? null);

        if (! $chargeId) {
            return;
        }

        try {
            $charge = $this->stripe->retrieveCharge($chargeId);
            $balanceTransaction = $charge->balance_transaction ?? null;

            if (is_object($balanceTransaction)) {
                $invoice->update([
                    'stripe_fee_amount' => (int) ($balanceTransaction->fee ?? 0),
                    'stripe_net_amount' => (int) ($balanceTransaction->net ?? 0),
                ]);
            }
        } catch (Throwable $exception) {
            Log::info('Unable to load Stripe processing fee for project invoice.', [
                'project_invoice_id' => $invoice->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function syncLineItems(ProjectInvoice $invoice, StripeObject $stripeInvoice): void
    {
        $lines = $stripeInvoice->lines->data ?? [];

        foreach ($lines as $index => $line) {
            $quantity = max(1, (int) ($line->quantity ?? 1));
            $amount = (int) ($line->amount ?? 0);
            $unitAmount = $line->price->unit_amount
                ?? $line->pricing?->unit_amount_decimal
                ?? ($quantity > 0 ? (int) round($amount / $quantity) : $amount);

            ProjectInvoiceItem::query()->updateOrCreate(
                [
                    'project_invoice_id' => $invoice->id,
                    'stripe_invoice_item_id' => $this->id($line->id ?? null),
                ],
                [
                    'name' => $line->description ?: 'Služba',
                    'quantity' => $quantity,
                    'unit_amount' => (int) round((float) $unitAmount),
                    'amount' => $amount,
                    'tax_rate' => 0,
                    'sort_order' => $index,
                ]
            );
        }
    }

    /**
     * Keeps the Stripe invoice number identical to the StudioKristian number.
     */
    private function pushInvoiceNumber(ProjectInvoice $invoice, StripeObject $stripeInvoice): void
    {
        if (($stripeInvoice->status ?? null) !== 'draft') {
            return;
        }

        try {
            $this->stripe->updateInvoice((string) $stripeInvoice->id, [
                'number' => $invoice->invoice_number,
                'metadata' => $this->stripe->domainMetadata([
                    'project_invoice_id' => (string) $invoice->id,
                ]),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to push local invoice number to Stripe.', [
                'project_invoice_id' => $invoice->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function subscriptionFor(
        StripeObject $stripeInvoice,
        bool $lockForUpdate = false
    ): ?ProjectSubscription
    {
        $stripeSubscriptionId = $this->subscriptionId($stripeInvoice);

        if (! $stripeSubscriptionId) {
            return null;
        }

        $query = ProjectSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function subscriptionByStripeId(?string $stripeSubscriptionId): ?ProjectSubscription
    {
        return $stripeSubscriptionId
            ? ProjectSubscription::query()
                ->where('stripe_subscription_id', $stripeSubscriptionId)
                ->first()
            : null;
    }

    private function subscriptionId(StripeObject $invoice): ?string
    {
        return $this->id($invoice->subscription ?? null)
            ?: $this->id($invoice->parent?->subscription_details?->subscription ?? null);
    }

    private function paymentIntentId(StripeObject $invoice): ?string
    {
        $payment = $invoice->payments?->data[0]?->payment ?? null;

        return $this->id($invoice->payment_intent ?? null)
            ?: $this->id($payment?->payment_intent ?? null);
    }

    private function belongsToDomain(StripeObject $object): bool
    {
        return ($object->metadata?->billing_domain ?? null) === config('billing.domain_tag');
    }

    private function mapSubscriptionStatus(string $status): string
    {
        return match ($status) {
            'active' => ProjectSubscription::STATUS_ACTIVE,
            // Actual Stripe trials are unsupported in Custom Project Billing.
            'trialing' => ProjectSubscription::STATUS_DRAFT,
            'past_due', 'unpaid' => ProjectSubscription::STATUS_PAST_DUE,
            'paused' => ProjectSubscription::STATUS_PAUSED,
            'canceled', 'incomplete_expired' => ProjectSubscription::STATUS_CANCELED,
            default => ProjectSubscription::STATUS_DRAFT,
        };
    }

    private function id(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return is_object($value) && isset($value->id) ? (string) $value->id : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_numeric($value) && (int) $value > 0
            ? Carbon::createFromTimestamp((int) $value)
            : null;
    }
}
