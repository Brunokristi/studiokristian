<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SaasPlanPrice;
use App\Services\Billing\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CheckoutSessionController extends Controller
{
    public function __invoke(Request $request, StripeBillingService $stripe): JsonResponse
    {
        $this->authorizeBillingApi($request);

        return response()->json([
            'message' => 'This checkout endpoint has moved to /api/v1/billing/checkout.',
        ], 410);

        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'saas_plan_price_id' => ['required', 'integer', 'exists:saas_plan_prices,id'],
            'success_url' => ['required', 'url', 'max:2000'],
            'cancel_url' => ['required', 'url', 'max:2000'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);

        /** @var SaasPlanPrice $price */
        $price = SaasPlanPrice::query()
            ->with('plan.project')
            ->whereKey($data['saas_plan_price_id'])
            ->where('active', true)
            ->whereHas('plan', fn ($query) => $query->where('active', true))
            ->firstOrFail();

        try {
            $session = $stripe->createCheckoutSession(
                $company,
                $price,
                $data['success_url'],
                $data['cancel_url'],
                $data['customer_email'] ?? null
            );
        } catch (Throwable $exception) {
            Log::error('Stripe checkout session creation failed.', [
                'company_id' => $company->id,
                'saas_plan_price_id' => $price->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to create Stripe Checkout Session.',
            ], 422);
        }

        return response()->json([
            'id' => $session->id,
            'url' => $session->url,
        ], 201);
    }

    private function authorizeBillingApi(Request $request): void
    {
        $token = config('services.studiokristian.billing_api_token');

        abort_unless(is_string($token) && $token !== '', 503);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 403);
    }
}