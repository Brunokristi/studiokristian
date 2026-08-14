<?php

namespace Tests\Feature\Admin;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\Project;
use App\Models\ServiceBlueprint;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            'contact_ids' => [$contact->id],
        ])->assertCreated();

        $projectId = $response->json('data.id');
        $this->assertDatabaseHas('projects', ['id' => $projectId, 'service_blueprint_version_id' => $blueprintVersion->id]);
        $rootId = \App\Models\ProjectFolder::query()->where('project_id', $projectId)->where('name', 'Design')->value('id');
        $this->assertDatabaseHas('project_folders', ['project_id' => $projectId, 'parent_id' => $rootId, 'name' => 'Approved']);
        $this->assertDatabaseHas('client_contact_project', ['project_id' => $projectId, 'client_contact_id' => $contact->id]);
        $this->assertDatabaseCount('contract_instances', 0);

        $blueprintVersion->fields()->where('key', 'content_source')->first()->forceFill(['default_value' => 'provider']);
        $this->assertSame('client', \App\Models\Project::query()->find($projectId)->configuration['content_source']);
    }

    public function test_project_creation_does_not_depend_on_contract_generation(): void
    {
        Storage::fake('local');
        [$admin, $company, $contact, $product] = $this->readyService('{{unsupported.variable}}');

        $this->actingAs($admin)->postJson('/admin/client-portal/api/projects', [
            'company_id' => $company->id, 'service_product_id' => $product->id,
            'name' => 'Broken Project', 'url' => '', 'portal_status' => 'active',
            'configuration' => ['hosting_included' => true, 'content_source' => 'client'],
            'contact_ids' => [$contact->id],
        ])->assertCreated();

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('contract_instances', 0);
    }

    public function test_project_creation_copies_document_and_link_structure_from_blueprint(): void
    {
        Storage::fake('local');
        [$admin, $company, $contact, $product] = $this->readyService();

        $response = $this->actingAs($admin)->postJson('/admin/client-portal/api/projects', [
            'company_id' => $company->id,
            'service_product_id' => $product->id,
            'name' => 'Client Delivery',
            'url' => '',
            'portal_status' => 'active',
            'configuration' => ['hosting_included' => true, 'content_source' => 'client'],
            'contact_ids' => [$contact->id],
        ])->assertCreated();

        $projectId = (int) $response->json('data.id');

        $this->assertDatabaseHas('project_folders', [
            'project_id' => $projectId,
            'type' => 'file',
            'name' => 'Client brief',
            'resource_type' => 'document',
            'requirement_level' => 'required',
            'requires_client_signature' => true,
        ]);

        $this->assertDatabaseHas('project_folders', [
            'project_id' => $projectId,
            'type' => 'file',
            'name' => 'Drive folder',
            'resource_type' => 'link',
            'requirement_level' => 'optional',
            'url' => 'https://drive.example.com/workspace',
        ]);
    }

    public function test_project_creation_sends_invitation_to_assigned_contact_and_coworker(): void
    {
        Notification::fake();
        Storage::fake('local');
        [$admin, $company, $contact, $product] = $this->readyService();
        $coworker = User::factory()->create(['is_admin' => false, 'email' => 'matej@example.test']);

        $this->actingAs($admin)->postJson('/admin/client-portal/api/projects', [
            'company_id' => $company->id,
            'service_product_id' => $product->id,
            'name' => 'Assigned Delivery',
            'url' => '',
            'portal_status' => 'active',
            'configuration' => ['hosting_included' => true, 'content_source' => 'client'],
            'contact_ids' => [$contact->id],
            'coworker_ids' => [$coworker->id],
        ])->assertCreated();

        Notification::assertSentTo($contact, ProjectInvitationNotification::class);
        Notification::assertSentTo($coworker, ProjectInvitationNotification::class);
    }

    public function test_project_structure_update_rejects_deleting_required_items(): void
    {
        Storage::fake('local');
        [$admin, $company, $contact, $product] = $this->readyService();

        $response = $this->actingAs($admin)->postJson('/admin/client-portal/api/projects', [
            'company_id' => $company->id,
            'service_product_id' => $product->id,
            'name' => 'Delivery Guard',
            'url' => '',
            'portal_status' => 'active',
            'configuration' => ['hosting_included' => true, 'content_source' => 'client'],
            'contact_ids' => [$contact->id],
        ])->assertCreated();

        $project = Project::query()->with('folders')->findOrFail((int) $response->json('data.id'));

        $requiredItem = $project->folders
            ->first(fn ($folder) => $folder->type === 'file' && $folder->requirement_level === 'required');

        $this->assertNotNull($requiredItem);

        $payload = $project->folders
            ->filter(fn ($folder) => (int) $folder->id !== (int) $requiredItem->id)
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($folder) => [
                'id' => $folder->id,
                'client_key' => (string) $folder->id,
                'parent_client_key' => $folder->parent_id ? (string) $folder->parent_id : null,
                'type' => $folder->type,
                'name' => $folder->name,
                'resource_type' => $folder->resource_type,
                'requirement_level' => $folder->requirement_level,
                'requires_client_signature' => (bool) $folder->requires_client_signature,
                'template_name' => $folder->template_name,
                'content' => $folder->content,
                'url' => $folder->url,
                'client_visible' => (bool) $folder->client_visible,
            ])
            ->all();

        $this->actingAs($admin)
            ->putJson('/admin/client-portal/api/projects/' . $project->id . '/structure', [
                'folders' => $payload,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['folders']);
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
        $version->folders()->create([
            'parent_id' => $root->id,
            'type' => 'file',
            'name' => 'Client brief',
            'resource_type' => 'document',
            'requirement_level' => 'required',
            'requires_client_signature' => true,
            'template_name' => 'Client brief',
            'content' => '{"type":"doc","content":[]}',
            'client_visible' => true,
        ]);
        $version->folders()->create([
            'parent_id' => $root->id,
            'type' => 'file',
            'name' => 'Drive folder',
            'resource_type' => 'link',
            'requirement_level' => 'optional',
            'requires_client_signature' => false,
            'template_name' => null,
            'content' => null,
            'url' => 'https://drive.example.com/workspace',
            'client_visible' => true,
        ]);
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