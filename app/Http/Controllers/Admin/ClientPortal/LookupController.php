<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ServiceProduct;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'companies' => Company::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'service_products' => ServiceProduct::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'active']),
        ]);
    }

    public function contacts(Company $company): JsonResponse
    {
        return response()->json($company->contacts()->where('active', true)->orderBy('last_name')->get([
            'id', 'company_id', 'first_name', 'last_name', 'email',
        ]));
    }
}