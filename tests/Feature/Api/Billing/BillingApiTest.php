<?php

namespace Tests\Feature\Api\Billing;

use App\Models\Company;
use App\Models\Project;
use App\Models\SaasCustomerApiCredential;
use App\Models\SaasPlan;
use App\Models\SaasPlanPrice;
use App\Models\SaasProjectApiCredential;
use App\Models\SaasSubscription;
use App\Models\SaasBillingCustomer;
use App\Models\SaasFeature;
use App\Models\SaasPlanFeature;
use App\Models\CompanyTrial;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
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
            ->assertJsonPath('id', 'cs_generic')
            ->assertJsonPath('url', 'https://checkout.stripe.com/cs_generic');
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

    public function test_upgrade_changes_subscription_price_immediately(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('Upgrade Product');
        $higherPrice = SaasPlanPrice::query()->create([
            'saas_plan_id' => $plan->id,
            'amount' => 3900,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'active' => true,
            'stripe_price_id' => 'price_'.uniqid(),
        ]);

        $subscription = SaasSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'stripe_customer_id' => 'cus_upgrade',
            'stripe_subscription_id' => 'sub_upgrade',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($higherPrice): void {
            $mock->shouldReceive('changeSubscriptionPriceImmediately')
                ->once()
                ->withArgs(fn (SaasSubscription $s, SaasPlanPrice $p) => $p->is($higherPrice))
                ->andReturn(StripeObject::constructFrom(['id' => 'sub_upgrade']));
        });

        $response = $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->postJson('/api/v1/billing/customer/subscription/change', ['plan_price_id' => $higherPrice->id]);

        $response->assertOk();
        $response->assertJsonPath('data.price.id', $higherPrice->id);
        $response->assertJsonPath('data.scheduled_change', null);

        $subscription->refresh();
        $this->assertEquals($higherPrice->id, $subscription->saas_plan_price_id);
        $this->assertNull($subscription->scheduled_saas_plan_price_id);
    }

    public function test_downgrade_schedules_subscription_change_for_period_end(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('Downgrade Product');
        $lowerPrice = SaasPlanPrice::query()->create([
            'saas_plan_id' => $plan->id,
            'amount' => 900,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'active' => true,
            'stripe_price_id' => 'price_'.uniqid(),
        ]);

        $subscription = SaasSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'current_period_end' => now()->addDays(20),
            'stripe_customer_id' => 'cus_downgrade',
            'stripe_subscription_id' => 'sub_downgrade',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($lowerPrice): void {
            $mock->shouldReceive('scheduleSubscriptionPriceChange')
                ->once()
                ->withArgs(fn (SaasSubscription $s, SaasPlanPrice $p) => $p->is($lowerPrice))
                ->andReturn(StripeObject::constructFrom(['id' => 'sub_sched_123']));
        });

        $response = $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->postJson('/api/v1/billing/customer/subscription/change', ['plan_price_id' => $lowerPrice->id]);

        $response->assertOk();
        // The customer stays on the CURRENT price - it only becomes effective at period end.
        $response->assertJsonPath('data.price.id', $price->id);
        $response->assertJsonPath('data.scheduled_change.price.id', $lowerPrice->id);

        $subscription->refresh();
        $this->assertEquals($price->id, $subscription->saas_plan_price_id);
        $this->assertEquals($lowerPrice->id, $subscription->scheduled_saas_plan_price_id);
        $this->assertEquals('sub_sched_123', $subscription->stripe_schedule_id);
    }

    public function test_cancel_schedules_cancellation_at_period_end_without_ending_access(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('Cancel Product');

        SaasSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'current_period_end' => now()->addDays(10),
            'stripe_customer_id' => 'cus_cancel',
            'stripe_subscription_id' => 'sub_cancel',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('setCancelAtPeriodEnd')
                ->once()
                ->withArgs(fn (SaasSubscription $s, bool $cancel) => $cancel === true)
                ->andReturn(StripeObject::constructFrom(['id' => 'sub_cancel']));
        });

        $response = $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->postJson('/api/v1/billing/customer/subscription/cancel');

        $response->assertOk();
        $response->assertJsonPath('data.status', SaasSubscription::STATUS_ACTIVE);
        $response->assertJsonPath('data.cancel_at_period_end', true);
    }

    public function test_resume_reverses_scheduled_cancellation(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('Resume Product');

        SaasSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'cancel_at_period_end' => true,
            'stripe_customer_id' => 'cus_resume',
            'stripe_subscription_id' => 'sub_resume',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('setCancelAtPeriodEnd')
                ->once()
                ->withArgs(fn (SaasSubscription $s, bool $cancel) => $cancel === false)
                ->andReturn(StripeObject::constructFrom(['id' => 'sub_resume']));
        });

        $response = $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->postJson('/api/v1/billing/customer/subscription/resume');

        $response->assertOk();
        $response->assertJsonPath('data.cancel_at_period_end', false);
    }

    public function test_resume_fails_when_no_cancellation_is_scheduled(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('No Resume Product');

        SaasSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'cancel_at_period_end' => false,
            'stripe_customer_id' => 'cus_no_resume',
            'stripe_subscription_id' => 'sub_no_resume',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('setCancelAtPeriodEnd');
        });

        $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->postJson('/api/v1/billing/customer/subscription/resume')
            ->assertStatus(422);
    }

    public function test_change_subscription_rejects_price_from_another_project(): void
    {
        [$projectA, $planA, $priceA, $companyA] = $this->fixture('Project A Change');
        [$projectB, $planB, $priceB] = $this->fixture('Project B Change');

        SaasSubscription::query()->create([
            'project_id' => $projectA->id,
            'company_id' => $companyA->id,
            'saas_plan_id' => $planA->id,
            'saas_plan_price_id' => $priceA->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
            'stripe_customer_id' => 'cus_cross',
            'stripe_subscription_id' => 'sub_cross',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('changeSubscriptionPriceImmediately');
            $mock->shouldNotReceive('scheduleSubscriptionPriceChange');
        });

        $this
            ->withToken($this->projectToken($projectA))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($projectA, $companyA))
            ->postJson('/api/v1/billing/customer/subscription/change', ['plan_price_id' => $priceB->id])
            ->assertStatus(422);
    }

    public function test_customer_can_retrieve_only_its_payment_and_invoice_history(): void
    {
        [$project, $plan, $price, $company] = $this->fixture('History Product');
        [$otherCompany] = $this->companyCredential($project, 'Other History Company');

        $invoice = SaasInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'stripe_invoice_id' => 'in_history_company',
            'invoice_number' => 'INV-45',
            'invoice_date' => now(),
            'amount_due' => 4500,
            'amount_paid' => 4500,
            'currency' => 'EUR',
            'status' => 'paid',
        ]);

        SaasPayment::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'saas_invoice_id' => $invoice->id,
            'amount' => 4500,
            'currency' => 'EUR',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        SaasInvoice::query()->create([
            'project_id' => $project->id,
            'company_id' => $otherCompany->id,
            'stripe_invoice_id' => 'in_history_other',
            'amount_due' => 9900,
            'amount_paid' => 9900,
            'currency' => 'EUR',
            'status' => 'paid',
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$this->projectToken($project),
            'X-Billing-Customer-Token' => $this->customerToken($project, $company),
        ];

        $this->getJson('/api/v1/billing/customer/invoices', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.number', 'INV-45')
            ->assertJsonMissing(['amount_paid' => 9900]);

        $this->getJson('/api/v1/billing/customer/payments', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.amount', 4500)
            ->assertJsonMissing(['amount' => 9900]);
    }

    public function test_self_service_provisioning_creates_customer_credential_and_company(): void
    {
        [$project] = $this->fixture('Self Service Product');
        $token = $this->projectToken($project);

        $response = $this
            ->withToken($token)
            ->postJson('/api/v1/billing/customer-credentials', [
                'external_reference' => 'adocare-company-42',
                'name' => 'ADOCare Company 42',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.already_provisioned', false);
        $this->assertNotEmpty($response->json('data.token'));

        $credential = SaasCustomerApiCredential::query()
            ->where('project_id', $project->id)
            ->where('external_reference', 'adocare-company-42')
            ->first();

        $this->assertNotNull($credential);
        $this->assertNotNull($credential->company_id);
        $this->assertEquals('ADOCare Company 42', $credential->company->name);
    }

    public function test_self_service_provisioning_is_idempotent(): void
    {
        [$project] = $this->fixture('Idempotent Product');
        $token = $this->projectToken($project);

        $first = $this
            ->withToken($token)
            ->postJson('/api/v1/billing/customer-credentials', [
                'external_reference' => 'adocare-company-7',
            ]);
        $first->assertCreated();

        $second = $this
            ->withToken($token)
            ->postJson('/api/v1/billing/customer-credentials', [
                'external_reference' => 'adocare-company-7',
            ]);

        $second->assertOk();
        $second->assertJsonPath('data.already_provisioned', true);
        $second->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertNull($second->json('data.token'));

        $this->assertEquals(1, SaasCustomerApiCredential::query()
            ->where('project_id', $project->id)
            ->where('external_reference', 'adocare-company-7')
            ->count());
        $this->assertEquals(1, Company::query()->where('name', 'like', '%adocare-company-7%')->count());
    }

    public function test_self_service_provisioning_requires_project_token(): void
    {
        $this->postJson('/api/v1/billing/customer-credentials', [
            'external_reference' => 'adocare-company-1',
        ])->assertUnauthorized();
    }

    public function test_self_service_provisioning_isolates_by_project(): void
    {
        [$projectA] = $this->fixture('Project A');
        [$projectB] = $this->fixture('Project B');

        $this->withToken($this->projectToken($projectA))
            ->postJson('/api/v1/billing/customer-credentials', ['external_reference' => 'shared-ref'])
            ->assertCreated();

        // Same external_reference, different Project - must not collide/reuse the other Project's credential.
        $response = $this->withToken($this->projectToken($projectB))
            ->postJson('/api/v1/billing/customer-credentials', ['external_reference' => 'shared-ref']);

        $response->assertCreated();
        $response->assertJsonPath('data.already_provisioned', false);
    }

    public function test_self_service_provisioning_uses_billing_profile_not_credential_name(): void
    {
        [$project] = $this->fixture('Billing Profile Product');

        $response = $this
            ->withToken($this->projectToken($project))
            ->postJson('/api/v1/billing/customer-credentials', [
                'external_reference' => 'adocare-company-29',
                'name' => 'Company 29 billing session',
                'billing_profile' => [
                    'name' => 'ADOCare s.r.o.',
                    'email' => 'billing@adocare.test',
                    'phone' => '+421900000000',
                    'address' => [
                        'line1' => 'Hlavná 1',
                        'city' => 'Bratislava',
                        'postal_code' => '81101',
                        'country' => 'SK',
                    ],
                    'ico' => '12345678',
                    'dic' => '2023456789',
                    'ic_dph' => 'SK2023456789',
                ],
            ]);

        $response->assertCreated();

        $credential = SaasCustomerApiCredential::query()
            ->where('project_id', $project->id)
            ->where('external_reference', 'adocare-company-29')
            ->first();

        // The technical credential label is preserved on the credential itself...
        $this->assertEquals('Company 29 billing session', $credential->name);

        // ...but the Company's legal identity comes from the billing profile, never that label.
        $company = $credential->company;
        $this->assertEquals('ADOCare s.r.o.', $company->name);
        $this->assertEquals('billing@adocare.test', $company->billing_email);
        $this->assertEquals('+421900000000', $company->billing_phone);
        $this->assertEquals('Hlavná 1', $company->billing_address_line1);
        $this->assertEquals('Bratislava', $company->billing_address_city);
        $this->assertEquals('81101', $company->billing_address_postal_code);
        $this->assertEquals('SK', $company->billing_address_country);
        $this->assertEquals('12345678', $company->registration_number);
        $this->assertEquals('2023456789', $company->tax_number);
        $this->assertEquals('SK2023456789', $company->vat_number);
    }

    public function test_self_service_provisioning_repeat_call_repairs_existing_company(): void
    {
        [$project] = $this->fixture('Repair Product');
        $token = $this->projectToken($project);

        $this->withToken($token)
            ->postJson('/api/v1/billing/customer-credentials', [
                'external_reference' => 'adocare-company-repair',
                'name' => 'Company 99 billing session',
            ])
            ->assertCreated();

        // A Company was created before any real billing profile existed - it still has the
        // technical label as its name, exactly like the real "Company 29 billing session" bug.
        $credential = SaasCustomerApiCredential::query()
            ->where('external_reference', 'adocare-company-repair')
            ->first();
        $this->assertEquals('Company 99 billing session', $credential->company->name);

        // Re-provisioning (idempotent) with a billing_profile now repairs the existing Company -
        // no second credential/Company is created.
        $repair = $this
            ->withToken($token)
            ->postJson('/api/v1/billing/customer-credentials', [
                'external_reference' => 'adocare-company-repair',
                'billing_profile' => ['name' => 'Real Legal Name s.r.o.', 'email' => 'billing@real.test'],
            ]);

        $repair->assertOk();
        $repair->assertJsonPath('data.already_provisioned', true);

        $this->assertEquals(1, SaasCustomerApiCredential::query()
            ->where('external_reference', 'adocare-company-repair')
            ->count());

        $company = $credential->company->fresh();
        $this->assertEquals('Real Legal Name s.r.o.', $company->name);
        $this->assertEquals('billing@real.test', $company->billing_email);
    }

    public function test_update_profile_repairs_existing_stripe_customer_without_duplicating(): void
    {
        [$project, , , $company] = $this->fixture('Repair Existing Customer Product');
        $billingCustomer = \App\Models\SaasBillingCustomer::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'stripe_customer_id' => 'cus_existing_123',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock) use ($company): void {
            $mock->shouldReceive('createOrUpdateCustomer')
                ->once()
                ->withArgs(fn (Company $c, $email, $projectId) => $c->is($company) && $email === null)
                ->andReturn(StripeObject::constructFrom(['id' => 'cus_existing_123']));
        });

        $response = $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->patchJson('/api/v1/billing/customer/profile', [
                'name' => 'Repaired Legal Name s.r.o.',
                'email' => 'billing@repaired.test',
                'ico' => '87654321',
            ]);

        $response->assertOk();

        $company->refresh();
        $this->assertEquals('Repaired Legal Name s.r.o.', $company->name);
        $this->assertEquals('billing@repaired.test', $company->billing_email);
        $this->assertEquals('87654321', $company->registration_number);

        // Still exactly one SaasBillingCustomer row - no duplicate Stripe Customer created.
        $this->assertEquals(1, \App\Models\SaasBillingCustomer::query()
            ->where('project_id', $project->id)
            ->where('company_id', $company->id)
            ->count());
    }

    public function test_update_profile_does_not_call_stripe_when_no_customer_exists_yet(): void
    {
        [$project, , , $company] = $this->fixture('No Stripe Customer Yet Product');

        $this->mock(StripeBillingService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createOrUpdateCustomer');
        });

        $response = $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->patchJson('/api/v1/billing/customer/profile', [
                'name' => 'Some Company s.r.o.',
            ]);

        $response->assertOk();
        $this->assertEquals('Some Company s.r.o.', $company->fresh()->name);
    }

    public function test_update_profile_requires_customer_token(): void
    {
        [$project] = $this->fixture('No Token Product');

        // A missing/invalid Customer Credential (project token alone is not enough) is 403,
        // matching AuthenticateBillingApi's existing behavior for every other customer-scoped route.
        $this->withToken($this->projectToken($project))
            ->patchJson('/api/v1/billing/customer/profile', ['name' => 'X'])
            ->assertForbidden();
    }

    public function test_past_due_subscription_exposes_server_calculated_grace_period_and_current_plan_entitlements(): void
    {
        Carbon::setTestNow('2026-09-10 14:00:00');

        [$project, $plan, $price, $company] = $this->fixture('Grace Period Product');
        $this->assertEquals(7, $project->payment_failure_grace_period_days);
        $project->update(['payment_failure_grace_period_days' => 7]);
        $feature = SaasFeature::query()->create([
            'project_id' => $project->id,
            'key' => 'users',
            'name' => 'Users',
            'type' => SaasFeature::TYPE_LIMIT,
            'active' => true,
        ]);
        SaasPlanFeature::query()->create([
            'saas_plan_id' => $plan->id,
            'saas_feature_id' => $feature->id,
            'limit_value' => 10,
        ]);

        SaasSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_PAST_DUE,
            'payment_failed_at' => now(),
            'stripe_customer_id' => 'cus_grace',
            'stripe_subscription_id' => 'sub_grace',
        ]);

        $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->getJson('/api/v1/billing/customer/subscriptions')
            ->assertOk()
            ->assertJsonPath('subscriptions.0.status', SaasSubscription::STATUS_PAST_DUE)
            ->assertJsonPath('subscriptions.0.payment_status', 'failed')
            ->assertJsonPath('subscriptions.0.payment_failed_at', '2026-09-10T14:00:00+00:00')
            ->assertJsonPath('subscriptions.0.grace_period_ends_at', '2026-09-17T14:00:00+00:00')
            ->assertJsonPath('subscriptions.0.payment_action_required', true)
            ->assertJsonPath('subscriptions.0.entitlements.users.value', 10);

        Carbon::setTestNow();
    }

    public function test_payment_method_portal_uses_authenticated_company_billing_customer(): void
    {
        [$project, , , $company] = $this->fixture('Payment Portal Product');
        SaasBillingCustomer::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'stripe_customer_id' => 'cus_portal_company',
        ]);

        $this->mock(StripeBillingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createBillingPortalSession')
                ->once()
                ->with('cus_portal_company', 'https://adocare.test/settings/billing')
                ->andReturn(StripeObject::constructFrom([
                    'url' => 'https://billing.stripe.com/session/test',
                ]));
        });

        $this
            ->withToken($this->projectToken($project))
            ->withHeader('X-Billing-Customer-Token', $this->customerToken($project, $company))
            ->postJson('/api/v1/billing/customer/payment-method', [
                'return_url' => 'https://adocare.test/settings/billing',
                'stripe_customer_id' => 'cus_attacker',
            ])
            ->assertOk()
            ->assertJsonPath('url', 'https://billing.stripe.com/session/test');
    }

    public function test_payment_method_portal_requires_a_customer_credential(): void
    {
        [$project] = $this->fixture('Payment Portal Token Product');

        $this->mock(StripeBillingService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('createBillingPortalSession');
        });

        $this
            ->withToken($this->projectToken($project))
            ->postJson('/api/v1/billing/customer/payment-method', [
                'return_url' => 'https://adocare.test/settings/billing',
            ])
            ->assertForbidden();
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
