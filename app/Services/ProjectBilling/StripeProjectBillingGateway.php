<?php

namespace App\Services\ProjectBilling;

use App\Models\Company;
use App\Models\ProjectBillingCustomer;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\StripeObject;

class StripeProjectBillingGateway
{
    private StripeClient $stripe;

    public function __construct()
    {
        $secret =
            config('services.stripe.secret');

        if (
            ! is_string($secret) ||
            $secret === ''
        ) {
            throw new RuntimeException(
                'Stripe secret key is not configured.'
            );
        }

        $this->stripe =
            new StripeClient($secret);
    }

    public function domainMetadata(
        array $extra = []
    ): array {
        return array_merge(
            [
                'billing_domain' =>
                    config(
                        'billing.domain_tag'
                    ),
            ],
            $extra
        );
    }

    public function resolveCustomer(
        Company $company
    ): string {
        $company->loadMissing(
            'billingContact'
        );

        $record =
            DB::transaction(
                function () use (
                    $company
                ): ProjectBillingCustomer {
                    return ProjectBillingCustomer::query()
                        ->lockForUpdate()
                        ->firstOrCreate([
                            'company_id' =>
                                $company->id,
                        ]);
                }
            );

        $payload = [
            'name' =>
                $company->name,

            'metadata' =>
                $this->domainMetadata([
                    'company_id' =>
                        (string) $company->id,
                ]),
        ];

        $billingContact =
            $company->billingContact;

        if (
            $billingContact?->email
        ) {
            $payload['email'] =
                $billingContact->email;
        }

        if (
            $billingContact?->phone
        ) {
            $payload['phone'] =
                $billingContact->phone;
        }

        /*
         * Company address is intentionally one field.
         *
         * Stripe accepts a structured address, but our application stores
         * the address as the single canonical company address string.
         *
         * We therefore do not invent street/city/postal values from it.
         */
        if (
            trim(
                (string) $company->address
            ) !== ''
        ) {
            $payload['description'] =
                'Company address: ' .
                trim(
                    (string) $company->address
                );
        }

        if (
            $record->stripe_customer_id
        ) {
            try {
                $this->stripe
                    ->customers
                    ->update(
                        $record->stripe_customer_id,
                        $payload
                    );

                return $record->stripe_customer_id;
            } catch (ApiErrorException $exception) {
                if ($exception->getStripeCode() !== 'resource_missing') {
                    throw $exception;
                }

                // The local ID belongs to an old/test Stripe account; replace it safely.
                $record->update(['stripe_customer_id' => null]);
            }
        }

        $customer =
            $this->stripe
                ->customers
                ->create($payload);

        $record->update([
            'stripe_customer_id' =>
                $customer->id,
        ]);

        return $customer->id;
    }

    public function isFutureForCustomer(string $customerId, int $timestamp): bool
    {
        $customer = $this->stripe->customers->retrieve($customerId, []);
        $reference = time();

        if ($customer->test_clock) {
            $clockId = is_string($customer->test_clock)
                ? $customer->test_clock
                : $customer->test_clock->id;
            $clock = $this->stripe->testHelpers->testClocks->retrieve($clockId, []);
            $reference = (int) ($clock->frozen_time ?? $reference);
        }

        return $timestamp > $reference;
    }

    public function createProduct(
        string $name,
        ?string $description,
        array $metadata = []
    ): StripeObject {
        return $this->stripe
            ->products
            ->create(
                array_filter([
                    'name' =>
                        $name,

                    'description' =>
                        $description ?: null,

                    'metadata' =>
                        $this->domainMetadata(
                            $metadata
                        ),
                ], fn ($value) =>
                    $value !== null
                )
            );
    }

