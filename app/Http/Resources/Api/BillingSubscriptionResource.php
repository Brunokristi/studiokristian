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
            'plan' => [
                'id' => $this->plan?->id,
                'name' => $this->plan?->name,
                'slug' => $this->plan?->slug,
            ],
            'price' => [
                'id' => $this->price?->id,
                'amount' => $this->price?->amount,
                'currency' => $this->price?->currency,
                'interval' => $this->price?->interval,
            ],
        ];
    }
}
