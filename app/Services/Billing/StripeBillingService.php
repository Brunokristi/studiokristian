<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Models\SaasBillingCustomer;
use App\Models\SaasSubscription;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\StripeObject;

class StripeBillingService
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

    /**
     * @throws ApiErrorException
     */
    public function createProduct(SaasPlan $plan): StripeObject
    {
        return $this->stripe->products->create([
            'name' => $plan->name,
            'description' => $plan->description ?: null,
            'active' => (bool) $plan->active,
            'metadata' => [
                'project_id' => (string) $plan->project_id,
                'plan_id' => (string) $plan->id,
                'features' => $this->featuresMetadata($plan),
            ],
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function updateProduct(SaasPlan $plan): StripeObject
    {
        if (! $plan->stripe_product_id) {
            return $this->createProduct($plan);
        }

        return $this->stripe->products->update(
            $plan->stripe_product_id,
            [
                'name' => $plan->name,
                'description' => $plan->description ?: null,
                'active' => (bool) $plan->active,
                'metadata' => [
                    'project_id' => (string) $plan->project_id,
                    'plan_id' => (string) $plan->id,
                    'features' => $this->featuresMetadata($plan),
                ],
            ]
        );
    }

    /**
     * @throws ApiErrorException
     */
    public function deactivateProduct(SaasPlan $plan): void
    {
        if (! $plan->stripe_product_id) {
            return;
        }

        $this->stripe->products->update(
            $plan->stripe_product_id,
            [
                'active' => false,
            ]
        );
    }

    /**
     * @throws ApiErrorException
     */
    public function createPrice(SaasPlanPrice $price): StripeObject
    {
        $plan = $price->plan;

        if (! $plan?->stripe_product_id) {
            throw new RuntimeException('Cannot create a Stripe Price before the Stripe Product exists.');
        }

        return $this->stripe->prices->create([
            'product' => $plan->stripe_product_id,
            'unit_amount' => (int) $price->amount,
            'currency' => strtolower($price->currency),
            'recurring' => [
                'interval' => $this->stripeInterval($price->interval),
            ],
            'active' => (bool) $price->active,
            'metadata' => [
                'project_id' => (string) $plan->project_id,
                'plan_id' => (string) $plan->id,
                'plan_price_id' => (string) $price->id,
            ],
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function updatePriceActiveState(SaasPlanPrice $price): void
    {
        if (! $price->stripe_price_id) {
            return;
        }

        $this->stripe->prices->update(
            $price->stripe_price_id,
            [
                'active' => (bool) $price->active,
                'metadata' => [
                    'project_id' => (string) $price->plan->project_id,
                    'plan_id' => (string) $price->saas_plan_id,
                    'plan_price_id' => (string) $price->id,
                ],
            ]
        );
    }

    /**
     * @throws ApiErrorException
     */
    public function deactivatePrice(SaasPlanPrice $price): void
    {
        if (! $price->stripe_price_id) {
            return;
        }

        $this->stripe->prices->update(
            $price->stripe_price_id,
            [
                'active' => false,
            ]
        );
    }

    /**
     * Create/update the Stripe Customer for a Company using its real billing profile - never
     * the technical Customer Credential name. Updates the existing Stripe Customer in place
     * when one already exists for this (project, company) so subscriptions/invoices stay
     * attached to the same customer instead of a duplicate being created.
     *
     * @throws ApiErrorException
     */
    public function createOrUpdateCustomer(Company $company, ?string $email = null, ?int $projectId = null): StripeObject
    {
        $payload = [
            'name' => $company->name,
            'metadata' => array_filter([
                'company_id' => (string) $company->id,
                'registration_number' => $company->registration_number,
                'tax_number' => $company->tax_number,
            ]),
        ];

        $resolvedEmail = $email ?: $company->billing_email;
        if ($resolvedEmail) {
            $payload['email'] = $resolvedEmail;
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

        $billingCustomer = $projectId
            ? SaasBillingCustomer::query()
                ->where('project_id', $projectId)
                ->where('company_id', $company->id)
                ->first()
            : null;

        $stripeCustomerId =
            $billingCustomer?->stripe_customer_id ??
            (! $projectId ? $company->stripe_customer_id : null);

        if ($stripeCustomerId) {
            try {
                $customer = $this->stripe->customers->update(
                    $stripeCustomerId,
                    $payload
                );
            } catch (ApiErrorException $e) {
                // The stored Stripe Customer id is stale (e.g. deleted directly in Stripe, or
                // test-mode data was reset) - self-heal by minting a replacement instead of
                // permanently blocking checkout/profile sync for this Company.
                if ($e->getStripeCode() !== 'resource_missing') {
                    throw $e;
                }

                Log::warning('Stripe Customer id no longer exists - creating a replacement.', [
                    'company_id' => $company->id,
                    'stale_stripe_customer_id' => $stripeCustomerId,
                ]);

                $stripeCustomerId = null;
            }
        }

        if (!$stripeCustomerId) {
            $customer = $this->stripe->customers->create($payload);

            if ($projectId) {
                SaasBillingCustomer::query()->updateOrCreate(
                    ['project_id' => $projectId, 'company_id' => $company->id],
                    ['stripe_customer_id' => $customer->id]
                );
            } else {
                $company->update([
                    'stripe_customer_id' => $customer->id,
                ]);
            }
        }

        $this->syncVatTaxId($customer->id, $company->vat_number);

        return $customer;
    }

    public function listInvoicesForCustomer(string $stripeCustomerId): array
    {
        return $this->stripe->invoices->all([
            'customer' => $stripeCustomerId,
            'limit' => 100,
        ])->data ?? [];
    }

    /**
     * IC DPH/VAT ID uses Stripe's native EU VAT tax-id mechanism (generic `eu_vat` type -
     * Stripe has no dedicated Slovak type). IČO/DIČ are not Stripe-native tax ID types, so
     * they only live in `metadata` above, never here.
     */
    private function syncVatTaxId(string $stripeCustomerId, ?string $vatNumber): void
    {
        if (!$vatNumber || !preg_match('/^[A-Za-z]{2}[A-Za-z0-9]{2,12}$/', $vatNumber)) {
            return;
        }

        $value = strtoupper($vatNumber);

        try {
            $existing = $this->stripe->customers->allTaxIds($stripeCustomerId, ['limit' => 10]);

            foreach ($existing->data as $taxId) {
                if ($taxId->type === 'eu_vat' && $taxId->value === $value) {
                    return;
                }
            }

            $this->stripe->customers->createTaxId($stripeCustomerId, [
                'type' => 'eu_vat',
                'value' => $value,
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('Failed to sync Stripe customer VAT tax id.', [
                'stripe_customer_id' => $stripeCustomerId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @throws ApiErrorException
     */
    public function createCheckoutSession(
        Company $company,
        SaasPlanPrice $price,
        string $successUrl,
        string $cancelUrl,
        ?string $email = null,
        ?string $idempotencyKey = null
    ): StripeObject {
        if (! $price->active || ! $price->stripe_price_id || ! $price->plan?->active) {
            throw new RuntimeException('The selected SaaS price is not available for checkout.');
        }

        $customer = $this->createOrUpdateCustomer($company, $email, $price->plan->project_id);

        return $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customer->id,
            'line_items' => [
                [
                    'price' => $price->stripe_price_id,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $this->metadata($company, $price),
            'subscription_data' => [
                'metadata' => $this->metadata($company, $price),
            ],
        ], $idempotencyKey ? ['idempotency_key' => $idempotencyKey] : []);
    }

    private function metadata(Company $company, SaasPlanPrice $price): array
    {
        return [
            'company_id' => (string) $company->id,
            'saas_project_id' => (string) $price->plan->project_id,
            'project_id' => (string) $price->plan->project_id,
            'plan_id' => (string) $price->saas_plan_id,
            'plan_price_id' => (string) $price->id,
        ];
    }

    /**
     * Immediately move an existing subscription onto a different price, prorating the
     * difference for the remainder of the current period. Stripe determines the actual
     * proration/invoice amount - this only tells Stripe which price to switch to.
     *
     * @throws ApiErrorException
     */
    public function changeSubscriptionPriceImmediately(SaasSubscription $subscription, SaasPlanPrice $newPrice): StripeObject
    {
        $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->stripe_subscription_id);
        $itemId = $stripeSubscription->items->data[0]->id;

        return $this->stripe->subscriptions->update($subscription->stripe_subscription_id, [
            'items' => [['id' => $itemId, 'price' => $newPrice->stripe_price_id]],
            'proration_behavior' => 'create_prorations',
        ]);
    }

    /**
     * Schedule a subscription to move onto a different price only once the current billing
     * period ends (used for downgrades) - the customer keeps their current plan/price until
     * then. Uses Stripe's own Subscription Schedule mechanism rather than any local-only
     * scheduling, so Stripe remains authoritative for when/how the change actually applies.
     *
     * @throws ApiErrorException
     */
    public function scheduleSubscriptionPriceChange(SaasSubscription $subscription, SaasPlanPrice $newPrice): StripeObject
    {
        $schedule = $subscription->stripe_schedule_id
            ? $this->stripe->subscriptionSchedules->retrieve($subscription->stripe_schedule_id)
            : $this->stripe->subscriptionSchedules->create(['from_subscription' => $subscription->stripe_subscription_id]);

        $currentPhase = $schedule->phases[0];

        return $this->stripe->subscriptionSchedules->update($schedule->id, [
            'phases' => [
                [
                    'items' => $currentPhase->items,
                    'start_date' => $currentPhase->start_date,
                    'end_date' => $currentPhase->end_date,
                ],
                [
                    'items' => [['price' => $newPrice->stripe_price_id, 'quantity' => 1]],
                ],
            ],
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function setCancelAtPeriodEnd(SaasSubscription $subscription, bool $cancel): StripeObject
    {
        return $this->stripe->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => $cancel,
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function createBillingPortalSession(string $stripeCustomerId, string $returnUrl): StripeObject
    {
        return $this->stripe->billingPortal->sessions->create([
            'customer' => $stripeCustomerId,
            'return_url' => $returnUrl,
        ]);
    }

    private function stripeInterval(string $interval): string
    {
        return match ($interval) {
            SaasPlanPrice::INTERVAL_YEARLY => 'year',
            default => 'month',
        };
    }

    private function featuresMetadata(SaasPlan $plan): string
    {
        return collect($plan->features ?: [])
            ->map(fn ($feature) => trim((string) $feature))
            ->filter()
            ->implode(', ');
    }
}