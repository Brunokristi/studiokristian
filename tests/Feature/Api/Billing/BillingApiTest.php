<?php

namespace Tests\Feature\Api\Billing;

use App\Models\Company;
use App\Models\Project;
use App\Models\SaasCustomerApiCredential;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Models\SaasProjectApiCredential;
use App\Models\SaasSubscription;
use App\Models\CompanyTrial;
use App\Models\ServiceProduct;
use App\Services\Billing\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery\MockInterface;
use Stripe\StripeObject;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_credentials_isolate_plan_retrieval(): void
    {
        [$project, $plan] = $this->fixture('Product A');
        [$otherProject, $otherPlan] = $this->fixture('Product B');
        $token = $this->projectToken($project);

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/billing/plans');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $plan->id)
            ->assertJsonMissing(['id' => $otherPlan->id]);
    }

    public function test_customer_credentials_isolate_billing_state(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('Product A');
        [$otherCompany] = $this->companyCredential($project, 'Other Company');

        SaasSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
        ]);

        SaasSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $otherCompany->id,
            'saas_plan_id' => $plan->id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
        ]);

        $response = $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->getJson('/api/v1/billing/customer/subscriptions');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'subscriptions')
            ->assertJsonPath('subscriptions.0.plan.id', $plan->id);
    }

    public function test_checkout_uses_scoped_internal_price_and_not_client_stripe_id(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('Product A');
        $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($company, $price): void {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(fn (...$arguments) => $arguments[0]->is($company) && $arguments[1]->is($price))
                ->andReturn(StripeObject::constructFrom([
                    'id' => 'cs_generic',
                    'url' => 'https://checkout.stripe.com/cs_generic',
                ]));
        });

        $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->postJson('/api/v1/billing/checkout', [
                'plan_price_id' => $price->id,
                'stripe_price_id' => 'price_attacker',
                'success_url' => 'https://product.test/success',
                'cancel_url' => 'https://product.test/cancel',
            ])
            ->assertCreated()
            ->assertJsonPath('id', 'cs_generic');
    }

    public function test_customer_can_start_one_application_trial_from_project_configuration(): void
    {
        Carbon::setTestNow('2026-09-05 18:00:00');

        [$project, $plan, $price, $company] = $this->fixture('Trial Product');
        $project->update([
            'trial_enabled' => true,
            'trial_duration_days' => 30,
            'trial_credits' => 100,
        ]);

        $response = $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->postJson('/api/v1/billing/customer/trial', [
                'started_at' => '2000-01-01T00:00:00Z',
                'ends_at' => '2099-01-01T00:00:00Z',
                'credits' => 999999,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', CompanyTrial::STATUS_ACTIVE)
            ->assertJsonPath('data.started_at', '2026-09-05T18:00:00+00:00')
            ->assertJsonPath('data.ends_at', '2026-10-05T18:00:00+00:00')
            ->assertJsonPath('data.credit_allowance', 100)
            ->assertJsonPath('data.credits_remaining', 100);

        $this->assertDatabaseHas('company_trials', [
            'company_id' => $company->id,
            'project_id' => $project->id,
            'credits_allowance' => 100,
            'credits_used' => 0,
        ]);

        $this->assertDatabaseMissing('saas_billing_customers', [
            'company_id' => $company->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_starting_a_trial_twice_returns_the_original_trial(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('Idempotent Trial Product');
        $project->update([
            'trial_enabled' => true,
            'trial_duration_days' => 14,
            'trial_credits' => 500,
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$this->projectToken($project),
            'X-Billing-Customer-Token' => $this->customerToken($project, $company),
        ];

        $first = $this->postJson('/api/v1/billing/customer/trial', [], $headers);
        $second = $this->postJson('/api/v1/billing/customer/trial', [], $headers);

        $first->assertCreated();
        $second
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('data.started_at', $first->json('data.started_at'));

        $this->assertDatabaseCount('company_trials', 1);
    }

    public function test_trial_state_expires_from_server_time_without_a_scheduled_job(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('Expiration Product');
        $project->update([
            'trial_enabled' => true,
            'trial_duration_days' => 1,
            'trial_credits' => 10,
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$this->projectToken($project),
            'X-Billing-Customer-Token' => $this->customerToken($project, $company),
        ];

        Carbon::setTestNow('2026-09-05 18:00:00');
        $this->postJson('/api/v1/billing/customer/trial', [], $headers)
            ->assertCreated();

        Carbon::setTestNow('2026-09-06 18:00:00');
        $this->getJson('/api/v1/billing/customer/trial', $headers)
            ->assertOk()
            ->assertJsonPath('data.status', CompanyTrial::STATUS_EXPIRED);

        $this->assertDatabaseHas('company_trials', [
            'project_id' => $project->id,
            'company_id' => $company->id,
            'status' => CompanyTrial::STATUS_EXPIRED,
        ]);

        Carbon::setTestNow();
    }

    public function test_trial_cannot_start_when_project_trials_are_disabled(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('Disabled Trial Product');

        $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->postJson('/api/v1/billing/customer/trial')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Trials are not enabled for this SaaS Project.');

        $this->assertDatabaseCount('company_trials', 0);
    }

    private function fixture(string $name): array
    {
        $company = Company::query()->create(['name' => $name.' Company']);
        $serviceProduct = ServiceProduct::query()->create([
            'name' => $name,
            'slug' => uniqid('product-'),
            'active' => true,
        ]);
        $project = Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => $name,
            'url' => uniqid('product-'),
            'summary' => '',
            'portal_status' => 'draft',
            'is_published' => false,
            'is_saas' => true,
        ]);
        $plan = SaasPlan::query()->create([
            'project_id' => $project->id,
            'name' => 'Professional',
            'slug' => uniqid('plan-'),
            'active' => true,
            'stripe_product_id' => 'prod_'.uniqid(),
        ]);
        $price = SaasPlanPrice::query()->create([
            'saas_plan_id' => $plan->id,
            'amount' => 1900,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'active' => true,
            'stripe_price_id' => 'price_'.uniqid(),
        ]);

        $this->companyCredential($project, $company);

        return [$project, $plan, $price, $company];
    }

    private function projectToken(Project $project): string
    {
        $token = 'project-token-'.$project->id;
        SaasProjectApiCredential::query()->create([
            'project_id' => $project->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }

    private function companyCredential(Project $project, string|Company $company): array
    {
        if (is_string($company)) {
            $company = Company::query()->create(['name' => $company]);
        }

        $token = 'customer-token-'.$project->id.'-'.$company->id;
        SaasCustomerApiCredential::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        return [$company, $token];
    }

    private function customerToken(Project $project, Company $company): string
    {
        return 'customer-token-'.$project->id.'-'.$company->id;
    }
}
