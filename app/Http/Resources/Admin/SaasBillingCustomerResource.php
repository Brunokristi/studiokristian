<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class SaasBillingCustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->display_label,
            'email' => $this->billing_email,
            'phone' => $this->billing_phone,
            'address' => $this->address,
            'ico' => $this->ico,
            'dic' => $this->dic,
            'ic_dph' => $this->ic_dph,
            'stripe_customer_id' => $this->stripe_customer_id,
        ];
    }
}
