<?php

namespace App\Http\Resources\Admin\ClientPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_product_id' => $this->service_product_id,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
            'sort_order' => $this->sort_order,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}