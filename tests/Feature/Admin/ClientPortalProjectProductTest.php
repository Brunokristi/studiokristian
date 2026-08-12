<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\ServiceProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPortalProjectProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_project_cannot_use_an_inactive_service_product(): void
    {
        $company = Company::query()->create(['name' => 'Example Client']);
        $inactiveProduct = $this->product('Legacy Hosting', false);

        $this->actingAs($this->admin())
            ->postJson('/admin/client-portal/api/projects', $this->payload($company, $inactiveProduct))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_product_id');
    }

    public function test_an_existing_inactive_product_can_be_preserved_on_project_update(): void
    {
        $company = Company::query()->create(['name' => 'Example Client']);
        $inactiveProduct = $this->product('Legacy Hosting', false);
        $project = $this->project($company, $inactiveProduct);

        $this->actingAs($this->admin())
            ->putJson("/admin/client-portal/api/projects/{$project->id}", $this->payload($company, $inactiveProduct))
            ->assertOk()
            ->assertJsonPath('data.service_product_id', $inactiveProduct->id);
    }

    public function test_a_project_cannot_switch_to_another_inactive_product(): void
    {
        $company = Company::query()->create(['name' => 'Example Client']);
        $currentProduct = $this->product('Legacy Hosting', false);
        $otherInactiveProduct = $this->product('Retired Support', false);
        $project = $this->project($company, $currentProduct);

        $this->actingAs($this->admin())
            ->putJson("/admin/client-portal/api/projects/{$project->id}", $this->payload($company, $otherInactiveProduct))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_product_id');
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

    private function project(Company $company, ServiceProduct $product): Project
    {
        return Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $product->id,
            'portal_status' => 'active',
            'name' => 'Website Redesign',
            'url' => 'website-redesign',
        ]);
    }

    private function payload(Company $company, ServiceProduct $product): array
    {
        return [
            'company_id' => $company->id,
            'service_product_id' => $product->id,
            'name' => 'Website Redesign',
            'url' => 'website-redesign',
            'portal_status' => 'active',
            'contact_ids' => [],
        ];
    }
}