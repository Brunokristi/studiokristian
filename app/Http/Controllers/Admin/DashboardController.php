<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CompanyResource;
use App\Http\Resources\Admin\ProjectResource;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Project;
use App\Models\ServiceProduct;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $recentProjects = Project::query()
            ->whereNotNull('company_id')
            ->with(['company', 'serviceProduct'])
            ->withCount('contacts')
            ->latest('updated_at')
            ->limit(6)
            ->get();
        $recentCompanies = Company::query()
            ->withCount(['contacts', 'projects'])
            ->withCount(['contacts as portal_contacts_count' => fn ($query) => $query->where('active', true)->where('can_access_portal', true)])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        return response()->json([
            'counts' => [
                'active_clients' => Company::query()->where('status', 'active')->count(),
                'active_projects' => Project::query()->whereNotNull('company_id')->where('portal_status', 'active')->whereNull('archived_at')->count(),
                'active_service_products' => ServiceProduct::query()->where('active', true)->count(),
                'portal_contacts' => ClientContact::query()->where('active', true)->where('can_access_portal', true)->count(),
            ],
            'recent_projects' => ProjectResource::collection($recentProjects),
            'recent_clients' => CompanyResource::collection($recentCompanies),
        ]);
    }
}