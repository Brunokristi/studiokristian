<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SaasSubscriptionResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaasCustomerController extends Controller
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorizeSaasProject($project);

        $search = trim($request->string('search')->toString());

        $subscriptions = $project
            ->saasSubscriptions()
            ->with(['company', 'plan', 'price'])
            ->when(
                $search !== '',
                fn ($query) => $query->whereHas(
                    'company',
                    fn ($company) => $company->where('name', 'like', "%{$search}%")
                )
            )
            ->orderByDesc('updated_at')
            ->paginate(min(max($request->integer('per_page', 25), 10), 100));

        return SaasSubscriptionResource::collection($subscriptions);
    }

    private function authorizeSaasProject(Project $project): void
    {
        abort_unless(request()->user()?->is_admin, 403);
        abort_unless($project->is_saas, 404);
    }
}