<?php

namespace Tests\Feature\Admin;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ServiceBlueprint;
use App\Models\ServiceProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectInstantiationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_creation_instantiates_the_published_service_factory(): void
    {
        Storage::fake('local');
        [$admin, $company, $contact, $product, $blueprintVersion] = $this->readyService();

        $response = $this->actingAs($admin)->postJson('/admin/client-portal/api/projects', [
            'company_id' => $company->id, 'service_product_id' => $product->id,
            'name' => 'Humanitas Website', 'url' => '', 'portal_status' => 'active',
            'configuration' => ['hosting_included' => true, 'content_source' => 'client'],
            'selected_deliverable_ids' => [], 'contact_ids' => [$contact->id], 'contract_values' => [],
        ])->assertCreated();

        $projectId = $response->json('data.id');
        $this->assertDatabaseHas('projects', ['id' => $projectId, 'service_blueprint_version_id' => $blueprintVersion->id]);
        $this->assertDatabaseHas('project_deliverables', ['project_id' => $projectId, 'key_snapshot' => 'production', 'requirement_level' => 'required']);
        $this->assertDatabaseHas('project_deliverables', ['project_id' => $projectId, 'key_snapshot' => 'seo', 'requirement_level' => 'recommended']);
        $this->assertDatabaseMissing('project_deliverables', ['project_id' => $projectId, 'key_snapshot' => 'maintenance']);
        $rootId = \App\Models\ProjectFolder::query()->where('project_id', $projectId)->where('name', 'Design')->value('id');
        $this->assertDatabaseHas('project_folders', ['project_id' => $projectId, 'parent_id' => $rootId, 'name' => 'Approved']);
        $this->assertDatabaseHas('client_contact_project', ['project_id' => $projectId, 'client_contact_id' => $contact->id]);
        $contract = \App\Models\ContractInstance::query()->where('project_id', $projectId)->sole();
        $this->assertStringContainsString('Hosting for Humanitas Website', $contract->rendered_content);
        $this->assertStringNotContainsString('Maintenance clause', $contract->rendered_content);

        $blueprintVersion->fields()->where('key', 'content_source')->first()->forceFill(['default_value' => 'provider']);
        $this->assertSame('client', \App\Models\Project::query()->find($projectId)->configuration['content_source']);
    }

    public function test_contract_render_failure_rolls_back_the_entire_project_factory(): void
    {
        Storage::fake('local');
        [$admin, $company, $contact, $product] = $this->readyService('{{unsupported.variable}}');

        $this->actingAs($admin)->postJson('/admin/client-portal/api/projects', [
            'company_id' => $company->id, 'service_product_id' => $product->id,
            'name' => 'Broken Project', 'url' => '', 'portal_status' => 'active',
            'configuration' => ['hosting_included' => true, 'content_source' => 'client'],
            'contact_ids' => [$contact->id], 'contract_values' => [],
        ])->assertServerError();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('project_deliverables', 0);
        $this->assertDatabaseCount('project_folders', 0);
        $this->assertDatabaseCount('contract_instances', 0);
    }

    private function readyService(?string $contractText = null): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::query()->create(['name' => 'Humanitas s.r.o.']);
        $contact = ClientContact::query()->create(['company_id' => $company->id, 'first_name' => 'Anna', 'last_name' => 'Novak', 'email' => 'anna@humanitas.test', 'active' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Custom Website Development', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $blueprint = ServiceBlueprint::query()->create(['service_product_id' => $product->id, 'name' => 'Website Delivery']);
        $version = $blueprint->versions()->create(['version' => '1.0', 'status' => 'draft', 'created_by' => $admin->id]);
        $version->fields()->create(['key' => 'hosting_included', 'label' => 'Hosting included?', 'type' => 'checkbox', 'required' => true]);
        $version->fields()->create(['key' => 'content_source', 'label' => 'Content supplied by', 'type' => 'select', 'required' => true, 'default_value' => 'provider', 'options' => [['label' => 'Client', 'value' => 'client'], ['label' => 'Provider', 'value' => 'provider']]]);
        foreach ([['production', 'Production website', 'required', true], ['seo', 'SEO', 'recommended', true], ['maintenance', 'Maintenance docs', 'optional', false]] as [$key, $name, $level, $selected]) {
            $version->deliverables()->create(['key' => $key, 'name' => $name, 'requirement_level' => $level, 'expected_resource_type' => 'manual_confirmation', 'default_selected' => $selected]);
        }
        $root = $version->folders()->create(['name' => 'Design']);
        $version->folders()->create(['parent_id' => $root->id, 'name' => 'Approved']);
        $version->update(['status' => 'published', 'change_summary' => 'Initial', 'published_at' => now()]);

        $template = ContractTemplate::query()->create(['service_product_id' => $product->id, 'name' => 'Website Agreement', 'slug' => 'website-agreement']);
        ContractTemplateVersion::query()->create([
            'contract_template_id' => $template->id, 'version' => '1.0', 'content' => '', 'status' => 'published', 'published_at' => now(),
            'document_schema' => ['blocks' => [
                ['type' => 'heading', 'level' => 1, 'content' => 'Agreement for {{project.name}}'],
                ['type' => 'conditional', 'conditions' => [['field' => 'hosting_included', 'operator' => 'checked']], 'blocks' => [['type' => 'clause', 'content' => $contractText ?? 'Hosting for {{project.name}}']]],
                ['type' => 'conditional', 'conditions' => [['field' => 'maintenance_included', 'operator' => 'checked']], 'blocks' => [['type' => 'clause', 'content' => 'Maintenance clause']]],
            ]],
        ]);
        $product->update(['default_contract_template_id' => $template->id]);
        return [$admin, $company, $contact, $product, $version];
    }
}