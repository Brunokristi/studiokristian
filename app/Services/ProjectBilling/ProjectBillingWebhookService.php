<?php

namespace App\Services\ProjectBilling;

use App\Models\ProjectInvoice;
use App\Models\ProjectInvoiceItem;
use App\Models\ProjectSubscription;
use App\Notifications\ProjectInvoiceIssuedNotification;
use App\Notifications\ProjectInvoicePaidNotification;
use App\Notifications\ProjectInvoicePaymentFailedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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
    ) {
    }

    public function process(Event $event): void
    {
        match ($event->type) {
            'invoice.created' => $this->syncInvoice($event->data->object, ProjectInvoice::STATUS_DRAFT),
            'invoice.finalized' => $this->syncInvoice($event->data->object, ProjectInvoice::STATUS_OPEN),
            'invoice.paid' => $this->handleInvoicePaid($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            'invoice.voided' => $this->closeInvoice($event->data->object, ProjectInvoice::STATUS_VOID),
            'invoice.marked_uncollectible' => $this->closeInvoice($event->data->object, ProjectInvoice::STATUS_UNCOLLECTIBLE),
            'customer.subscription.created',
            'customer.subscription.updated' => $this->syncSubscription($event->data->object),
            'customer.subscription.deleted' => $this->syncDeletedSubscription($event->data->object),
            'payment_intent.succeeded',
            'payment_intent.payment_failed' => $this->syncPaymentIntent($event->data->object),
            default => Log::info('Project billing webhook acknowledged without processing.', [
                'stripe_event_id' => $event->id,
                'type' => $event->type,
            ]),
        };
    }

    /**
     * Same sync path the webhooks use, callable directly when we must not wait for delivery.
     */
    public function syncInvoiceFromStripe(StripeObject $stripeInvoice, string $status): ?ProjectInvoice
    {
        return $this->syncInvoice($stripeInvoice, $status);
    }

    /**
     * Creates or updates the local invoice for a Stripe invoice that belongs to this
     * domain - including recurring invoices Stripe generates on its own.
     */
    private function syncInvoice(StripeObject $stripeInvoice, string $status): ?ProjectInvoice
    {
        $stripeInvoiceId = $this->id($stripeInvoice->id ?? null);

        if (! $stripeInvoiceId) {
            return null;
        }

        $invoice = ProjectInvoice::query()
            ->where('stripe_invoice_id', $stripeInvoiceId)
            ->first();

        $subscription = $this->subscriptionFor($stripeInvoice);

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

            $invoice = ProjectInvoice::query()->create(array_merge([
                'project_id' => $project->id,
                'company_id' => $company->id,
                'project_subscription_id' => $subscription->id,
                'invoice_number' => $this->numbers->next((int) $issueDate->format('Y')),
                'status' => $status,
                'currency' => strtoupper((string) ($stripeInvoice->currency ?? config('billing.currency'))),
                'payment_method' => ProjectInvoice::METHOD_STRIPE_CARD,
                'collection_method' => (string) ($stripeInvoice->collection_method ?? 'charge_automatically'),
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
        }

        $invoice->update(array_filter([
            'stripe_invoice_id' => $stripeInvoiceId,
            'stripe_customer_id' => $this->id($stripeInvoice->customer ?? null),
            'stripe_subscription_id' => $this->subscriptionId($stripeInvoice),
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

        // Emailed once the invoice is finalized, because only then does Stripe expose the
        // hosted payment link the customer needs for the first payment.
        if ($invoice->status === ProjectInvoice::STATUS_OPEN && ! $invoice->sent_at) {
            if (! $isNew) {
                $this->pdf->generate($invoice);
            }

            $this->notify($invoice, new ProjectInvoiceIssuedNotification($invoice->id));
            $invoice->update(['sent_at' => now()]);
        }

        return $invoice->fresh('items');
    }

    private function handleInvoicePaid(StripeObject $stripeInvoice): void
    {
        $invoice = $this->syncInvoice($stripeInvoice, ProjectInvoice::STATUS_PAID);

        if (! $invoice) {
            return;
        }

        $alreadyPaid = $invoice->payment_status === ProjectInvoice::PAYMENT_PAID;

        $invoice->update([
            'status' => ProjectInvoice::STATUS_PAID,
            'payment_status' => ProjectInvoice::PAYMENT_PAID,
            'paid_at' => $invoice->paid_at ?: now(),
            'payment_failed_at' => null,
            'amount_due' => 0,
        ]);

        $this->syncProcessingFee($invoice, $stripeInvoice);
        $this->enableAutomaticCollection($invoice);

        if (! $alreadyPaid) {
            $this->notify($invoice, new ProjectInvoicePaidNotification($invoice->id));
        }
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
        $invoice = $this->syncInvoice($stripeInvoice, ProjectInvoice::STATUS_OPEN);

        if (! $invoice || $invoice->payment_status === ProjectInvoice::PAYMENT_PAID) {
            return;
        }

        $alreadyFailed = $invoice->payment_status === ProjectInvoice::PAYMENT_FAILED;

        $invoice->update([
            'payment_status' => ProjectInvoice::PAYMENT_FAILED,
            'payment_failed_at' => $invoice->payment_failed_at ?: now(),
        ]);

        if (! $alreadyFailed) {
            $this->notify($invoice, new ProjectInvoicePaymentFailedNotification($invoice->id));
        }
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
            'current_period_start' => $this->timestamp($stripeSubscription->current_period_start ?? null),
            'current_period_end' => $this->timestamp($stripeSubscription->current_period_end ?? null),
            'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
            'canceled_at' => $this->timestamp($stripeSubscription->canceled_at ?? null),
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
            ProjectInvoiceItem::query()->updateOrCreate(
                [
                    'project_invoice_id' => $invoice->id,
                    'stripe_invoice_item_id' => $this->id($line->id ?? null),
                ],
                [
                    'name' => $line->description ?: 'Služba',
                    'quantity' => (int) ($line->quantity ?? 1),
                    'unit_amount' => (int) ($line->price->unit_amount ?? $line->amount ?? 0),
                    'amount' => (int) ($line->amount ?? 0),
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

    private function notify(ProjectInvoice $invoice, object $notification): void
    {
        $email = $invoice->customer_email ?: $invoice->company?->resolveBillingEmail();

        if (! $email) {
            return;
        }

        Notification::route('mail', $email)->notify($notification);
    }

    private function subscriptionFor(StripeObject $stripeInvoice): ?ProjectSubscription
    {
        return $this->subscriptionByStripeId($this->subscriptionId($stripeInvoice));
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

    private function belongsToDomain(StripeObject $object): bool
    {
        return ($object->metadata?->billing_domain ?? null) === config('billing.domain_tag');
    }

    private function mapSubscriptionStatus(string $status): string
    {
        return match ($status) {
            'active', 'trialing' => ProjectSubscription::STATUS_ACTIVE,
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