    public function createRecurringPrice(
        string $productId,
        int $unitAmount,
        string $currency,
        string $interval,
        int $intervalCount,
        array $metadata = []
    ): StripeObject {
        return $this->stripe
            ->prices
            ->create([
                'product' =>
                    $productId,

                'unit_amount' =>
                    $unitAmount,

                'currency' =>
                    strtolower(
                        $currency
                    ),

                'recurring' => [
                    'interval' =>
                        $interval,

                    'interval_count' =>
                        $intervalCount,
                ],

                'metadata' =>
                    $this->domainMetadata(
                        $metadata
                    ),
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
        ?string $idempotencyKey = null,
        ?int $backdateStartDate = null
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

        if ($backdateStartDate) {
            $payload['backdate_start_date'] = $backdateStartDate;
        }

        return $this->stripe->subscriptions->create(
            array_merge($payload, ['expand' => ['latest_invoice']]),
            $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : []
        );
    }

    public function createFutureSchedule(
        string $customerId,
        array $items,
        int $startDate,
        string $collectionMethod,
        array $metadata = [],
        ?int $endDate = null
    ): StripeObject {
        $phase = [
            'start_date' => $startDate,
            'items' => $items,
            'collection_method' => $collectionMethod,
            'metadata' => $this->domainMetadata($metadata),
        ];

        if ($collectionMethod === 'send_invoice') {
            $phase['invoice_settings'] = [
                'days_until_due' => (int) config('billing.invoice.due_days'),
            ];
        }

        if ($endDate) {
            $phase['end_date'] = $endDate;
        }

        return $this->stripe->subscriptionSchedules->create([
            'customer' => $customerId,
            'start_date' => $startDate,
            'end_behavior' => $endDate ? 'cancel' : 'release',
            'phases' => [$phase],
            'metadata' => $this->domainMetadata($metadata),
        ]);
    }

    public function retrieveSubscription(string $subscriptionId): StripeObject
    {
        return $this->stripe->subscriptions->retrieve($subscriptionId, []);
    }

    public function updateSubscription(string $subscriptionId, array $payload): StripeObject
    {
        return $this->stripe->subscriptions->update($subscriptionId, $payload);
    }

    public function previewSubscriptionUpdate(
        string $customerId,
        string $subscriptionId,
        array $items,
        int $prorationDate
    ): StripeObject {
        return $this->stripe->invoices->createPreview([
            'customer' => $customerId,
            'subscription' => $subscriptionId,
            'subscription_details' => [
                'items' => $items,
                'proration_date' => $prorationDate,
            ],
        ]);
    }

    public function updateSubscriptionItems(string $subscriptionId, array $items): StripeObject
    {
        return $this->stripe->subscriptions->update($subscriptionId, [
            'items' => $items,
            'proration_behavior' => 'create_prorations',
        ]);
    }

    public function createScheduleFromSubscription(string $subscriptionId): StripeObject
    {
        return $this->stripe->subscriptionSchedules->create([
            'from_subscription' => $subscriptionId,
        ]);
    }

    public function retrieveSchedule(string $scheduleId): StripeObject
    {
        return $this->stripe->subscriptionSchedules->retrieve($scheduleId, []);
    }

    public function updateSchedule(string $scheduleId, array $payload): StripeObject
    {
        return $this->stripe->subscriptionSchedules->update($scheduleId, $payload);
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

    public function cancelSubscriptionAt(string $subscriptionId, int $cancelAt): StripeObject
    {
        return $this->stripe->subscriptions->update($subscriptionId, [
            'cancel_at' => $cancelAt,
        ]);
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

    public function listDraftInvoices(string $customerId): array
    {
        return $this->stripe->invoices->all([
            'customer' => $customerId,
            'status' => 'draft',
            'limit' => 100,
        ])->data ?? [];
    }

    /**
     * Deleting a draft releases its invoice number for reuse.
     */
    public function deleteInvoice(string $invoiceId): void
    {
        $this->stripe->invoices->delete($invoiceId, []);
    }

    /**
     * Marks a Stripe invoice as settled outside Stripe (e.g. bank transfer) so it
     * stops chasing the customer for payment.
     */
    public function payInvoiceOutOfBand(string $invoiceId): StripeObject
    {
        return $this->stripe->invoices->pay($invoiceId, ['paid_out_of_band' => true]);
    }

    public function voidInvoice(string $invoiceId): StripeObject
    {
        return $this->stripe->invoices->voidInvoice($invoiceId, []);
    }

    public function retrieveCharge(string $chargeId): StripeObject
    {
        return $this->stripe->charges->retrieve($chargeId, ['expand' => ['balance_transaction']]);
    }

    public function createRefund(string $paymentIntentId, int $amount, ?string $reason = null, ?string $idempotencyKey = null): StripeObject
    {
        $params = array_filter([
            'payment_intent' => $paymentIntentId,
            'amount' => $amount,
            'reason' => $reason,
        ], fn ($value) => $value !== null);

        return $this->stripe->refunds->create(
            $params,
            $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : []
        );
    }
}
