<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class BillingInvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->invoice_number,
            'date' => $this->invoice_date?->toIso8601String(),
            'amount_due' => $this->amount_due,
            'amount_paid' => $this->amount_paid,
            'currency' => strtoupper($this->currency),
            'status' => $this->status,
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'view_url' => $this->hosted_invoice_url,
            'pdf_url' => $this->invoice_pdf_url,
        ];
    }
}
