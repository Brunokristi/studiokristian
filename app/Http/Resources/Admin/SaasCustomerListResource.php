<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class SaasCustomerListResource extends JsonResource
{
    public function toArray($request): array
    {
        $subscription = $this->saasSubscriptions->first();

        return [
            'id' => $this->id,
            'name' => $this->display_label,
            'status' => $subscription?->status,
            'plan' => $subscription?->plan?->name,
            'plan_price' => $subscription?->price ? [
                'amount' => $subscription->price->amount,
                'currency' => $subscription->price->currency,
                'interval' => $subscription->price->interval,
            ] : null,
        ];
    }
}
