<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ServiceBlueprintField;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Services\ServiceBlueprintVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ServiceBlueprintVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_blueprint_is_immutable_and_a_new_draft_is_an_independent_deep_copy(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $v1 = $service->create($product, 'Website Delivery', '1.0', $actor);
        $field = $v1->fields()->create(['key' => 'hosting_included', 'label' => 'Hosting included?', 'type' => 'checkbox']);
        $v1->deliverables()->create(['key' => 'website', 'name' => 'Production website', 'requirement_level' => 'required', 'expected_resource_type' => 'external_link']);
        $root = $v1->folders()->create(['name' => 'Design']);
        $child = $v1->folders()->create(['parent_id' => $root->id, 'name' => 'Approved']);
        $service->publish($v1, 'Initial delivery blueprint.', $actor);

        $this->expectException(LogicException::class);
        $field->update(['label' => 'Changed']);
    }

    public function test_draft_copy_preserves_hierarchy_and_existing_project_remains_on_old_version(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $v1 = $service->create($product, 'Website Delivery', '1.0', $actor);
        $v1->fields()->create(['key' => 'hosting_included', 'label' => 'Hosting included?', 'type' => 'checkbox']);
        $root = $v1->folders()->create(['name' => 'Design']);
        $v1->folders()->create(['parent_id' => $root->id, 'name' => 'Approved']);
        $service->publish($v1, 'Initial version.', $actor);
        $project = Project::query()->create(['service_product_id' => $product->id, 'service_blueprint_version_id' => $v1->id, 'name' => 'Humanitas', 'url' => 'humanitas']);

        $v2 = $service->createDraft($v1->blueprint, '1.1', $actor);
        $copiedRoot = $v2->folders()->whereNull('parent_id')->sole();
        $copiedChild = $v2->folders()->whereNotNull('parent_id')->sole();
        $this->assertSame($copiedRoot->id, $copiedChild->parent_id);
        $v2->fields()->first()->update(['label' => 'Is hosting managed?']);
        $service->publish($v2, 'Updated hosting wording.', $actor);

        $this->assertSame($v1->id, $project->fresh()->service_blueprint_version_id);
        $this->assertSame('Hosting included?', $v1->fields()->first()->label);
        $this->assertSame('Is hosting managed?', $v2->fields()->first()->label);
    }

    public function test_draft_copy_preserves_file_structure_metadata(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $v1 = $service->create($product, 'Website Delivery', '1.0', $actor);
        $root = $v1->folders()->create(['name' => 'Documents', 'type' => 'folder']);
        $v1->folders()->create([
            'parent_id' => $root->id,
            'name' => 'Client brief',
            'type' => 'file',
            'resource_type' => 'document',
            'requirement_level' => 'required',
            'requires_client_signature' => true,
            'template_name' => 'Client brief template',
            'url' => '',
        ]);
        $service->publish($v1, 'Initial version.', $actor);

        $v2 = $service->createDraft($v1->blueprint, '1.1', $actor);
        $copied = $v2->folders()->where('name', 'Client brief')->sole();

        $this->assertSame('file', $copied->type);
        $this->assertSame('document', $copied->resource_type);
        $this->assertSame('required', $copied->requirement_level);
        $this->assertTrue((bool) $copied->requires_client_signature);
        $this->assertSame('Client brief template', $copied->template_name);
    }

    public function test_blueprint_update_persists_folder_and_document_tree(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $version = $service->create($product, 'Website Delivery', '1.0', $actor);

        $this->actingAs($actor)
            ->putJson('/admin/client-portal/api/blueprint-versions/' . $version->id, [
                'fields' => [],
                'folders' => [
                    [
                        'client_key' => 'root-folder',
                        'parent_client_key' => null,
                        'type' => 'folder',
                        'name' => 'Documents',
                        'resource_type' => null,
                        'requirement_level' => null,
                        'requires_client_signature' => false,
                        'template_name' => null,
                        'content' => null,
                        'url' => null,
                        'client_visible' => true,
                    ],
                    [
                        'client_key' => 'child-folder',
                        'parent_client_key' => 'root-folder',
                        'type' => 'folder',
                        'name' => 'Legal',
                        'resource_type' => null,
                        'requirement_level' => null,
                        'requires_client_signature' => false,
                        'template_name' => null,
                        'content' => null,
                        'url' => null,
                        'client_visible' => true,
                    ],
                    [
                        'client_key' => 'doc-file',
                        'parent_client_key' => 'root-folder',
                        'type' => 'file',
                        'name' => 'Client brief',
                        'resource_type' => 'document',
                        'requirement_level' => 'required',
                        'requires_client_signature' => true,
                        'template_name' => 'Client brief template',
                        'content' => '<p>Welcome</p>',
                        'url' => '',
                        'client_visible' => true,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('service_blueprint_folder_definitions', ['service_blueprint_version_id' => $version->id, 'name' => 'Documents']);
        $this->assertDatabaseHas('service_blueprint_folder_definitions', ['service_blueprint_version_id' => $version->id, 'name' => 'Legal']);
        $this->assertDatabaseHas('service_blueprint_folder_definitions', ['service_blueprint_version_id' => $version->id, 'name' => 'Client brief', 'resource_type' => 'document', 'requirement_level' => 'required']);

        $root = $version->fresh()->folders()->where('name', 'Documents')->sole();
        $child = $version->fresh()->folders()->where('name', 'Legal')->sole();
        $this->assertSame($root->id, $child->parent_id);
    }
}