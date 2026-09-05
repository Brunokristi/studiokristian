<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SaasSubscriptionResource;
use App\Models\Project;
use App\Models\SaasSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaasSubscriptionController extends Controller
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $this->authorizeSaasProject($project);

        $subscriptions = $project
            ->saasSubscriptions()
            ->with(['company', 'plan', 'price'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status'))
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