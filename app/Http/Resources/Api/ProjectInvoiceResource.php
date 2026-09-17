<?php

namespace App\Http\Resources;

use App\Services\Billing\PayBySquareService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectInvoiceResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $payBySquare =
            app(
                PayBySquareService::class
            );

        $payment = null;

        if (
            $this->payment_method ===
            \App\Models\ProjectInvoice::METHOD_BANK_TRANSFER
            && $this->supplier_iban
            && $this->total > 0
        ) {
            $payment =
                $payBySquare
                    ->frontendData(
                        $this->resource
                    );
        }

        return [
            'id' => $this->id,

            'invoice_number' =>
                $this->invoice_number,

            'status' =>
                $this->status,

            'currency' =>
                $this->currency,

            'subtotal' =>
                $this->subtotal,

            'tax_amount' =>
                $this->tax_amount,

            'total' =>
                $this->total,

            'issue_date' =>
                $this->issue_date
                    ?->format('Y-m-d'),

            'delivery_date' =>
                $this->delivery_date
                    ?->format('Y-m-d'),

            'due_date' =>
                $this->due_date
                    ?->format('Y-m-d'),

            'variable_symbol' =>
                $this->variable_symbol,

            'payment_method' =>
                $this->payment_method,

            'pay_by_square' =>
                $payment,
        ];
    }
}