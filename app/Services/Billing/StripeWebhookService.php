<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Models\SaasBillingCustomer;
use App\Models\SaasPlanPrice;
use App\Models\SaasSubscription;
use App\Services\Billing\ApplicationTrialService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\StripeObject;

class StripeWebhookService
{
    public function process(Event $event): void
    {
        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
            'customer.subscription.created',
            'customer.subscription.updated' => $this->syncSubscription($event->data->object),
            'customer.subscription.deleted' => $this->syncDeletedSubscription($event->data->object),
            'invoice.paid' => $this->syncInvoice($event->data->object, true, $event->created ?? null),
            'invoice.payment_failed' => $this->syncInvoice($event->data->object, false, $event->created ?? null),
            default => $this->acknowledgeUnsupported($event),
        };
    }

    public function syncInvoiceFromStripe(StripeObject $invoice): void
    {
        $this->syncInvoice(
            $invoice,
            ($invoice->status ?? null) === 'paid'
        );
    }

    private function handleCheckoutSessionCompleted(StripeObject $session): void
    {
        $stripeSubscriptionId = $this->stripeId($session->subscription ?? null);

        if (! $stripeSubscriptionId) {
            Log::info('Stripe checkout session completed without subscription.', [
                'checkout_session_id' => $this->stripeId($session->id ?? null),
            ]);

            return;
        }

        $subscription = SaasSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();

        $company = $this->companyFromMetadata($session->metadata ?? null)
            ?: $this->companyFromStripeCustomer($this->stripeId($session->customer ?? null));
        $price = $this->priceFromMetadata($session->metadata ?? null);

        if ($company && ! $company->stripe_customer_id && $session->customer) {
            $company->update([
                'stripe_customer_id' => $this->stripeId($session->customer),
            ]);
        }

        if ($subscription) {
            $subscription->update([
                'company_id' => $company?->id ?? $subscription->company_id,
                'stripe_customer_id' => $this->stripeId($session->customer ?? null) ?: $subscription->stripe_customer_id,
            ]);

            return;
        }

        if ($company && $price) {
            SaasSubscription::query()->updateOrCreate(
                [
                    'stripe_subscription_id' => $stripeSubscriptionId,
                ],
                [
                    'project_id' => $price->plan->project_id,
                    'company_id' => $company->id,
                    'saas_plan_id' => $price->saas_plan_id,
                    'saas_plan_price_id' => $price->id,
                    'status' => SaasSubscription::STATUS_INCOMPLETE,
                    'stripe_customer_id' => $this->stripeId($session->customer ?? null),
                ]
            );

            return;
        }

        Log::info('Stripe checkout session completed before subscription sync data was available.', [
            'checkout_session_id' => $this->stripeId($session->id ?? null),
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);
    }

    private function syncSubscription(StripeObject $stripeSubscription): void
    {
        $stripeSubscriptionId = $this->stripeId($stripeSubscription->id ?? null);
        $stripeCustomerId = $this->stripeId($stripeSubscription->customer ?? null);

        if (! $stripeSubscriptionId || ! $stripeCustomerId) {
            Log::warning('Stripe subscription event missing required identifiers.');

            return;
        }

        $price = $this->priceFromSubscription($stripeSubscription);
        $company = $this->companyFromMetadata($stripeSubscription->metadata ?? null)
            ?: $this->companyFromStripeCustomer($stripeCustomerId)
            ?: $this->companyFromExistingSubscription($stripeSubscriptionId, $stripeCustomerId);

        if (! $company || ! $price) {
            Log::warning('Stripe subscription could not be mapped to local company or price.', [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'stripe_customer_id' => $stripeCustomerId,
                'stripe_price_id' => $this->subscriptionPriceId($stripeSubscription),
            ]);

            return;
        }

        if (! $company->stripe_customer_id) {
            $company->update([
                'stripe_customer_id' => $stripeCustomerId,
            ]);
        }

        SaasBillingCustomer::query()->updateOrCreate(
            [
                'project_id' => $price->plan->project_id,
                'company_id' => $company->id,
            ],
            [
                'stripe_customer_id' => $stripeCustomerId,
            ]
        );

        $existing = SaasSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();

        $attributes = [
            'project_id' => $price->plan->project_id,
            'company_id' => $company->id,
            'saas_plan_id' => $price->saas_plan_id,
            'saas_plan_price_id' => $price->id,
            'status' => $this->subscriptionStatus($stripeSubscription->status ?? SaasSubscription::STATUS_INCOMPLETE),
            'current_period_start' => $this->timestamp($stripeSubscription->current_period_start ?? null),
            'current_period_end' => $this->timestamp($stripeSubscription->current_period_end ?? null),
            'canceled_at' => $this->timestamp($stripeSubscription->canceled_at ?? null),
            'ended_at' => $this->timestamp($stripeSubscription->ended_at ?? null),
            'stripe_customer_id' => $stripeCustomerId,
            'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
        ];

        if (($attributes['status'] ?? null) !== SaasSubscription::STATUS_PAST_DUE) {
            $attributes['payment_failed_at'] = null;
        }

        if ($existing && $existing->scheduled_saas_plan_price_id) {
            $stripeScheduleId = $this->stripeId($stripeSubscription->schedule ?? null);

            if ($existing->scheduled_saas_plan_price_id === $price->id || ! $stripeScheduleId) {
                $attributes['scheduled_saas_plan_id'] = null;
                $attributes['scheduled_saas_plan_price_id'] = null;
                $attributes['stripe_schedule_id'] = null;
            }
        }

        SaasSubscription::query()->updateOrCreate(
            [
                'stripe_subscription_id' => $stripeSubscriptionId,
            ],
            $attributes
        );
    }

    private function syncDeletedSubscription(StripeObject $stripeSubscription): void
    {
        $stripeSubscriptionId = $this->stripeId($stripeSubscription->id ?? null);

        if (! $stripeSubscriptionId) {
            Log::warning('Stripe deleted subscription event missing subscription ID.');

            return;
        }

        $subscription = SaasSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();

        if (! $subscription) {
            Log::info('Stripe deleted subscription did not match a local subscription.', [
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);

            return;
        }

        $subscription->update([
            'status' => SaasSubscription::STATUS_CANCELED,
            'canceled_at' => $this->timestamp($stripeSubscription->canceled_at ?? null) ?: now(),
            'ended_at' => $this->timestamp($stripeSubscription->ended_at ?? null) ?: now(),
            'current_period_start' => $this->timestamp($stripeSubscription->current_period_start ?? null) ?: $subscription->current_period_start,
            'current_period_end' => $this->timestamp($stripeSubscription->current_period_end ?? null) ?: $subscription->current_period_end,
            'cancel_at_period_end' => false,
            'payment_failed_at' => null,
            'scheduled_saas_plan_id' => null,
            'scheduled_saas_plan_price_id' => null,
            'stripe_schedule_id' => null,
        ]);
    }

    private function syncInvoice(StripeObject $invoice, bool $paid, mixed $eventCreatedAt = null): void
    {
        $stripeInvoiceId = $this->stripeId($invoice->id ?? null);

        if (! $stripeInvoiceId) {
            Log::warning('Stripe invoice event missing invoice ID.');

            return;
        }

        $stripeSubscriptionId = $this->invoiceSubscriptionId($invoice);
        $stripeCustomerId = $this->stripeId($invoice->customer ?? null);
        $subscription = $stripeSubscriptionId
            ? SaasSubscription::query()
                ->where('stripe_subscription_id', $stripeSubscriptionId)
                ->first()
            : null;

        $localInvoice = SaasInvoice::query()->updateOrCreate(
            [
                'stripe_invoice_id' => $stripeInvoiceId,
            ],
            [
                'project_id' => $subscription?->project_id,
                'company_id' => $subscription?->company_id,
                'saas_subscription_id' => $subscription?->id,
                'stripe_customer_id' => $stripeCustomerId,
                'stripe_subscription_id' => $stripeSubscriptionId,
                'amount_due' => (int) ($invoice->amount_due ?? 0),
                'amount_paid' => (int) ($invoice->amount_paid ?? 0),
                'currency' => strtoupper((string) ($invoice->currency ?? 'EUR')),
                'status' => (string) ($invoice->status ?? ($paid ? 'paid' : 'open')),
                'paid_at' => $paid ? ($this->timestamp($invoice->status_transitions?->paid_at ?? null) ?: now()) : null,
                'attempted_at' => $this->timestamp($invoice->webhooks_delivered_at ?? null) ?: now(),
                'invoice_number' => $invoice->number ?? null,
                'invoice_date' => $this->timestamp($invoice->created ?? null),
                'period_start' => $this->timestamp($invoice->period_start ?? null),
                'period_end' => $this->timestamp($invoice->period_end ?? null),
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'invoice_pdf_url' => $invoice->invoice_pdf ?? null,
            ]
        );

        $this->syncPayment($localInvoice, $invoice, $subscription, $paid);

        if (! $subscription) {
            Log::info('Stripe invoice did not match a local subscription.', [
                'stripe_invoice_id' => $stripeInvoiceId,
                'stripe_subscription_id' => $stripeSubscriptionId,
            ]);

            return;
        }

        if ($paid && $subscription->status !== SaasSubscription::STATUS_CANCELED) {
            $subscription->update([
                'status' => SaasSubscription::STATUS_ACTIVE,
                'payment_failed_at' => null,
            ]);

            if ($subscription->company && $subscription->project) {
                app(ApplicationTrialService::class)->markConverted(
                    $subscription->company,
                    $subscription->project
                );
            }
        }

        if (! $paid) {
            $subscription->update([
                'status' => SaasSubscription::STATUS_PAST_DUE,
                'payment_failed_at' => $subscription->payment_failed_at
                    ?: $this->timestamp($eventCreatedAt)
                    ?: $localInvoice->attempted_at
                    ?: now(),
            ]);
        }
    }

    private function syncPayment(SaasInvoice $invoice, StripeObject $stripeInvoice, ?SaasSubscription $subscription, bool $paid): void
    {
        $paymentIntentId = $this->stripeId($stripeInvoice->payment_intent ?? null);
        $paymentIntent = is_object($stripeInvoice->payment_intent)
            ? $stripeInvoice->payment_intent
            : null;
        $paymentMethod = $paymentIntent?->payment_method_details ?? $stripeInvoice->payment_method_details ?? null;
        $charge = $paymentIntent?->latest_charge ?? $stripeInvoice->charge ?? null;

        $attributes = $paymentIntentId
            ? ['stripe_payment_intent_id' => $paymentIntentId]
            : ['saas_invoice_id' => $invoice->id];

        SaasPayment::query()->updateOrCreate(
            $attributes,
            [
                'project_id' => $invoice->project_id,
                'company_id' => $invoice->company_id,
                'saas_subscription_id' => $subscription?->id,
                'saas_invoice_id' => $invoice->id,
                'stripe_charge_id' => $this->stripeId($charge?->id ?? $charge),
                'amount' => (int) ($invoice->amount_paid ?: $invoice->amount_due),
                'currency' => $invoice->currency,
                'status' => $paid ? 'paid' : (string) ($stripeInvoice->status ?: 'failed'),
                'paid_at' => $paid ? $invoice->paid_at : null,
                'payment_method_type' => $paymentMethod?->type ?? null,
                'payment_method_brand' => $paymentMethod?->card?->brand ?? null,
                'payment_method_last4' => $paymentMethod?->card?->last4 ?? null,
            ]
        );
    }

    private function acknowledgeUnsupported(Event $event): void
    {
        Log::info('Stripe webhook event acknowledged without processing.', [
            'stripe_event_id' => $event->id,
            'type' => $event->type,
        ]);
    }

    private function priceFromSubscription(StripeObject $subscription): ?SaasPlanPrice
    {
        $stripePriceId = $this->subscriptionPriceId($subscription);

        if (! $stripePriceId) {
            return null;
        }

        return SaasPlanPrice::query()
            ->where('stripe_price_id', $stripePriceId)
            ->with('plan')
            ->first();
    }

    private function priceFromMetadata(mixed $metadata): ?SaasPlanPrice
    {
        $priceId = $metadata?->plan_price_id ?? $metadata?->saas_plan_price_id ?? null;

        if (! $priceId) {
            return null;
        }

        return SaasPlanPrice::query()
            ->with('plan')
            ->find($priceId);
    }

    private function subscriptionPriceId(StripeObject $subscription): ?string
    {
        $items = $subscription->items?->data ?? [];
        $item = $items[0] ?? null;

        return $this->stripeId($item?->price?->id ?? null);
    }

    private function invoiceSubscriptionId(StripeObject $invoice): ?string
    {
        return $this->stripeId($invoice->subscription ?? null)
            ?: $this->stripeId($invoice->parent?->subscription_details?->subscription ?? null);
    }

    private function companyFromMetadata(mixed $metadata): ?Company
    {
        $companyId = $metadata?->company_id ?? $metadata?->internal_company_id ?? null;

        if (! $companyId) {
            return null;
        }

        return Company::query()->find($companyId);
    }

    private function companyFromStripeCustomer(?string $stripeCustomerId): ?Company
    {
        if (! $stripeCustomerId) {
            return null;
        }

        return Company::query()
            ->where('stripe_customer_id', $stripeCustomerId)
            ->first()
            ?: SaasBillingCustomer::query()
                ->where('stripe_customer_id', $stripeCustomerId)
                ->with('company')
                ->first()
                ?->company;
    }

    private function companyFromExistingSubscription(string $stripeSubscriptionId, string $stripeCustomerId): ?Company
    {
        $subscription = SaasSubscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->orWhere('stripe_customer_id', $stripeCustomerId)
            ->with('company')
            ->first();

        return $subscription?->company;
    }

    private function subscriptionStatus(mixed $status): string
    {
        $status = (string) $status;

        return in_array($status, SaasSubscription::STATUSES, true)
            ? $status
            : SaasSubscription::STATUS_INCOMPLETE;
    }

    private function timestamp(mixed $timestamp): ?Carbon
    {
        if (! is_numeric($timestamp) || (int) $timestamp <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $timestamp);
    }

    private function stripeId(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}