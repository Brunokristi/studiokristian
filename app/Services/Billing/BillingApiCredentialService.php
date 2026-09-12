<?php

namespace App\Services\Billing;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Project;
use App\Models\SaasCustomerApiCredential;
use App\Models\SaasProjectApiCredential;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

    /**
     * Self-service credential issuance for a SaaS product's own tenant, authenticated with
     * only the Project credential (no StudioKristian admin session required). Idempotent
     * per (project, external_reference) - a repeated call returns the existing credential's
     * metadata instead of minting a second one. The Customer Credential still requires a
     * StudioKristian Company row to scope subscriptions/trials to, so one is created on
     * first use and reused afterwards.
     *
     * `$billingProfile` is the actual Company's billing identity (legal name, billing
     * email/phone/address, ICO/DIC/IC DPH) - never the technical credential label. It is
     * applied to the StudioKristian Company on first creation, and also on a repeat call
     * (idempotent "already_provisioned" path) so an existing Company/Stripe Customer can be
     * repaired without minting a second credential.
     */
    public function provisionSelfServiceCustomerCredential(Project $project, string $externalReference, ?string $name = null, ?array $billingProfile = null): array
    {
        $existing = $project->billingCustomerCredentials()
            ->where('external_reference', $externalReference)
            ->whereNull('revoked_at')
            ->first();

        if ($existing) {
            if ($billingProfile) {
                $attributes = $this->companyAttributesFromProfile($billingProfile);
                if (!empty($billingProfile['name'])) {
                    $attributes['name'] = $billingProfile['name'];
                }
                $existing->company?->update($attributes);
                $this->syncBillingContactFromProfile($existing->company, $billingProfile);
            }

            return [
                'id' => $existing->id,
                'name' => $existing->name,
                'already_provisioned' => true,
            ];
        }

        try {
            return DB::transaction(function () use ($project, $externalReference, $name, $billingProfile) {
                $label = $name ?: "SaaS customer {$externalReference} ({$project->name})";

                $attributes = $this->companyAttributesFromProfile($billingProfile);
                $attributes['name'] = $billingProfile['name'] ?? $label;

                $company = Company::create($attributes);

                $this->syncBillingContactFromProfile($company, $billingProfile);

                $token = Str::random(64);

                $credential = $project->billingCustomerCredentials()->create([
                    'company_id' => $company->id,
                    'external_reference' => $externalReference,
                    'name' => $label,
                    'token_hash' => hash('sha256', $token),
                ]);

                return [
                    ...$this->response($credential, $token),
                    'already_provisioned' => false,
                ];
            });
        } catch (QueryException $e) {
            // Unique (project_id, external_reference) violation - a concurrent request won the race.
            $existing = $project->billingCustomerCredentials()
                ->where('external_reference', $externalReference)
                ->whereNull('revoked_at')
                ->first();

            if ($existing) {
                return [
                    'id' => $existing->id,
                    'name' => $existing->name,
                    'already_provisioned' => true,
                ];
            }

            throw $e;
        }
    }

    /**
     * Maps an ADOCare-style billing profile payload onto the Company's canonical
     * fields. Email/phone belong to the billing contact, not the Company.
     */
    public function companyAttributesFromProfile(?array $profile): array
    {
        if (!$profile) {
            return [];
        }

        return array_filter([
            'address' => $this->composeAddress($profile['address'] ?? []),
            'registration_number' => $profile['ico'] ?? null,
            'tax_number' => $profile['dic'] ?? null,
            'vat_number' => $profile['ic_dph'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * The API only supplies an email/phone, so it is materialised as the Company's
     * billing ClientContact - the single canonical billing identity.
     */
    public function syncBillingContactFromProfile(?Company $company, ?array $profile): void
    {
        $email = trim((string) ($profile['email'] ?? ''));

        if (!$company || $email === '') {
            return;
        }

        $contact = ClientContact::query()
            ->where('company_id', $company->id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower($email)])
            ->first();

        $attributes = array_filter([
            'phone' => $profile['phone'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($contact) {
            if ($attributes) {
                $contact->update($attributes);
            }
        } else {
            $contact = $company->contacts()->create([
                ...$attributes,
                'first_name' => $profile['name'] ?? $company->name,
                'last_name' => '',
                'email' => $email,
                'active' => true,
            ]);
        }

        $company->update([
            'billing_contact_id' => $contact->id,
        ]);
    }

    private function composeAddress(array $address): ?string
    {
        $lines = collect([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            trim(($address['postal_code'] ?? '').' '.($address['city'] ?? '')),
            $address['country'] ?? null,
        ])->filter(fn ($line) => trim((string) $line) !== '');

        return $lines->isEmpty() ? null : $lines->implode("\n");
    }

    public function revokeProjectCredential(SaasProjectApiCredential $credential): void
    {
        $credential->update([
            'revoked_at' => now(),
        ]);
    }

    private function response(SaasProjectApiCredential|SaasCustomerApiCredential $credential, string $token): array
    {
        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'token' => $token,
            'created_at' => $credential->created_at?->toIso8601String(),
        ];
    }
}

