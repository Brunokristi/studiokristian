<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BillingPlanResource;
use App\Http\Resources\Api\BillingSubscriptionResource;
use App\Http\Resources\Api\BillingTrialResource;
use App\Http\Resources\Api\BillingInvoiceResource;
use App\Http\Resources\Api\BillingPaymentResource;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Models\SaasSubscription;
use App\Models\SaasBillingCustomer;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Services\Billing\BillingApiCredentialService;
use App\Services\Billing\StripeBillingService;
use App\Services\Billing\ApplicationTrialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BillingController extends Controller
{
    /**
     * Self-service Customer Credential provisioning for the authenticated SaaS Project's
     * own tenants - no StudioKristian admin session required. Idempotent per
     * (project, external_reference): repeated calls never mint a second credential.
     */
    public function provisionCustomerCredential(Request $request, BillingApiCredentialService $credentials): JsonResponse
    {
        $project = $request->attributes->get('billing_project');

        $data = $request->validate([
            'external_reference' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'billing_profile' => ['nullable', 'array'],
            'billing_profile.name' => ['nullable', 'string', 'max:255'],
            'billing_profile.email' => ['nullable', 'email', 'max:255'],
            'billing_profile.phone' => ['nullable', 'string', 'max:64'],
            'billing_profile.address' => ['nullable', 'array'],
            'billing_profile.address.line1' => ['nullable', 'string', 'max:255'],
            'billing_profile.address.line2' => ['nullable', 'string', 'max:255'],
            'billing_profile.address.city' => ['nullable', 'string', 'max:255'],
            'billing_profile.address.postal_code' => ['nullable', 'string', 'max:32'],
            'billing_profile.address.country' => ['nullable', 'string', 'size:2'],
            'billing_profile.ico' => ['nullable', 'string', 'max:32'],
            'billing_profile.dic' => ['nullable', 'string', 'max:32'],
            'billing_profile.ic_dph' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $credentials->provisionSelfServiceCustomerCredential(
            $project,
            $data['external_reference'],
            $data['name'] ?? null,
            $data['billing_profile'] ?? null
        );

        return response()->json(['data' => $result], $result['already_provisioned'] ? 200 : 201);
    }

    /**
     * Synchronize the Company's billing profile (legal name, billing email/phone/address,
     * ICO/DIC/IC DPH) from the SaaS product's own records. Never uses the technical
     * Customer Credential name as the Company's legal identity. If a Stripe Customer
     * already exists for this (project, company), it is updated in place - never duplicated.
     */
    public function updateProfile(Request $request, BillingApiCredentialService $credentials, StripeBillingService $stripe): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:32'],
            'address.country' => ['nullable', 'string', 'size:2'],
            'ico' => ['nullable', 'string', 'max:32'],
            'dic' => ['nullable', 'string', 'max:32'],
            'ic_dph' => ['nullable', 'string', 'max:32'],
        ]);

        $project = $request->attributes->get('billing_project');
        $company = $request->attributes->get('billing_company');

        $attributes = $credentials->companyAttributesFromProfile($data);
        if (array_key_exists('name', $data) && $data['name']) {
            $attributes['name'] = $data['name'];
        }

        $company->update($attributes);
        $company = $company->fresh();

        $billingCustomer = SaasBillingCustomer::query()
            ->where('project_id', $project->id)
            ->where('company_id', $company->id)
            ->first();

        if ($billingCustomer) {
            $stripe->createOrUpdateCustomer($company, null, $project->id);
        }

        return response()->json(['data' => ['updated' => true]]);
    }

    public function plans(Request $request)
    {
        $project = $request->attributes->get('billing_project');

        $plans = SaasPlan::query()
            ->where('project_id', $project->id)
            ->where('active', true)
            ->with([
                'prices' => fn ($query) => $query->where('active', true)->orderBy('amount'),
                'planFeatures.feature',
            ])
            ->orderBy('sort_order')
            ->get();

        return BillingPlanResource::collection($plans);
    }

    public function customer(Request $request, ApplicationTrialService $trials)
    {
        $project = $request->attributes->get('billing_project');
        $company = $request->attributes->get('billing_company');

        $subscriptions = SaasSubscription::query()
            ->where('project_id', $project->id)
            ->where('company_id', $company->id)
            ->with(['plan.planFeatures.feature', 'price'])
            ->latest('updated_at')
            ->get();

        $trial = $trials->forCompany(
            $company,
            $project
        );

        return response()->json([
            'subscriptions' => BillingSubscriptionResource::collection($subscriptions),
            'trial' => $trial
                ? new BillingTrialResource($trial)
                : null,
            'payments' => BillingPaymentResource::collection(
                SaasPayment::query()
                    ->where('project_id', $project->id)
                    ->where('company_id', $company->id)
                    ->latest('paid_at')
                    ->get()
            ),
            'invoices' => BillingInvoiceResource::collection(
                SaasInvoice::query()
                    ->where('project_id', $project->id)
                    ->where('company_id', $company->id)
                    ->latest('invoice_date')
                    ->get()
            ),
        ]);
    }

    public function payments(Request $request)
    {
        $project = $request->attributes->get('billing_project');
        $company = $request->attributes->get('billing_company');

        return BillingPaymentResource::collection(
            SaasPayment::query()
                ->where('project_id', $project->id)
                ->where('company_id', $company->id)
                ->latest('paid_at')
                ->get()
        );
    }

    public function invoices(Request $request)
    {
        $project = $request->attributes->get('billing_project');
        $company = $request->attributes->get('billing_company');

        return BillingInvoiceResource::collection(
            SaasInvoice::query()
                ->where('project_id', $project->id)
                ->where('company_id', $company->id)
                ->latest('invoice_date')
                ->get()
        );
    }

    public function trial(Request $request, ApplicationTrialService $trials): JsonResponse
    {
        $project = $request->attributes->get('billing_project');
        $company = $request->attributes->get('billing_company');
        $trial = $trials->forCompany($company, $project);

        return response()->json([
            'data' => $trial
                ? new BillingTrialResource($trial)
                : null,
        ]);
    }

    public function startTrial(Request $request, ApplicationTrialService $trials): JsonResponse
    {
        $project = $request->attributes->get('billing_project');
        $company = $request->attributes->get('billing_company');

        if (! $project->trial_enabled) {
            return response()->json([
                'message' => 'Trials are not enabled for this SaaS Project.',
            ], 422);
        }

        $existing = $trials->forCompany($company, $project);

        $trial = $trials->startFor($company, $project);

        return response()->json([
            'data' => new BillingTrialResource($trial),
            'created' => $existing === null,
        ], $existing === null ? 201 : 200);
    }

    public function checkout(Request $request, StripeBillingService $stripe): JsonResponse
    {
        $data = $request->validate([
            'plan_price_id' => ['required', 'integer'],
            'success_url' => ['required', 'url', 'starts_with:https://', 'max:2000'],
            'cancel_url' => ['required', 'url', 'starts_with:https://', 'max:2000'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);

        $project = $request->attributes->get('billing_project');
        $company = $request->attributes->get('billing_company');

        $price = SaasPlanPrice::query()
            ->whereKey($data['plan_price_id'])
            ->where('active', true)
            ->whereHas('plan', fn ($query) => $query
                ->where('project_id', $project->id)
                ->where('active', true))
            ->with('plan')
            ->firstOrFail();

        if (! $price->stripe_price_id) {
            return response()->json(['message' => 'The selected price is not ready for checkout.'], 422);
        }

        try {
            $session = $stripe->createCheckoutSession(
                $company,
                $price,
                $data['success_url'],
                $data['cancel_url'],
                $data['customer_email'] ?? null,
                $request->header('Idempotency-Key')
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'Unable to create Checkout Session.'], 422);
        }

        return response()->json([
            'id' => $session->id,
            'url' => $session->url,
        ], 201);
    }
}
