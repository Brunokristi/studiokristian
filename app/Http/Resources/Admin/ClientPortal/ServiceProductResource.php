<?php

namespace App\Http\Resources\Admin\ClientPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_sk' => data_get(
                $this->name_translations,
                'sk',
                ''
            ),
            'slug' => $this->slug,
            'description' => $this->description,
            'description_sk' => data_get(
                $this->description_translations,
                'sk',
                ''
            ),
            'active' => $this->active,
            'sort_order' => $this->sort_order,

            'projects_count' =>
                $this->whenCounted('projects'),

            'services' =>
                ServiceResource::collection(
                    $this->whenLoaded('services')
                ),

            'services_count' =>
                $this->whenCounted('services'),

            'updated_at' =>
                $this->updated_at?->toIso8601String(),
        ];
    }
}
