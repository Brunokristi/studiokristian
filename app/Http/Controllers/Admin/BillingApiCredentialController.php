<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Project;
use App\Services\Billing\BillingApiCredentialService;
use Illuminate\Http\Request;

class BillingApiCredentialController extends Controller
{
    public function project(Request $request, Project $project, BillingApiCredentialService $service): array
    {
        abort_unless($project->is_saas, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return $service->issueProjectCredential($project, $data['name']);
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
}
