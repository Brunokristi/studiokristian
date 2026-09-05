<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BillingPlanResource;
use App\Http\Resources\Api\BillingSubscriptionResource;
use App\Models\CompanyTrial;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Models\SaasSubscription;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BillingController extends Controller
{
    public function plans(Request $request)
    {
        $project = $request->attributes->get('billing_project');

        $plans = SaasPlan::query()
            ->where('project_id', $project->id)
            ->where('active', true)
            ->with(['prices' => fn ($query) => $query->where('active', true)->orderBy('amount')])
            ->orderBy('sort_order')
            ->get();

        return BillingPlanResource::collection($plans);
    }

    public function customer(Request $request)
    {
        $project = $request->attributes->get('billing_project');
        $company = $request->attributes->get('billing_company');

        $subscriptions = SaasSubscription::query()
            ->where('project_id', $project->id)
            ->where('company_id', $company->id)
            ->with(['plan', 'price'])
            ->latest('updated_at')
            ->get();

        $trial = CompanyTrial::query()
            ->where('project_id', $project->id)
            ->where('company_id', $company->id)
            ->latest('started_at')
            ->first();

        return response()->json([
            'subscriptions' => BillingSubscriptionResource::collection($subscriptions),
            'trial' => $trial ? [
                'status' => $trial->status,
                'started_at' => $trial->started_at?->toIso8601String(),
                'expires_at' => $trial->expires_at?->toIso8601String(),
                'credits_allowance' => $trial->credits_allowance,
                'credits_used' => $trial->credits_used,
                'credits_remaining' => $trial->creditsRemaining(),
            ] : null,
        ]);
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
