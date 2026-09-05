<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SaasProjectApiCredentialResource;
use App\Models\Company;
use App\Models\Project;
use App\Models\SaasProjectApiCredential;
use App\Services\Billing\BillingApiCredentialService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class BillingApiCredentialController extends Controller
{
    public function index(Project $project)
    {
        abort_unless($project->is_saas, 404);

        return SaasProjectApiCredentialResource::collection(
            $project
                ->billingApiCredentials()
                ->whereNull('revoked_at')
                ->latest()
                ->get()
        );
    }

    public function project(Request $request, Project $project, BillingApiCredentialService $service, AuditLogger $auditLogger)
    {
        abort_unless($project->is_saas, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $credential = $service->issueProjectCredential(
            $project,
            $data['name']
        );

        $auditLogger->record(
            'saas_project_credential_created',
            $request->user(),
            $project,
            $project->company_id,
            $project->id,
            [
                'credential_id' => $credential['id'],
                'credential_name' => $credential['name'],
            ],
            $request
        );

        return response()->json([
            'data' => [
                ...$credential,
                'token_visible_once' => true,
            ],
        ], 201);
    }

    public function customer(Request $request, Project $project, BillingApiCredentialService $service): array
    {
        abort_unless($project->is_saas, 404);

        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return $service->issueCustomerCredential(
            $project,
            Company::query()->findOrFail($data['company_id']),
            $data['name']
        );
    }

    public function revoke(Request $request, Project $project, SaasProjectApiCredential $credential, BillingApiCredentialService $service, AuditLogger $auditLogger)
    {
        abort_unless($project->is_saas, 404);
        abort_unless($credential->project_id === $project->id, 404);

        $service->revokeProjectCredential($credential);

        $auditLogger->record(
            'saas_project_credential_revoked',
            $request->user(),
            $credential,
            $project->company_id,
            $project->id,
            [
                'credential_id' => $credential->id,
                'credential_name' => $credential->name,
            ],
            $request
        );

        return response()->noContent();
    }
}
