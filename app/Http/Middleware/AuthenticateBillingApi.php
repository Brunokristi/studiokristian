<?php

namespace App\Http\Middleware;

use App\Models\SaasCustomerApiCredential;
use App\Models\SaasProjectApiCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBillingApi
{
    public function handle(Request $request, Closure $next, string $customer = ''): Response
    {
        $token = $request->bearerToken();

        abort_unless(is_string($token) && $token !== '', 401);

        $credential = SaasProjectApiCredential::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->with('project')
            ->first();

        abort_unless($credential?->project?->is_saas, 403);

        $credential->update(['last_used_at' => now()]);
        $request->attributes->set('billing_project', $credential->project);

        if ($customer === 'required') {
            $customerToken = $request->header('X-Billing-Customer-Token');
            $customerCredential = SaasCustomerApiCredential::query()
                ->where('token_hash', hash('sha256', (string) $customerToken))
                ->where('project_id', $credential->project_id)
                ->whereNull('revoked_at')
                ->with('company');

            $customerCredential = $customerCredential->first();

            abort_unless($customerCredential?->company, 403);

            $customerCredential->update(['last_used_at' => now()]);
            $request->attributes->set('billing_company', $customerCredential->company);
        }

        return $next($request);
    }
}
