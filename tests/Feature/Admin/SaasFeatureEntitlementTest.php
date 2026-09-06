<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\SaasCustomerApiCredential;
use App\Models\SaasFeature;
use App\Models\SaasPlan;
use App\Models\SaasProjectApiCredential;
use App\Models\SaasSubscription;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Services\Billing\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\StripeObject;
use Tests\TestCase;

class SaasFeatureEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_project_scoped_feature(): void
    {
        $admin = $this->admin();
        $project = $this->saasProject();

        $response = $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/features", [
                'key' => 'users',
                'name' => 'Users',
                'type' => 'limit',
                'unit' => 'users',
                'active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.key', 'users')
            ->assertJsonPath('data.type', 'limit');

        $this->assertDatabaseHas('saas_features', [
            'project_id' => $project->id,
            'key' => 'users',
        ]);
    }

    public function test_feature_key_must_be_unique_within_a_project(): void
    {
        $admin = $this->admin();
        $project = $this->saasProject();

        $this->createFeature($project, ['key' => 'users']);

        $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/features", [
                'key' => 'users',
                'name' => 'Users again',
                'type' => 'limit',
                'active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_the_same_feature_key_is_valid_across_different_projects(): void
    {
        $admin = $this->admin();
        $projectA = $this->saasProject();
        $projectB = $this->saasProject();

        $this->createFeature($projectA, ['key' => 'users']);

        $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$projectB->id}/features", [
                'key' => 'users',
                'name' => 'Users',
                'type' => 'limit',
                'active' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseCount('saas_features', 2);
    }

    public function test_a_feature_from_another_project_cannot_be_assigned_to_a_plan(): void
    {
        $this->mockStripe();
        $admin = $this->admin();
        $projectA = $this->saasProject();
        $projectB = $this->saasProject();

        $foreignFeature = $this->createFeature($projectB, ['key' => 'users']);

        $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$projectA->id}/plans", [
                'name' => 'Start',
                'slug' => 'start',
                'active' => true,
                'sort_order' => 0,
                'prices' => [],
                'entitlements' => [
                    ['feature_id' => $foreignFeature->id, 'limit_value' => 2],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entitlements.0.feature_id');

        $this->assertDatabaseCount('saas_plans', 0);
    }

    public function test_boolean_entitlement_is_synced_and_exposed_through_the_admin_api(): void
    {
        $this->mockStripe();
        $admin = $this->admin();
        $project = $this->saasProject();
        $feature = $this->createFeature($project, [
            'key' => 'priority_support',
            'type' => 'boolean',
        ]);

        $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/plans", [
                'name' => 'Pro',
                'slug' => 'pro',
                'active' => true,
                'sort_order' => 0,
                'prices' => [],
                'entitlements' => [
                    ['feature_id' => $feature->id, 'boolean_value' => true],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.entitlement_values.0.key', 'priority_support')
            ->assertJsonPath('data.entitlement_values.0.boolean_value', true);
    }

    public function test_numeric_limit_entitlement_is_synced(): void
    {
        $this->mockStripe();
        $admin = $this->admin();
        $project = $this->saasProject();
        $feature = $this->createFeature($project, [
            'key' => 'users',
            'type' => 'limit',
            'unit' => 'users',
        ]);

        $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/plans", [
                'name' => 'Start',
                'slug' => 'start',
                'active' => true,
                'sort_order' => 0,
                'prices' => [],
                'entitlements' => [
                    ['feature_id' => $feature->id, 'limit_value' => 2],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.entitlement_values.0.limit_value', 2)
            ->assertJsonPath('data.entitlement_values.0.is_unlimited', false);
    }

    public function test_unlimited_entitlement_does_not_use_a_magic_number(): void
    {
        $this->mockStripe();
        $admin = $this->admin();
        $project = $this->saasProject();
        $feature = $this->createFeature($project, [
            'key' => 'branches',
            'type' => 'limit',
        ]);

        $planResponse = $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/plans", [
                'name' => 'Pro',
                'slug' => 'pro',
                'active' => true,
                'sort_order' => 0,
                'prices' => [
                    ['amount' => 4500, 'currency' => 'EUR', 'interval' => 'monthly', 'active' => true],
                ],
                'entitlements' => [
                    ['feature_id' => $feature->id, 'is_unlimited' => true],
                ],
            ])
            ->assertCreated();

        $planId = $planResponse->json('data.id');

        $this->assertDatabaseHas('saas_plan_features', [
            'saas_plan_id' => $planId,
            'saas_feature_id' => $feature->id,
            'is_unlimited' => true,
            'limit_value' => null,
        ]);

        $token = $this->projectCredential($project);

        $this
            ->withToken($token)
            ->getJson('/api/v1/billing/plans')
            ->assertOk()
            ->assertJsonPath('data.0.entitlements.branches', ['type' => 'unlimited']);
    }

    public function test_feature_absent_from_a_plan_is_simply_omitted(): void
    {
        $this->mockStripe();
        $admin = $this->admin();
        $project = $this->saasProject();
        $this->createFeature($project, ['key' => 'custom_integrations', 'type' => 'boolean']);

        $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/plans", [
                'name' => 'Start',
                'slug' => 'start',
                'active' => true,
                'sort_order' => 0,
                'prices' => [],
                'entitlements' => [],
            ])
            ->assertCreated();

        $token = $this->projectCredential($project);

        $this
            ->withToken($token)
            ->getJson('/api/v1/billing/plans')
            ->assertOk()
            ->assertJsonPath('data.0.entitlements', []);
    }

    public function test_a_project_without_any_ai_related_feature_works_normally(): void
    {
        $this->mockStripe();
        $admin = $this->admin();
        $project = $this->saasProject();

        // AI credits are never defined for this project - they are optional, project-specific.
        $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/plans", [
                'name' => 'Start',
                'slug' => 'start',
                'active' => true,
                'sort_order' => 0,
                'prices' => [],
            ])
            ->assertCreated();

        $token = $this->projectCredential($project);

        $this
            ->withToken($token)
            ->getJson('/api/v1/billing/plans')
            ->assertOk()
            ->assertJsonPath('data.0.entitlements', []);
    }

    public function test_plans_api_returns_project_specific_entitlements_and_isolates_other_projects(): void
    {
        $this->mockStripe();
        $admin = $this->admin();
        $projectA = $this->saasProject();
        $projectB = $this->saasProject();

        $usersFeature = $this->createFeature($projectA, ['key' => 'users', 'type' => 'limit']);
        $this->createFeature($projectB, ['key' => 'tasks', 'type' => 'limit']);

        $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$projectA->id}/plans", [
                'name' => 'Start',
                'slug' => 'start',
                'active' => true,
                'sort_order' => 0,
                'prices' => [],
                'entitlements' => [
                    ['feature_id' => $usersFeature->id, 'limit_value' => 2],
                ],
            ])
            ->assertCreated();

        $tokenA = $this->projectCredential($projectA);

        $this
            ->withToken($tokenA)
            ->getJson('/api/v1/billing/plans')
            ->assertOk()
            ->assertJsonPath('data.0.entitlements.users.type', 'limit')
            ->assertJsonPath('data.0.entitlements.users.value', 2)
            ->assertJsonMissingPath('data.0.entitlements.tasks');
    }

    public function test_customer_subscription_api_exposes_current_plan_entitlements(): void
    {
        $this->mockStripe();
        $admin = $this->admin();
        $project = $this->saasProject();
        $usersFeature = $this->createFeature($project, ['key' => 'users', 'type' => 'limit']);
        $supportFeature = $this->createFeature($project, ['key' => 'priority_support', 'type' => 'boolean']);

        $planResponse = $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/plans", [
                'name' => 'Rast',
                'slug' => 'rast',
                'active' => true,
                'sort_order' => 0,
                'prices' => [
                    ['amount' => 4500, 'currency' => 'EUR', 'interval' => 'monthly', 'active' => true],
                ],
                'entitlements' => [
                    ['feature_id' => $usersFeature->id, 'limit_value' => 10],
                    ['feature_id' => $supportFeature->id, 'boolean_value' => true],
                ],
            ])
            ->assertCreated();

        $plan = SaasPlan::query()->findOrFail($planResponse->json('data.id'));
        $price = $plan->prices()->firstOrFail();

        $company = Company::query()->create(['name' => 'Entitlement Company']);

        SaasSubscription::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'saas_plan_id' => $plan->id,
            'saas_plan_price_id' => $price->id,
            'status' => SaasSubscription::STATUS_ACTIVE,
        ]);

        $projectToken = $this->projectCredential($project);
        $customerToken = $this->customerCredential($project, $company);

        $this
            ->withToken($projectToken)
            ->withHeader('X-Billing-Customer-Token', $customerToken)
            ->getJson('/api/v1/billing/customer/subscriptions')
            ->assertOk()
            ->assertJsonPath('subscriptions.0.entitlements.users.value', 10)
            ->assertJsonPath('subscriptions.0.entitlements.priority_support.value', true);
    }

    private function mockStripe(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createProduct')->andReturn($this->stripeObject('prod_'.uniqid()));
            $mock->shouldReceive('updateProduct')->andReturn($this->stripeObject('prod_'.uniqid()));
            $mock->shouldReceive('createPrice')->andReturnUsing(fn () => $this->stripeObject('price_'.uniqid()));
            $mock->shouldReceive('deactivateProduct');
            $mock->shouldReceive('deactivatePrice');
            $mock->shouldReceive('updatePriceActiveState');
        });
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function saasProject(): Project
    {
        $company = Company::query()->create(['name' => 'Entitlement Co']);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => 'Entitlement product',
            'slug' => uniqid('entitlement-product-'),
            'active' => true,
        ]);

        return Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => 'Entitlement SaaS',
            'url' => uniqid('entitlement-saas-'),
            'summary' => '',
            'portal_status' => 'draft',
            'is_published' => false,
            'is_saas' => true,
        ]);
    }

    private function createFeature(Project $project, array $attributes = []): SaasFeature
    {
        return SaasFeature::query()->create([
            'project_id' => $project->id,
            'key' => $attributes['key'] ?? uniqid('feature-'),
            'name' => $attributes['name'] ?? 'Feature',
            'type' => $attributes['type'] ?? SaasFeature::TYPE_LIMIT,
            'unit' => $attributes['unit'] ?? null,
            'active' => $attributes['active'] ?? true,
            'sort_order' => $attributes['sort_order'] ?? 0,
        ]);
    }

    private function projectCredential(Project $project): string
    {
        $token = 'project-token-'.$project->id.'-'.uniqid();

        SaasProjectApiCredential::query()->create([
            'project_id' => $project->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }

    private function customerCredential(Project $project, Company $company): string
    {
        $token = 'customer-token-'.$project->id.'-'.$company->id;

        SaasCustomerApiCredential::query()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }

    private function stripeObject(string $id): StripeObject
    {
        return StripeObject::constructFrom(['id' => $id]);
    }
}
