<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DynamicFieldValueValidator
{
    public function validate(iterable $definitions, array $values): array
    {
        $rules = [];
        $normalized = [];
        foreach ($definitions as $definition) {
            $key = is_array($definition) ? $definition['key'] : $definition->key;
            $type = is_array($definition) ? $definition['type'] : $definition->type;
            $required = (bool) (is_array($definition) ? ($definition['required'] ?? false) : $definition->required);
            $options = is_array($definition) ? ($definition['options'] ?? []) : ($definition->options ?? []);
            $default = is_array($definition) ? ($definition['default_value'] ?? null) : $definition->default_value;
            $base = $required ? ['required'] : ['nullable'];
            $typeRules = match ($type) {
                'text', 'textarea', 'radio' => ['string', 'max:5000'],
                'number' => ['numeric'],
                'date' => ['date_format:Y-m-d'],
                'checkbox' => ['boolean'],
                'select' => [Rule::in(array_column($options, 'value'))],
                'multi_select' => ['array'],
                default => ['prohibited'],
            };
            $rules[$key] = [...$base, ...$typeRules];
            if ($type === 'multi_select') $rules[$key.'.*'] = [Rule::in(array_column($options, 'value'))];
            $normalized[$key] = array_key_exists($key, $values) ? $values[$key] : $default;
        }
        return Validator::make($normalized, $rules)->validate();
    }
}