<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SaasFeatureResource;
use App\Models\Project;
use App\Models\SaasFeature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaasFeatureController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorizeSaasProject($project);

        return SaasFeatureResource::collection(
            $project->saasFeatures()->get()
        );
    }

    public function store(Request $request, Project $project): SaasFeatureResource
    {
        $this->authorizeSaasProject($project);

        $data = $this->validated($request, $project);

        $feature = $project->saasFeatures()->create($data);

        return new SaasFeatureResource($feature);
    }

    public function update(Request $request, SaasFeature $feature): SaasFeatureResource
    {
        $this->authorizeSaasProject($feature->project);

        $data = $this->validated($request, $feature->project, $feature);

        $feature->update($data);

        return new SaasFeatureResource($feature->fresh());
    }

    public function destroy(SaasFeature $feature): Response
    {
        $this->authorizeSaasProject($feature->project);

        $feature->delete();

        return response()->noContent();
    }

    private function validated(Request $request, Project $project, ?SaasFeature $feature = null): array
    {
        $validator = Validator::make($request->all(), [
            'key' => [
                'required',
                'alpha_dash:ascii',
                'max:100',
                Rule::unique('saas_features', 'key')->where('project_id', $project->id)->ignore($feature?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(SaasFeature::TYPES)],
            'unit' => ['nullable', 'string', 'max:50'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return $validator->validate();
    }

    private function authorizeSaasProject(Project $project): void
    {
        abort_unless(request()->user()?->is_admin, 403);
        abort_unless($project->is_saas, 404);
    }
}
