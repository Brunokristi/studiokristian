<?php

namespace App\Http\Requests\Admin;

class StoreCompanyRequest extends AdminClientPortalRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'registration_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'tax_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'vat_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            'address' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'billing_contact_id' => [
                'nullable',
                'integer',
                'exists:client_contacts,id',
            ],

            'status' => [
                'required',
                'in:active,inactive,archived',
            ],

            'internal_notes' => [
                'nullable',
                'string',
                'max:10000',
            ],
        ];
    }
}
