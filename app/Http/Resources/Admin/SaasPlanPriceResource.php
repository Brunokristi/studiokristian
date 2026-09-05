<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaasPlanPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'saas_plan_id' => $this->saas_plan_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'interval' => $this->interval,
            'active' => $this->active,
            'stripe_price_id' => $this->stripe_price_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}