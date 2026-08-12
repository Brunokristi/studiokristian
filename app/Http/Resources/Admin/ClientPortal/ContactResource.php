<?php

namespace App\Http\Resources\Admin\ClientPortal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'active' => $this->active,
            'can_access_portal' => $this->can_access_portal,
            'can_accept_documents' => $this->can_accept_documents,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}