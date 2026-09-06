<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SaasPlanResource;
use App\Models\Project;
use App\Models\SaasFeature;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Services\Billing\SaasPlanStripeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class SaasPlanController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorizeSaasProject($project);

        return SaasPlanResource::collection(
            $project
                ->saasPlans()
                ->with(['prices', 'planFeatures.feature'])
                ->withCount('subscriptions')
                ->paginate(100)
        );
    }

    public function store(
        Request $request,
        Project $project,
        SaasPlanStripeSyncService $sync
    ): JsonResponse
    {
        $this->authorizeSaasProject($project);

        $data = $this->validated($request, $project);

        try {
            $plan = $sync->createPlan($project, $data);
        } catch (Throwable $exception) {
            return $this->stripeFailure($exception);
        }

        return (new SaasPlanResource($plan))->response()->setStatusCode(201);
    }

    public function update(
        Request $request,
        SaasPlan $plan,
        SaasPlanStripeSyncService $sync
    ): SaasPlanResource|JsonResponse
    {
        $this->authorizeSaasProject($plan->project);

        $data = $this->validated($request, $plan->project, $plan);

        try {
            $plan = $sync->updatePlan($plan, $data);
        } catch (Throwable $exception) {
            return $this->stripeFailure($exception);
        }

        return new SaasPlanResource($plan);
    }

    public function destroy(
        SaasPlan $plan,
        SaasPlanStripeSyncService $sync
    ): Response|JsonResponse
    {
        $this->authorizeSaasProject($plan->project);

        try {
            $sync->archivePlan($plan);
        } catch (Throwable $exception) {
            return $this->stripeFailure($exception);
        }

        return response()->noContent();
    }

    private function validated(Request $request, Project $project, ?SaasPlan $plan = null): array
    {
        if (! $request->filled('slug') && $request->filled('name')) {
            $request->merge([
                'slug' => Str::slug($request->string('name')->toString()),
            ]);
        }

        if ($request->has('features')) {
            $request->merge([
                'features' => collect($request->input('features', []))
                    ->map(fn ($feature) => trim((string) $feature))
                    ->filter()
                    ->values()
                    ->all(),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:255', Rule::unique('saas_plans', 'slug')->where('project_id', $project->id)->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'features' => ['array'],
            'features.*' => ['string', 'max:255'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'prices' => ['array', 'max:2'],
            'prices.*.id' => ['nullable', 'integer', 'exists:saas_plan_prices,id'],
            'prices.*.amount' => ['required', 'integer', 'min:0'],
            'prices.*.currency' => ['required', 'string', 'size:3'],
            'prices.*.interval' => [
                'required',
                Rule::in(SaasPlanPrice::INTERVALS),
                'distinct',
            ],
            'prices.*.active' => ['required', 'boolean'],
            'entitlements' => ['array'],
            'entitlements.*.feature_id' => [
                'required',
                'integer',
                Rule::exists('saas_features', 'id')->where('project_id', $project->id),
            ],
            'entitlements.*.boolean_value' => ['nullable', 'boolean'],
            'entitlements.*.limit_value' => ['nullable', 'integer', 'min:0'],
            'entitlements.*.is_unlimited' => ['nullable', 'boolean'],
            'entitlements.*.is_custom' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request, $project) {
            $entitlements = $request->input('entitlements', []);

            if (! is_array($entitlements)) {
                return;
            }

            $featureIds = collect($entitlements)->pluck('feature_id')->filter()->unique()->all();

            $features = SaasFeature::query()
                ->where('project_id', $project->id)
                ->whereIn('id', $featureIds)
                ->get()
                ->keyBy('id');

            foreach ($entitlements as $index => $row) {
                $feature = $features->get($row['feature_id'] ?? null);

                if (! $feature) {
                    continue;
                }

                if ($feature->type === SaasFeature::TYPE_BOOLEAN) {
                    if (! is_bool($row['boolean_value'] ?? null)) {
                        $validator->errors()->add(
                            "entitlements.{$index}.boolean_value",
                            'This entitlement requires a boolean value.'
                        );
                    }

                    continue;
                }

                $isUnlimited = (bool) ($row['is_unlimited'] ?? false);
                $isCustom = (bool) ($row['is_custom'] ?? false);

                if (! $isUnlimited && ! $isCustom && ! isset($row['limit_value'])) {
                    $validator->errors()->add(
                        "entitlements.{$index}.limit_value",
                        'This entitlement requires a limit value, or must be marked unlimited or custom.'
                    );
                }
            }
        });

        return $validator->validate();
    }

    private function stripeFailure(Throwable $exception): JsonResponse
    {
        Log::error('Stripe SaaS plan synchronization failed.', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return response()->json([
            'message' => 'Stripe synchronization failed. Please check the plan values and Stripe configuration.',
        ], 422);
    }

    private function authorizeSaasProject(Project $project): void
    {
        abort_unless(request()->user()?->is_admin, 403);
        abort_unless($project->is_saas, 404);
    }
}