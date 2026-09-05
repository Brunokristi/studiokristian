<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\SaasPlan;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Services\Billing\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Stripe\StripeObject;
use Tests\TestCase;

class SaasManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_default_to_not_saas_when_created(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $company = Company::query()->create([
            'name' => 'Acme',
        ]);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => 'Build',
            'slug' => 'build',
            'active' => true,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson('/admin/client-portal/api/projects', [
                'company_id' => $company->id,
                'service_product_id' => $serviceProduct->id,
                'name' => 'Standard project',
                'portal_status' => 'draft',
                'contact_ids' => [],
                'coworker_ids' => [],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.is_saas', false);

        $this->assertDatabaseHas('projects', [
            'name' => 'Standard project',
            'is_saas' => false,
        ]);
    }

    public function test_saas_projects_are_listed_in_saas_management(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $saasProject = $this->project([
            'name' => 'ADOCare',
            'url' => 'adocare',
            'is_saas' => true,
        ]);

        $this->project([
            'name' => 'Regular work',
            'url' => 'regular-work',
            'is_saas' => false,
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson('/admin/client-portal/api/saas/projects');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $saasProject->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_manage_saas_plan_prices(): void
    {
        $this->mock(StripeBillingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createProduct')->once()->andReturn($this->stripeObject('prod_professional'));
            $mock->shouldReceive('createPrice')->twice()->andReturn(
                $this->stripeObject('price_monthly'),
                $this->stripeObject('price_yearly')
            );
            $mock->shouldReceive('updateProduct')->once()->andReturn($this->stripeObject('prod_professional'));
            $mock->shouldReceive('createPrice')->once()->andReturn($this->stripeObject('price_monthly_v2'));
            $mock->shouldReceive('deactivateProduct')->once();
            $mock->shouldReceive('deactivatePrice')->times(5);
        });

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $project = $this->project([
            'is_saas' => true,
        ]);

        $createResponse = $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/plans", [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For growing teams.',
                'features' => [
                    'Unlimited client records',
                    'AI summaries',
                ],
                'active' => true,
                'sort_order' => 1,
                'prices' => [
                    [
                        'amount' => 4900,
                        'currency' => 'EUR',
                        'interval' => 'monthly',
                        'active' => true,
                    ],
                    [
                        'amount' => 49000,
                        'currency' => 'EUR',
                        'interval' => 'yearly',
                        'active' => true,
                    ],
                ],
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Professional')
            ->assertJsonPath('data.stripe_product_id', 'prod_professional')
            ->assertJsonPath('data.features.0', 'Unlimited client records')
            ->assertJsonCount(2, 'data.prices');

        $planId = $createResponse->json('data.id');
        $priceId = $createResponse->json('data.prices.0.id');

        $this->assertDatabaseHas('saas_plan_prices', [
            'id' => $priceId,
            'stripe_price_id' => 'price_monthly',
            'active' => true,
        ]);

        $updateResponse = $this
            ->actingAs($admin)
            ->putJson("/admin/client-portal/api/saas/plans/{$planId}", [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For established teams.',
                'features' => [
                    'Unlimited client records',
                    'Advanced reporting',
                ],
                'active' => false,
                'sort_order' => 1,
                'prices' => [
                    [
                        'id' => $priceId,
                        'amount' => 5900,
                        'currency' => 'EUR',
                        'interval' => 'monthly',
                        'active' => true,
                    ],
                ],
            ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.features.1', 'Advanced reporting')
            ->assertJsonCount(1, 'data.prices');

        $this->assertDatabaseHas('saas_plans', [
            'id' => $planId,
            'name' => 'Professional',
        ]);

        $this->assertDatabaseHas('saas_plan_prices', [
            'id' => $priceId,
            'stripe_price_id' => 'price_monthly',
            'active' => false,
        ]);

        $this->assertDatabaseHas('saas_plan_prices', [
            'saas_plan_id' => $planId,
            'amount' => 5900,
            'stripe_price_id' => 'price_monthly_v2',
            'active' => true,
        ]);

        $this
            ->actingAs($admin)
            ->deleteJson("/admin/client-portal/api/saas/plans/{$planId}")
            ->assertNoContent();

        $this->assertDatabaseHas('saas_plans', [
            'id' => $planId,
            'active' => false,
        ]);
    }

    public function test_saas_plan_cannot_be_created_for_regular_project(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $project = $this->project([
            'is_saas' => false,
        ]);

        $this
            ->actingAs($admin)
            ->postJson("/admin/client-portal/api/saas/projects/{$project->id}/plans", [
                'name' => 'Starter',
                'slug' => 'starter',
                'active' => true,
                'prices' => [],
            ])
            ->assertNotFound();
    }

    public function test_admin_can_discontinue_saas_without_deleting_project(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $project = $this->project([
            'is_saas' => true,
        ]);

        $this
            ->actingAs($admin)
            ->deleteJson("/admin/client-portal/api/saas/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'is_saas' => false,
        ]);
    }

    private function project(array $attributes = []): Project
    {
        $company = Company::query()->create([
            'name' => $attributes['company_name'] ?? 'Acme',
        ]);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => $attributes['service_name'] ?? 'Build',
            'slug' => $attributes['service_slug'] ?? uniqid('build-'),
            'active' => true,
        ]);

        return Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => $attributes['name'] ?? 'SaaS project',
            'url' => $attributes['url'] ?? uniqid('saas-project-'),
            'summary' => '',
            'portal_status' => 'draft',
            'is_published' => false,
            'is_saas' => $attributes['is_saas'] ?? true,
        ]);
    }

    private function stripeObject(string $id): StripeObject
    {
        return StripeObject::constructFrom([
            'id' => $id,
        ]);
    }
}