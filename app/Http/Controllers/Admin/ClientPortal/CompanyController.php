<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientPortal\StoreCompanyRequest;
use App\Http\Requests\Admin\ClientPortal\UpdateCompanyRequest;
use App\Http\Resources\Admin\ClientPortal\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sort = in_array($request->string('sort')->toString(), ['name', 'registration_number', 'status', 'updated_at'], true)
            ? $request->string('sort')->toString() : 'updated_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $search = trim($request->string('search')->toString());

        $companies = Company::query()
            ->withCount(['contacts', 'projects'])
            ->withCount(['contacts as portal_contacts_count' => fn ($query) => $query->where('active', true)->where('can_access_portal', true)])
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%")
                    ->orWhere('vat_number', 'like', "%{$search}%")
                    ->orWhereHas('contacts', fn ($contacts) => $contacts->where('email', 'like', "%{$search}%"));
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy($sort, $direction)
            ->paginate(min(max($request->integer('per_page', 25), 10), 100));

        return CompanyResource::collection($companies);
    }

    public function store(StoreCompanyRequest $request): CompanyResource
    {
        return new CompanyResource(Company::query()->create($request->validated())->loadCount(['contacts', 'projects']));
    }

    public function show(Company $company): CompanyResource
    {
        return new CompanyResource($company->load(['contacts' => fn ($query) => $query->orderBy('last_name'), 'projects.serviceProduct'])->loadCount(['contacts', 'projects']));
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        $company->update($request->validated());
        if ($company->status !== 'archived') {
            $company->update(['archived_at' => null]);
        }

        return new CompanyResource($company->fresh()->loadCount(['contacts', 'projects']));
    }

    public function archive(Company $company): Response
    {
        $company->update(['status' => 'archived', 'archived_at' => now()]);

        return response()->noContent();
    }
}