<?php

namespace App\Services\ProjectBilling;

use App\Models\Company;
use App\Models\ProjectBillingCustomer;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stripe\StripeClient;
use Stripe\StripeObject;

/**
 * Low-level Stripe access for Custom Project Billing. Uses the same Stripe SDK and
 * account as SaaS billing but shares no domain logic with it. Every object created
 * here is tagged with the billing-domain metadata so SaaS sync ignores it.
 */
class StripeProjectBillingGateway
{
    private StripeClient $stripe;

    public function __construct()
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $this->stripe = new StripeClient($secret);
    }

    public function domainMetadata(array $extra = []): array
    {
        return array_merge(['billing_domain' => config('billing.domain_tag')], $extra);
    }

    /**
     * Exactly one Stripe Customer per Company for this domain, reused forever.
     */
    public function resolveCustomer(Company $company): string
    {
        $record = DB::transaction(function () use ($company): ProjectBillingCustomer {
            return ProjectBillingCustomer::query()
                ->lockForUpdate()
                ->firstOrCreate(['company_id' => $company->id]);
        });

        $payload = [
            'name' => $company->name,
            'metadata' => $this->domainMetadata(['company_id' => (string) $company->id]),
        ];

        $email = $company->resolveBillingEmail();

        if ($email) {
            $payload['email'] = $email;
        }

        if ($company->billing_phone) {
            $payload['phone'] = $company->billing_phone;
        }

        $address = array_filter([
            'line1' => $company->billing_address_line1,
            'line2' => $company->billing_address_line2,
            'city' => $company->billing_address_city,
            'postal_code' => $company->billing_address_postal_code,
            'country' => $company->billing_address_country,
        ]);

        if ($address) {
            $payload['address'] = $address;
        }

        if ($record->stripe_customer_id) {
            $this->stripe->customers->update($record->stripe_customer_id, $payload);

            return $record->stripe_customer_id;
        }

        $customer = $this->stripe->customers->create($payload);
        $record->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    public function createProduct(string $name, ?string $description, array $metadata = []): StripeObject
    {
        return $this->stripe->products->create(array_filter([
            'name' => $name,
            'description' => $description ?: null,
            'metadata' => $this->domainMetadata($metadata),
        ], fn ($value) => $value !== null));
    }

    public function createRecurringPrice(
        string $productId,
        int $unitAmount,
        string $currency,
        string $interval,
        int $intervalCount,
        array $metadata = []
    ): StripeObject {
        return $this->stripe->prices->create([
            'product' => $productId,
            'unit_amount' => $unitAmount,
            'currency' => strtolower($currency),
            'recurring' => [
                'interval' => $interval,
                'interval_count' => $intervalCount,
            ],
            'metadata' => $this->domainMetadata($metadata),
        ]);
    }

    /**
     * Invoice-first: `send_invoice` never attempts an upfront charge, so a customer
     * without a card can still receive and pay the first invoice. Stripe then stores
     * the card used for that payment as the subscription's default payment method.
     */
    public function createSubscription(
        string $customerId,
        array $items,
        string $collectionMethod,
        array $metadata = [],
        ?string $idempotencyKey = null
    ): StripeObject {
        $payload = [
            'customer' => $customerId,
            'items' => $items,
            'collection_method' => $collectionMethod,
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'metadata' => $this->domainMetadata($metadata),
        ];

        if ($collectionMethod === 'send_invoice') {
            $payload['days_until_due'] = (int) config('billing.invoice.due_days');
        }

        return $this->stripe->subscriptions->create(
            array_merge($payload, ['expand' => ['latest_invoice']]),
            $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : []
        );
    }

    public function retrieveSubscription(string $subscriptionId): StripeObject
    {
        return $this->stripe->subscriptions->retrieve($subscriptionId, []);
    }

    public function updateSubscription(string $subscriptionId, array $payload): StripeObject
    {
        return $this->stripe->subscriptions->update($subscriptionId, $payload);
    }

    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd): StripeObject
    {
        if ($atPeriodEnd) {
            return $this->stripe->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => true,
            ]);
        }

        return $this->stripe->subscriptions->cancel($subscriptionId, []);
    }

    public function pauseSubscription(string $subscriptionId, bool $paused): StripeObject
    {
        return $this->stripe->subscriptions->update($subscriptionId, [
            'pause_collection' => $paused ? ['behavior' => 'void'] : null,
        ]);
    }

    public function createInvoice(string $customerId, array $payload): StripeObject
    {
        return $this->stripe->invoices->create(array_merge([
            'customer' => $customerId,
            // We send our own invoice email, so Stripe must never auto-advance/email.
            'auto_advance' => false,
            'metadata' => $this->domainMetadata(),
        ], $payload));
    }

    public function createInvoiceItem(string $customerId, string $invoiceId, array $payload): StripeObject
    {
        return $this->stripe->invoiceItems->create(array_merge([
            'customer' => $customerId,
            'invoice' => $invoiceId,
            'metadata' => $this->domainMetadata(),
        ], $payload));
    }

    public function finalizeInvoice(string $invoiceId, bool $autoAdvance = false): StripeObject
    {
        return $this->stripe->invoices->finalizeInvoice($invoiceId, ['auto_advance' => $autoAdvance]);
    }

    public function updateInvoice(string $invoiceId, array $payload): StripeObject
    {
        return $this->stripe->invoices->update($invoiceId, $payload);
    }

    public function retrieveInvoice(string $invoiceId): StripeObject
    {
        return $this->stripe->invoices->retrieve($invoiceId, []);
    }

    public function voidInvoice(string $invoiceId): StripeObject
    {
        return $this->stripe->invoices->voidInvoice($invoiceId, []);
    }

    public function retrieveCharge(string $chargeId): StripeObject
    {
        return $this->stripe->charges->retrieve($chargeId, ['expand' => ['balance_transaction']]);
    }
}
