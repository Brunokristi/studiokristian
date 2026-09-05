<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProjectResource;
use App\Models\CompanyTrial;
use App\Models\Project;
use App\Models\SaasSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SaasProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim($request->string('search')->toString());

        $projects = Project::query()
            ->where('is_saas', true)
            ->with(['company', 'serviceProduct'])
            ->withCount(['saasPlans', 'saasSubscriptions'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 10), 100));

        return ProjectResource::collection($projects);
    }

    public function show(Project $project): array
    {
        $this->authorizeSaasProject($project);

        return [
            'project' => new ProjectResource($project->load(['company', 'serviceProduct'])),
            'metrics' => $this->metrics($project),
        ];
    }

    public function updateTrialSettings(Request $request, Project $project): array
    {
        $this->authorizeSaasProject($project);

        $project->update($request->validate([
            'trial_enabled' => ['required', 'boolean'],
            'trial_duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'trial_credits' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ]));

        return [
            'project' => new ProjectResource($project->fresh()->load(['company', 'serviceProduct'])),
        ];
    }

    public function revenue(Project $project): array
    {
        $this->authorizeSaasProject($project);

        return [
            'project' => new ProjectResource($project->load(['company', 'serviceProduct'])),
            'metrics' => [
                ...$this->metrics($project),
                'arr' => $this->metrics($project)['mrr'] * 12,
                'churn_rate' => 0,
            ],
        ];
    }

    public function destroy(Project $project): Response
    {
        $this->authorizeSaasProject($project);

        $project->update([
            'is_saas' => false,
        ]);

        return response()->noContent();
    }

    private function metrics(Project $project): array
    {
        $subscriptionQuery = SaasSubscription::query()
            ->where('project_id', $project->id);

        $activeSubscriptions = (clone $subscriptionQuery)
            ->where('status', SaasSubscription::STATUS_ACTIVE)
            ->count();

        $trialCompanies = CompanyTrial::query()
            ->where('project_id', $project->id)
            ->where('status', CompanyTrial::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->count();

        $activeCustomers = (clone $subscriptionQuery)
            ->whereIn('status', [
                SaasSubscription::STATUS_ACTIVE,
            ])
            ->distinct('company_id')
            ->count('company_id');

        $mrr = (int) (clone $subscriptionQuery)
            ->where('saas_subscriptions.status', SaasSubscription::STATUS_ACTIVE)
            ->join('saas_plan_prices', 'saas_plan_prices.id', '=', 'saas_subscriptions.saas_plan_price_id')
            ->where('saas_plan_prices.active', true)
            ->selectRaw("sum(case when saas_plan_prices.interval = 'monthly' then saas_plan_prices.amount when saas_plan_prices.interval = 'yearly' then saas_plan_prices.amount / 12 else 0 end) as mrr")
            ->value('mrr');

        return [
            'active_customers' => $activeCustomers,
            'active_subscriptions' => $activeSubscriptions,
            'trial_customers' => $trialCompanies,
            'mrr' => $mrr,
            'currency' => 'EUR',
        ];
    }

    private function authorizeSaasProject(Project $project): void
    {
        abort_unless(request()->user()?->is_admin, 403);
        abort_unless($project->is_saas, 404);
    }
}