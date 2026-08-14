<?php

namespace App\Services;

use App\Models\ServiceProduct;

class ServiceProductReadinessService
{
    public function inspect(ServiceProduct $product): array
    {
        $blueprintVersion = $product->blueprint?->versions()->where('status', 'published')->latest('published_at')->first()
            ?? $product->blueprint?->versions()->latest('id')->first();
        $template = $product->defaultContractTemplate;
        $contractVersion = $template?->versions()->where('status', 'published')->latest('published_at')->first()
            ?? $template?->versions()->latest('id')->first();
        $missing = [];
        if (! $blueprintVersion) $missing[] = 'published_blueprint';
        if (! $template) $missing[] = 'default_contract_template';
        elseif (! $contractVersion) $missing[] = 'published_contract_version';
        return compact('missing', 'blueprintVersion', 'template', 'contractVersion') + ['ready' => $missing === []];
    }
}