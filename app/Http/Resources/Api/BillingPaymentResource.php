<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class BillingPaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->paid_at?->toIso8601String(),
            'amount' => $this->amount,
            'currency' => strtoupper($this->currency),
            'status' => $this->status,
            'payment_method' => $this->payment_method_brand
                ? [
                    'type' => $this->payment_method_type,
                    'brand' => $this->payment_method_brand,
                    'last4' => $this->payment_method_last4,
                ]
                : null,
            'invoice_id' => $this->saas_invoice_id,
        ];
    }
}
