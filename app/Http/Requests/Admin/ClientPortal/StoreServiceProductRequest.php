<?php

namespace App\Http\Requests\Admin\ClientPortal;

use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreServiceProductRequest extends AdminClientPortalRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
        }
    }

    public function rules(): array
    {
        $productId = $this->route('serviceProduct')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:255', Rule::unique('service_products', 'slug')->ignore($productId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}