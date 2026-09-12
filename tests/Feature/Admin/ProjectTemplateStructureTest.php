<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\ServiceProduct;
use App\Models\ServiceProductTemplateFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTemplateStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_project_copies_the_service_product_structure(): void
    {
        [$admin, $company, $product] = $this->fixture();

        $response = $this->actingAs($admin)
            ->postJson('/admin/client-portal/api/projects', [
                'company_id' => $company->id,
                'service_product_id' => $product->id,
                'name' => 'New Website',
                'portal_status' => 'draft',
            ])
            ->assertCreated();

        $project = Project::query()->findOrFail($response->json('data.id'));

        $this->assertSame(2, $project->folders()->count());
        $this->assertNotNull($this->childFolder($project));
    }

    public function test_assigning_a_service_product_later_copies_the_structure(): void
    {
        [$admin, $company, $product, $emptyProduct] = $this->fixture();

        // Autosave creates the project before the intended product is chosen.
        $created = $this->actingAs($admin)
            ->postJson('/admin/client-portal/api/projects', [
                'company_id' => $company->id,
                'service_product_id' => $emptyProduct->id,
                'name' => 'Autosaved Project',
                'portal_status' => 'draft',
            ])
            ->assertCreated();

        $project = Project::query()->findOrFail($created->json('data.id'));
        $this->assertSame(0, $project->folders()->count());

        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/projects/{$project->id}", [
                'company_id' => $company->id,
                'service_product_id' => $product->id,
                'name' => 'Autosaved Project',
                'portal_status' => 'draft',
            ])
            ->assertOk();

        $project->refresh();

        $this->assertSame(2, $project->folders()->count());

        // Nesting is preserved, not flattened.
        $child = $this->childFolder($project);
        $this->assertNotNull($child);
        $this->assertNotNull($child->parent_id);
    }

    public function test_reassigning_never_duplicates_or_overwrites_existing_folders(): void
    {
        [$admin, $company, $product, $emptyProduct] = $this->fixture();

        $created = $this->actingAs($admin)
            ->postJson('/admin/client-portal/api/projects', [
                'company_id' => $company->id,
                'service_product_id' => $product->id,
                'name' => 'Structured Project',
                'portal_status' => 'draft',
            ])
            ->assertCreated();

        $project = Project::query()->findOrFail($created->json('data.id'));
        $this->assertSame(2, $project->folders()->count());

        // Switching products must not wipe or duplicate real project work.
        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/projects/{$project->id}", [
                'company_id' => $company->id,
                'service_product_id' => $emptyProduct->id,
                'name' => 'Structured Project',
                'portal_status' => 'draft',
            ])
            ->assertOk();

        $this->assertSame(2, $project->fresh()->folders()->count());

        $this->actingAs($admin)
            ->putJson("/admin/client-portal/api/projects/{$project->id}", [
                'company_id' => $company->id,
                'service_product_id' => $product->id,
                'name' => 'Structured Project',
                'portal_status' => 'draft',
            ])
            ->assertOk();

        $this->assertSame(2, $project->fresh()->folders()->count());
    }

    private function childFolder(Project $project)
    {
        return $project->folders()
            ->where('name', 'New document')
            ->first();
    }

    private function fixture(): array
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@studio.test',
            'password' => bcrypt('secret-password'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $company = Company::query()->create([
            'name' => 'ABC s.r.o.',
            'status' => 'active',
        ]);

        $product = ServiceProduct::query()->create([
            'name' => 'Custom Website Development',
            'slug' => uniqid('web-'),
            'active' => true,
        ]);

        $root = ServiceProductTemplateFolder::query()->create([
            'service_product_id' => $product->id,
            'parent_id' => null,
            'client_key' => 'dokumenty',
            'type' => 'folder',
            'name' => 'Dokumenty',
            'sort_order' => 0,
        ]);

        ServiceProductTemplateFolder::query()->create([
            'service_product_id' => $product->id,
            'parent_id' => $root->id,
            'client_key' => 'new-document',
            'type' => 'file',
            'name' => 'New document',
            'sort_order' => 1,
        ]);

        $emptyProduct = ServiceProduct::query()->create([
            'name' => 'No Template Product',
            'slug' => uniqid('empty-'),
            'active' => true,
        ]);

        return [$admin, $company, $product, $emptyProduct];
    }
}
