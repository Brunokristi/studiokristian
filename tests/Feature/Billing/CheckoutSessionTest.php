<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\Project;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Models\ServiceProduct;
use App\Services\Billing\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\StripeObject;
use Tests\TestCase;

class CheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_session_requires_billing_api_token(): void
    {
        config([
            'services.studiokristian.billing_api_token' => 'test-token',
        ]);

        $price = $this->saasPriceFixture()[1];

        $this
            ->postJson('/api/billing/checkout-sessions', [
                'company_id' => $price->plan->project->company_id,
                'saas_plan_price_id' => $price->id,
                'success_url' => 'https://adocare.test/success',
                'cancel_url' => 'https://adocare.test/cancel',
            ])
            ->assertForbidden();
    }

    public function test_legacy_checkout_session_endpoint_is_deprecated(): void
    {
        config([
            'services.studiokristian.billing_api_token' => 'test-token',
        ]);

        [$company, $price] = $this->saasPriceFixture();

        $this
            ->withToken('test-token')
            ->postJson('/api/billing/checkout-sessions', [
                'company_id' => $company->id,
                'saas_plan_price_id' => $price->id,
                'success_url' => 'https://adocare.test/success',
                'cancel_url' => 'https://adocare.test/cancel',
                'customer_email' => 'billing@example.com',
                'stripe_price_id' => 'price_attacker_supplied',
            ])
            ->assertStatus(410)
            ->assertJsonPath('message', 'This checkout endpoint has moved to /api/v1/billing/checkout.');
    }

    private function saasPriceFixture(): array
    {
        $company = Company::query()->create([
            'name' => 'Acme',
        ]);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => 'Build',
            'slug' => uniqid('build-'),
            'active' => true,
        ]);

        $project = Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => 'ADOCare',
            'url' => uniqid('adocare-'),
            'summary' => '',
            'portal_status' => 'draft',
            'is_published' => false,
            'is_saas' => true,
        ]);

        $plan = SaasPlan::query()->create([
            'project_id' => $project->id,
            'name' => 'Professional',
            'slug' => 'professional',
            'active' => true,
            'stripe_product_id' => 'prod_test',
        ]);

        $price = SaasPlanPrice::query()->create([
            'saas_plan_id' => $plan->id,
            'amount' => 4900,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'active' => true,
            'stripe_price_id' => 'price_test',
        ]);

        return [$company, $price->load('plan.project')];
    }
}