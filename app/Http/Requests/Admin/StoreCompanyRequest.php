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
        ];
    }
}