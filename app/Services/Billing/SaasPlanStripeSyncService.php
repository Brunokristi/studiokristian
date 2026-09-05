<?php

namespace App\Services\Billing;

use App\Models\Project;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SaasPlanStripeSyncService
{
    public function __construct(
        private readonly StripeBillingService $stripe
    ) {}

    /**
     * @throws Throwable
     */
    public function createPlan(Project $project, array $data): SaasPlan
    {
        $createdStripeProductId = null;

        try {
            return DB::transaction(function () use ($project, $data, &$createdStripeProductId): SaasPlan {
                $plan = $project->saasPlans()->create([
                    ...collect($data)->except('prices')->all(),
                    'stripe_product_id' => null,
                ]);

                $product = $this->stripe->createProduct($plan);
                $createdStripeProductId = $product->id;

                $plan->update([
                    'stripe_product_id' => $product->id,
                ]);

                $this->syncPrices($plan->fresh(), $data['prices'] ?? []);

                return $plan->fresh(['prices']);
            });
        } catch (Throwable $exception) {
            if ($createdStripeProductId) {
                Log::warning('Stripe product was created but local SaaS plan transaction failed.', [
                    'stripe_product_id' => $createdStripeProductId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    /**
     * @throws Throwable
     */
    public function updatePlan(SaasPlan $plan, array $data): SaasPlan
    {
        return DB::transaction(function () use ($plan, $data): SaasPlan {
            $plan->update([
                ...collect($data)->except('prices')->all(),
            ]);

            $product = $this->stripe->updateProduct($plan->fresh());

            if (! $plan->stripe_product_id) {
                $plan->update([
                    'stripe_product_id' => $product->id,
                ]);
            }

            $this->syncPrices($plan->fresh(), $data['prices'] ?? []);

            return $plan->fresh(['prices'])->loadCount('subscriptions');
        });
    }

    /**
     * @throws Throwable
     */
    public function archivePlan(SaasPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $plan->update([
                'active' => false,
            ]);

            $this->stripe->deactivateProduct($plan);

            foreach ($plan->prices as $price) {
                $price->update([
                    'active' => false,
                ]);

                $this->stripe->deactivatePrice($price);
            }
        });
    }

    /**
     * @throws Throwable
     */
    private function syncPrices(SaasPlan $plan, array $prices): void
    {
        $seen = [];

        foreach ($prices as $priceData) {
            $priceId = $priceData['id'] ?? null;
            $payload = [
                'amount' => (int) $priceData['amount'],
                'currency' => strtoupper((string) $priceData['currency']),
                'interval' => (string) $priceData['interval'],
                'active' => (bool) $priceData['active'],
            ];

            if ($priceId) {
                /** @var SaasPlanPrice $price */
                $price = $plan->prices()->whereKey($priceId)->firstOrFail();

                if ($this->corePriceChanged($price, $payload) && $price->stripe_price_id) {
                    $price->update([
                        'active' => false,
                    ]);

                    $this->stripe->deactivatePrice($price);

                    $newPrice = $plan->prices()->create($payload);
                    $stripePrice = $this->stripe->createPrice($newPrice->load('plan'));
                    $newPrice->update([
                        'stripe_price_id' => $stripePrice->id,
                    ]);

                    $seen[] = $price->id;
                    $seen[] = $newPrice->id;

                    continue;
                }

                $price->update($payload);

                if (! $price->stripe_price_id) {
                    $stripePrice = $this->stripe->createPrice($price->fresh(['plan']));
                    $price->update([
                        'stripe_price_id' => $stripePrice->id,
                    ]);
                } else {
                    $this->stripe->updatePriceActiveState($price->fresh(['plan']));
                }

                $seen[] = $price->id;

                continue;
            }

            $newPrice = $plan->prices()->create($payload);
            $stripePrice = $this->stripe->createPrice($newPrice->load('plan'));
            $newPrice->update([
                'stripe_price_id' => $stripePrice->id,
            ]);
            $seen[] = $newPrice->id;
        }

        $plan->prices()
            ->whereNotIn('id', $seen)
            ->get()
            ->each(function (SaasPlanPrice $price): void {
                $price->update([
                    'active' => false,
                ]);

                $this->stripe->deactivatePrice($price);
            });
    }

    private function corePriceChanged(SaasPlanPrice $price, array $payload): bool
    {
        return (int) $price->amount !== (int) $payload['amount'] ||
            strtoupper($price->currency) !== strtoupper($payload['currency']) ||
            $price->interval !== $payload['interval'];
    }
}