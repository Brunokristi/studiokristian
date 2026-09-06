<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaasPlanFeatureValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'feature_id' => $this->saas_feature_id,
            'key' => $this->feature?->key,
            'name' => $this->feature?->name,
            'type' => $this->feature?->type,
            'unit' => $this->feature?->unit,
            'boolean_value' => $this->boolean_value,
            'limit_value' => $this->limit_value,
            'is_unlimited' => $this->is_unlimited,
            'is_custom' => $this->is_custom,
        ];
    }
}
