<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_label' => $this->display_label,
            'registration_number' => $this->registration_number,
            'tax_number' => $this->tax_number,
            'vat_number' => $this->vat_number,
            'address' => $this->address,
            'status' => $this->status,
            'stripe_customer_id' => $this->stripe_customer_id,
            'internal_notes' => $this->internal_notes,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'contacts_count' => $this->whenCounted('contacts'),
            'projects_count' => $this->whenCounted('projects'),
            'portal_contacts_count' => $this->whenCounted('portal_contacts'),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'projects' => ProjectResource::collection($this->whenLoaded('projects')),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}