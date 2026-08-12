<?php

namespace Tests\Feature\Admin;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Project;
use App\Models\ServiceProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPortalAdminFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_endpoints_require_an_authenticated_admin(): void
    {
        $this->getJson('/admin/client-portal/api/dashboard')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->getJson('/admin/client-portal/api/dashboard')
            ->assertForbidden();
    }

    public function test_dashboard_counts_only_active_client_portal_records(): void
    {
        $activeCompany = Company::query()->create(['name' => 'Active Client']);
        Company::query()->create(['name' => 'Archived Client', 'status' => 'archived']);
        $activeProduct = $this->product('Active Product', true);
        $inactiveProduct = $this->product('Inactive Product', false);
        $this->project($activeCompany, $activeProduct, 'Client Project');
        $this->project($activeCompany, $inactiveProduct, 'Archived Project', 'archived');
        Project::query()->create(['name' => 'Public Portfolio Project', 'url' => 'public-project', 'portal_status' => 'active']);
        ClientContact::query()->create([
            'company_id' => $activeCompany->id,
            'first_name' => 'Portal',
            'last_name' => 'Contact',
            'email' => 'portal@example.test',
            'active' => true,
            'can_access_portal' => true,
        ]);

        $this->actingAs($this->admin())
            ->getJson('/admin/client-portal/api/dashboard')
            ->assertOk()
            ->assertJsonPath('counts.active_clients', 1)
            ->assertJsonPath('counts.active_projects', 1)
            ->assertJsonPath('counts.active_service_products', 1)
            ->assertJsonPath('counts.portal_contacts', 1)
            ->assertJsonCount(2, 'recent_projects');
    }

    public function test_client_index_searches_company_identifiers_and_contact_email(): void
    {
        $matching = Company::query()->create(['name' => 'Northwind Studio', 'registration_number' => '12345678']);
        Company::query()->create(['name' => 'Other Client']);
        ClientContact::query()->create([
            'company_id' => $matching->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'billing@northwind.test',
        ]);

        $this->actingAs($this->admin())
            ->getJson('/admin/client-portal/api/clients?search=billing%40northwind.test')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);

        $this->actingAs($this->admin())
            ->getJson('/admin/client-portal/api/clients?search=12345678')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_contact_access_is_normalized_when_deactivated_and_client_can_be_archived(): void
    {
        $company = Company::query()->create(['name' => 'Lifecycle Client']);
        $contact = ClientContact::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Jamie',
            'last_name' => 'Rivera',
            'email' => 'jamie@example.test',
            'active' => true,
            'can_access_portal' => true,
            'can_accept_documents' => true,
        ]);

        $this->actingAs($this->admin())
            ->putJson("/admin/client-portal/api/clients/{$company->id}/contacts/{$contact->id}", [
                'first_name' => 'Jamie',
                'last_name' => 'Rivera',
                'email' => 'jamie@example.test',
                'active' => false,
                'can_access_portal' => true,
                'can_accept_documents' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.can_access_portal', false)
            ->assertJsonPath('data.can_accept_documents', false);

        $this->assertNotNull($contact->fresh()->access_revoked_at);

        $this->actingAs($this->admin())
            ->postJson("/admin/client-portal/api/clients/{$company->id}/archive")
            ->assertNoContent();

        $this->assertSame('archived', $company->fresh()->status);
        $this->assertNotNull($company->fresh()->archived_at);
    }

    public function test_project_rejects_a_contact_from_another_company(): void
    {
        $company = Company::query()->create(['name' => 'Project Client']);
        $otherCompany = Company::query()->create(['name' => 'Other Client']);
        $product = $this->product('Web Design', true);
        $foreignContact = ClientContact::query()->create([
            'company_id' => $otherCompany->id,
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'other@example.test',
        ]);

        $this->actingAs($this->admin())
            ->postJson('/admin/client-portal/api/projects', [
                'company_id' => $company->id,
                'service_product_id' => $product->id,
                'name' => 'New Website',
                'url' => 'new-website',
                'portal_status' => 'active',
                'contact_ids' => [$foreignContact->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_ids');
    }

    public function test_deactivating_a_product_preserves_existing_projects(): void
    {
        $company = Company::query()->create(['name' => 'Product Client']);
        $product = $this->product('Brand Identity', true);
        $project = $this->project($company, $product, 'New Identity');

        $this->actingAs($this->admin())
            ->postJson("/admin/client-portal/api/service-products/{$product->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.projects_count', 1);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'service_product_id' => $product->id]);
    }

    public function test_a_generated_product_slug_is_validated_for_uniqueness(): void
    {
        $this->product('Web Design', true);

        $this->actingAs($this->admin())
            ->postJson('/admin/client-portal/api/service-products', [
                'name' => 'Web Design',
                'slug' => '',
                'description' => null,
                'active' => true,
                'sort_order' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function product(string $name, bool $active): ServiceProduct
    {
        return ServiceProduct::query()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'active' => $active,
            'sort_order' => 0,
        ]);
    }

    private function project(Company $company, ServiceProduct $product, string $name, string $status = 'active'): Project
    {
        return Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $product->id,
            'portal_status' => $status,
            'name' => $name,
            'url' => str($name)->slug(),
            'archived_at' => $status === 'archived' ? now() : null,
        ]);
    }
}