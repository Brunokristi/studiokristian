<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\ServiceProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingApiCredentialManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_and_list_project_credential_without_revealing_it_again(): void
    {
        $admin = $this->admin();
        $project = $this->saasProject();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                "/admin/client-portal/api/saas/projects/{$project->id}/billing-api/project-credentials",
                [
                    'name' => 'Production billing API',
                ]
            );

        $token = $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Production billing API')
            ->assertJsonPath('data.token_visible_once', true)
            ->json('data.token');

        $this->assertNotEmpty($token);
        $this->assertDatabaseMissing('saas_project_api_credentials', [
            'project_id' => $project->id,
            'token_hash' => $token,
        ]);

        $this
            ->actingAs($admin)
            ->getJson(
                "/admin/client-portal/api/saas/projects/{$project->id}/billing-api/project-credentials"
            )
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Production billing API')
            ->assertJsonMissing(['token' => $token]);
    }

    public function test_admin_can_revoke_project_credential(): void
    {
        $admin = $this->admin();
        $project = $this->saasProject();

        $credential = $project->billingApiCredentials()->create([
            'name' => 'Old credential',
            'token_hash' => hash('sha256', 'secret-token'),
        ]);

        $this
            ->actingAs($admin)
            ->deleteJson(
                "/admin/client-portal/api/saas/projects/{$project->id}/billing-api/project-credentials/{$credential->id}"
            )
            ->assertNoContent();

        $this->assertDatabaseHas('saas_project_api_credentials', [
            'id' => $credential->id,
        ]);

        $this->assertNotNull(
            $credential->fresh()->revoked_at
        );

        $this
            ->actingAs($admin)
            ->getJson(
                "/admin/client-portal/api/saas/projects/{$project->id}/billing-api/project-credentials"
            )
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_non_saas_projects_cannot_have_project_credentials(): void
    {
        $admin = $this->admin();
        $project = $this->saasProject([
            'is_saas' => false,
        ]);

        $this
            ->actingAs($admin)
            ->postJson(
                "/admin/client-portal/api/saas/projects/{$project->id}/billing-api/project-credentials",
                [
                    'name' => 'Invalid credential',
                ]
            )
            ->assertNotFound();
    }

    private function admin()
    {
        return \App\Models\User::factory()->create([
            'is_admin' => true,
        ]);
    }

    private function saasProject(array $attributes = []): Project
    {
        $company = Company::query()->create([
            'name' => 'Credential Company',
        ]);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => 'Credential Product',
            'slug' => uniqid('credential-product-'),
            'active' => true,
        ]);

        return Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => 'Credential SaaS',
            'url' => uniqid('credential-saas-'),
            'summary' => '',
            'portal_status' => 'draft',
            'is_published' => false,
            'is_saas' => true,
            ...$attributes,
        ]);
    }
}
