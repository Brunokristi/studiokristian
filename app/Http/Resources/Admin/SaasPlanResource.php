<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaasPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'features' => $this->features ?: [],
            'active' => $this->active,
            'sort_order' => $this->sort_order,
            'stripe_product_id' => $this->stripe_product_id,
            'prices' => $this->whenLoaded('prices', fn () =>
                SaasPlanPriceResource::collection(
                    $this->prices->where('active', true)->values()
                )
            ),
            'subscriptions_count' => $this->whenCounted('subscriptions'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}