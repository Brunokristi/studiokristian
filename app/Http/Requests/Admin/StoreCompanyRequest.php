<?php

namespace App\Http\Requests\Admin;

class StoreCompanyRequest extends AdminClientPortalRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive,archived'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:64'],
            'billing_address_line1' => ['nullable', 'string', 'max:255'],
            'billing_address_line2' => ['nullable', 'string', 'max:255'],
            'billing_address_city' => ['nullable', 'string', 'max:255'],
            'billing_address_postal_code' => ['nullable', 'string', 'max:32'],
            'billing_address_country' => ['nullable', 'string', 'max:2'],
        ];
    }
}