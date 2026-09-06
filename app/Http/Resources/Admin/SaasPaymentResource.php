<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class SaasPaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'amount' => $this->amount,
            'currency' => strtoupper($this->currency),
            'status' => $this->status,
            'payment_method_type' => $this->payment_method_type,
            'payment_method_brand' => $this->payment_method_brand,
            'payment_method_last4' => $this->payment_method_last4,
            'saas_invoice_id' => $this->saas_invoice_id,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->display_label,
            ]),
        ];
    }
}
