<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContactRequest extends AdminClientPortalRequest
{
    public function rules(): array
    {
        $contactId = $this->route('contact')?->getKey();

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('client_contacts', 'email')->ignore($contactId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:100'],
            'active' => ['required', 'boolean'],
            'can_access_portal' => ['required', 'boolean'],
            'can_accept_documents' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->boolean('can_accept_documents') && ! $this->boolean('can_access_portal')) {
                $validator->errors()->add('can_accept_documents', 'Document acceptance requires portal access.');
            }
        }];
    }
}