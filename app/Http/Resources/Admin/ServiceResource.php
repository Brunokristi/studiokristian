<?php

namespace App\Http\Resources\Admin;

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
            'name_sk' => data_get(
                $this->name_translations,
                'sk',
                ''
            ),
            'description' => $this->description,
            'description_sk' => data_get(
                $this->description_translations,
                'sk',
                ''
            ),
            'active' => $this->active,
            'sort_order' => $this->sort_order,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
