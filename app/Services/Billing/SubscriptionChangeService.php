<?php

namespace App\Services\Billing;

use App\Models\Project;
use App\Models\SaasPlanPrice;
use App\Models\SaasSubscription;
use RuntimeException;

/**
 * Business logic for changing/cancelling/resuming an existing subscription - decides
 * immediate-vs-scheduled and keeps the local SaasSubscription row in sync with what was
 * just requested from Stripe. Stripe/StripeBillingService remain the only things that
 * actually talk to Stripe; this service never calls the Stripe SDK directly.
 */
class SubscriptionChangeService
{
    public function __construct(private StripeBillingService $stripe)
    {
    }

    /**
     * @throws RuntimeException
     */
    public function changePlan(SaasSubscription $subscription, SaasPlanPrice $newPrice): SaasSubscription
    {
        if (!$newPrice->active || !$newPrice->stripe_price_id || !$newPrice->plan?->active) {
            throw new RuntimeException('The selected price is not available.');
        }

        if ($newPrice->plan->project_id !== $subscription->project_id) {
            throw new RuntimeException('The selected price does not belong to this SaaS Project.');
        }

        $isUpgrade = (int) $newPrice->amount >= (int) $subscription->price->amount;

        if ($isUpgrade) {
            $this->stripe->changeSubscriptionPriceImmediately($subscription, $newPrice);

            $subscription->update([
                'saas_plan_id' => $newPrice->saas_plan_id,
                'saas_plan_price_id' => $newPrice->id,
                'scheduled_saas_plan_id' => null,
                'scheduled_saas_plan_price_id' => null,
                'stripe_schedule_id' => null,
            ]);

            return $subscription->fresh();
        }

        $schedule = $this->stripe->scheduleSubscriptionPriceChange($subscription, $newPrice);

        $subscription->update([
            'scheduled_saas_plan_id' => $newPrice->saas_plan_id,
            'scheduled_saas_plan_price_id' => $newPrice->id,
            'stripe_schedule_id' => $schedule->id,
        ]);

        return $subscription->fresh();
    }

    public function cancelAtPeriodEnd(SaasSubscription $subscription): SaasSubscription
    {
        $this->stripe->setCancelAtPeriodEnd($subscription, true);

        $subscription->update(['cancel_at_period_end' => true]);

        return $subscription->fresh();
    }

    public function resume(SaasSubscription $subscription): SaasSubscription
    {
        $this->stripe->setCancelAtPeriodEnd($subscription, false);

        $subscription->update(['cancel_at_period_end' => false]);

        return $subscription->fresh();
    }
}
