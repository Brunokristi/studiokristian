<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ServiceProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->where('status', 'active')
            ->orderBy('name');

        if (! $request->user()?->is_admin) {
            $companies->whereHas('projects.coworkers', fn ($members) => $members->whereKey($request->user()->id));
        }

        return response()->json([
            'companies' => $companies->get(['id', 'name']),
            'service_products' => ServiceProduct::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'active']),
        ]);
    }

    public function contacts(Request $request, Company $company): JsonResponse
    {
        if (! $request->user()?->is_admin) {
            abort_unless(
                    $company->projects()->whereHas('members', fn ($members) => $members->whereKey($request->user()->id))->exists(),
                403
            );
        }

        return response()->json($company->contacts()->where('active', true)->orderBy('last_name')->get([
            'id', 'company_id', 'first_name', 'last_name', 'email',
        ]));
    }
}