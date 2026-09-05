<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Project;
use App\Models\SaasCustomerApiCredential;
use App\Models\SaasProjectApiCredential;
use Illuminate\Support\Str;

class BillingApiCredentialService
{
    public function issueProjectCredential(Project $project, string $name): array
    {
        $token = Str::random(64);

        $credential = $project->billingApiCredentials()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $token),
        ]);

        return $this->response($credential, $token);
    }

    public function issueCustomerCredential(Project $project, Company $company, string $name): array
    {
        $token = Str::random(64);

        $credential = $project->billingCustomerCredentials()->create([
            'company_id' => $company->id,
            'name' => $name,
            'token_hash' => hash('sha256', $token),
        ]);

        return $this->response($credential, $token);
    }

    private function response(SaasProjectApiCredential|SaasCustomerApiCredential $credential, string $token): array
    {
        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'token' => $token,
        ];
    }
}
