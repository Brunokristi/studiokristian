<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class BillingSubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'cancel_at_period_end' => (bool) $this->cancel_at_period_end,
            'payment_status' => $this->status === \App\Models\SaasSubscription::STATUS_PAST_DUE ? 'failed' : 'paid',
            'payment_failed_at' => $this->payment_failed_at?->toIso8601String(),
            'grace_period_ends_at' => $this->gracePeriodEndsAt()?->toIso8601String(),
            'payment_action_required' => $this->status === \App\Models\SaasSubscription::STATUS_PAST_DUE,
            'plan' => [
                'id' => $this->plan?->id,
                'name' => $this->plan?->name,
                'slug' => $this->plan?->slug,
            ],
            'entitlements' => $this->plan?->entitlementsApiArray() ?? [],
            'price' => [
                'id' => $this->price?->id,
                'amount' => $this->price?->amount,
                'currency' => $this->price?->currency,
                'interval' => $this->price?->interval,
            ],
            'scheduled_change' => $this->scheduled_saas_plan_price_id ? [
                'plan' => [
                    'id' => $this->scheduledPlan?->id,
                    'name' => $this->scheduledPlan?->name,
                    'slug' => $this->scheduledPlan?->slug,
                ],
                'price' => [
                    'id' => $this->scheduledPrice?->id,
                    'amount' => $this->scheduledPrice?->amount,
                    'currency' => $this->scheduledPrice?->currency,
                    'interval' => $this->scheduledPrice?->interval,
                ],
                'effective_at' => $this->current_period_end?->toIso8601String(),
            ] : null,
        ];
    }
}
