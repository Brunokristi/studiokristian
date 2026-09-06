<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class SaasInvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date?->toIso8601String(),
            'amount_due' => $this->amount_due,
            'amount_paid' => $this->amount_paid,
            'currency' => strtoupper($this->currency),
            'status' => $this->status,
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'hosted_invoice_url' => $this->hosted_invoice_url,
            'invoice_pdf_url' => $this->invoice_pdf_url,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->display_label,
            ]),
        ];
    }
}
