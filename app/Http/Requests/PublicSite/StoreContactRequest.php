<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'service' => ['nullable', 'string', 'max:150'],
            'contactMethod' => [
                'required',
                'string',
                Rule::in(['call', 'message', 'email', 'instagram', 'whatsapp']),
            ],
            'email' => ['required_if:contactMethod,email', 'nullable', 'email:rfc', 'max:255'],
            'phone' => [
                'required_if:contactMethod,call,message,whatsapp',
                'nullable',
                'string',
                'max:50',
            ],
            'instagram' => ['nullable', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:5000'],
            'locale' => ['nullable', 'string', 'in:en,sk'],

            // Honeypot field. Must stay empty.
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }
}
