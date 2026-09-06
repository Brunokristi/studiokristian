<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class BillingPlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'features' => $this->features ?: [],
            'prices' => $this->prices->map(fn ($price) => [
                'id' => $price->id,
                'amount' => $price->amount,
                'currency' => strtoupper($price->currency),
                'interval' => $price->interval,
                'active' => (bool) $price->active,
            ])->values(),
            'entitlements' => $this->entitlementsApiArray(),
        ];
    }
}
