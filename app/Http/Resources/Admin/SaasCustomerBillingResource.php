<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class SaasCustomerBillingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'company' => new SaasBillingCustomerResource($this->resource['company']),
            'subscriptions' => SaasSubscriptionResource::collection($this->resource['subscriptions']),
            'payments' => SaasPaymentResource::collection($this->resource['payments']),
            'invoices' => SaasInvoiceResource::collection($this->resource['invoices']),
        ];
    }
}
