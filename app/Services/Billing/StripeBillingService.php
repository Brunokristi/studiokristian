<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Models\SaasBillingCustomer;
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
     * @throws ApiErrorException
     */
    public function createOrUpdateCustomer(Company $company, ?string $email = null, ?int $projectId = null): StripeObject
    {
        $payload = [
            'name' => $company->display_label,
            'metadata' => [
                'company_id' => (string) $company->id,
            ],
        ];

        if ($email) {
            $payload['email'] = $email;
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
            return $this->stripe->customers->update(
                $stripeCustomerId,
                $payload
            );
        }

        $customer = $this->stripe->customers->create($payload);

        if ($projectId) {
            SaasBillingCustomer::query()->create([
                'project_id' => $projectId,
                'company_id' => $company->id,
                'stripe_customer_id' => $customer->id,
            ]);
        } else {
            $company->update([
                'stripe_customer_id' => $customer->id,
            ]);
        }

        return $customer;
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