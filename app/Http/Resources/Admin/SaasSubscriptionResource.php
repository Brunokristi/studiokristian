<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaasSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'company_id' => $this->company_id,
            'saas_plan_id' => $this->saas_plan_id,
            'saas_plan_price_id' => $this->saas_plan_price_id,
            'status' => $this->status,
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'stripe_customer_id' => $this->stripe_customer_id,
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->display_label,
            ]),
            'plan' => new SaasPlanResource($this->whenLoaded('plan')),
            'price' => new SaasPlanPriceResource($this->whenLoaded('price')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}