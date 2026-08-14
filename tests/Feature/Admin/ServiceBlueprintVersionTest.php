<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ServiceBlueprintField;
use App\Models\ServiceBlueprintFolderDefinition;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Services\ServiceBlueprintVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_blueprint_update_persists_raw_document_json_and_reloads_it(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $version = $service->create($product, 'Website Delivery', '1.0', $actor);
        $documentJson = json_encode([
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Website Development Agreement']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'This is the first paragraph.']]],
                ['type' => 'bulletList', 'content' => [
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Website']]]]],
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hosting']]]]],
                ]],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $this->actingAs($actor)
            ->putJson('/admin/client-portal/api/blueprint-versions/' . $version->id, [
                'fields' => [],
                'deliverables' => [],
                'folders' => [
                    [
                        'client_key' => 'doc-file',
                        'parent_client_key' => null,
                        'type' => 'file',
                        'name' => 'Agreement',
                        'resource_type' => 'document',
                        'requirement_level' => 'required',
                        'requires_client_signature' => false,
                        'template_name' => 'Agreement template',
                        'content' => $documentJson,
                        'url' => '',
                        'client_visible' => true,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('service_blueprint_folder_definitions', [
            'service_blueprint_version_id' => $version->id,
            'name' => 'Agreement',
            'content' => $documentJson,
        ]);

        $response = $this->actingAs($actor)->getJson('/admin/client-portal/api/service-products/' . $product->id . '/blueprint');
        $response->assertOk();
        $response->assertJsonPath('version.folders.0.content', $documentJson);
    }

    public function test_blueprint_update_accepts_explicit_generic_file_resource_type(): void
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
                        'client_key' => 'assets-root',
                        'parent_client_key' => null,
                        'type' => 'folder',
                        'name' => 'Assets',
                        'resource_type' => null,
                        'requirement_level' => null,
                        'requires_client_signature' => false,
                        'template_name' => null,
                        'content' => null,
                        'url' => null,
                        'client_visible' => true,
                    ],
                    [
                        'client_key' => 'logo-file',
                        'parent_client_key' => 'assets-root',
                        'type' => 'file',
                        'name' => 'logo.svg',
                        'resource_type' => 'file',
                        'requirement_level' => 'recommended',
                        'requires_client_signature' => false,
                        'template_name' => null,
                        'content' => null,
                        'url' => null,
                        'client_visible' => true,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('service_blueprint_folder_definitions', [
            'service_blueprint_version_id' => $version->id,
            'name' => 'logo.svg',
            'type' => 'file',
            'resource_type' => 'file',
        ]);
    }

    public function test_blueprint_update_persists_external_link_url_and_tag(): void
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
                        'client_key' => 'external-link',
                        'parent_client_key' => null,
                        'type' => 'file',
                        'name' => 'Google Drive',
                        'resource_type' => 'link',
                        'requirement_level' => 'required',
                        'requires_client_signature' => false,
                        'template_name' => null,
                        'content' => null,
                        'url' => 'google.com/workspace',
                        'client_visible' => true,
                    ],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('service_blueprint_folder_definitions', [
            'service_blueprint_version_id' => $version->id,
            'type' => 'file',
            'resource_type' => 'link',
            'name' => 'Google Drive',
            'url' => 'https://google.com/workspace',
            'requirement_level' => 'required',
        ]);

        $response = $this->actingAs($actor)->getJson('/admin/client-portal/api/service-products/' . $product->id . '/blueprint');
        $response->assertOk();

        $link = collect($response->json('version.folders', []))
            ->firstWhere('resource_type', 'link');

        $this->assertNotNull($link);
        $this->assertSame('https://google.com/workspace', $link['url']);
        $this->assertSame('required', $link['requirement_level']);
    }

    public function test_document_endpoint_persists_full_document_schema_with_revision(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $version = $service->create($product, 'Website Delivery', '1.0', $actor);
        $folder = $version->folders()->create([
            'type' => 'file',
            'name' => 'Agreement',
            'resource_type' => 'document',
            'template_name' => 'Agreement',
            'content' => '',
            'client_visible' => true,
        ]);

        $document = [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Website Development Agreement']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Paragraph with '], ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'bold']]],
                ['type' => 'bulletList', 'content' => [
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hosting']]]]],
                ]],
                ['type' => 'taskList', 'content' => [
                    ['type' => 'taskItem', 'attrs' => ['checked' => true], 'content' => [['type' => 'text', 'text' => 'Checklist item']]],
                ]],
                ['type' => 'table', 'content' => [
                    ['type' => 'tableRow', 'content' => [
                        ['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Col A']]]]],
                        ['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Col B']]]]],
                    ]],
                ]],
            ],
        ];

        $response = $this->actingAs($actor)->putJson('/admin/client-portal/api/blueprint-folders/' . $folder->id . '/document', [
            'title' => 'Website Development Agreement',
            'subtitle' => 'Agreement between Provider and Client',
            'document_schema' => $document,
            'revision' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('saved_revision', 1);

        $fresh = ServiceBlueprintFolderDefinition::query()->findOrFail($folder->id);
        $decoded = json_decode((string) $fresh->content, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $fresh->document_revision);
        $this->assertSame('Website Development Agreement', $fresh->name);
        $this->assertSame('Website Development Agreement', $decoded['title']);
        $this->assertSame('Agreement between Provider and Client', $decoded['subtitle']);
        $this->assertSame('doc', $decoded['doc']['type']);
        $this->assertSame('heading', $decoded['doc']['content'][0]['type']);
        $this->assertSame('Website Development Agreement', $decoded['doc']['content'][0]['content'][0]['text']);
        $this->assertSame('bold', $decoded['doc']['content'][1]['content'][1]['marks'][0]['type']);
        $this->assertSame('bulletList', $decoded['doc']['content'][2]['type']);
        $this->assertSame('taskList', $decoded['doc']['content'][3]['type']);
        $this->assertSame('table', $decoded['doc']['content'][4]['type']);
    }

    public function test_document_endpoint_rejects_stale_revision_and_does_not_overwrite(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $version = $service->create($product, 'Website Delivery', '1.0', $actor);
        $folder = $version->folders()->create([
            'type' => 'file',
            'name' => 'Agreement',
            'resource_type' => 'document',
            'template_name' => 'Agreement',
            'content' => '',
            'client_visible' => true,
        ]);

        $this->actingAs($actor)->putJson('/admin/client-portal/api/blueprint-folders/' . $folder->id . '/document', [
            'title' => 'First',
            'subtitle' => 'One',
            'document_schema' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'v1']]]]],
            'revision' => 1,
        ])->assertOk();

        $stale = $this->actingAs($actor)->putJson('/admin/client-portal/api/blueprint-folders/' . $folder->id . '/document', [
            'title' => 'Second',
            'subtitle' => 'Two',
            'document_schema' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'stale']]]]],
            'revision' => 1,
        ]);

        $stale->assertStatus(409);
        $stale->assertJsonPath('saved_revision', 1);
        $stale->assertJsonPath('expected_revision', 2);

        $fresh = ServiceBlueprintFolderDefinition::query()->findOrFail($folder->id);
        $decoded = json_decode((string) $fresh->content, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $fresh->document_revision);
        $this->assertSame('First', $decoded['title']);
        $this->assertSame('v1', $decoded['doc']['content'][0]['content'][0]['text']);
    }

    public function test_document_endpoint_rejects_published_blueprint_document_changes(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $version = $service->create($product, 'Website Delivery', '1.0', $actor);
        $folder = $version->folders()->create([
            'type' => 'file',
            'name' => 'Agreement',
            'resource_type' => 'document',
            'template_name' => 'Agreement',
            'content' => '',
            'client_visible' => true,
        ]);

        $service->publish($version, 'Publish immutable blueprint.', $actor);

        $this->actingAs($actor)->putJson('/admin/client-portal/api/blueprint-folders/' . $folder->id . '/document', [
            'title' => 'Published',
            'subtitle' => 'No edit',
            'document_schema' => ['type' => 'doc', 'content' => []],
            'revision' => 1,
        ])->assertStatus(409);
    }

    public function test_document_endpoint_rejects_generic_file_resource(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $version = $service->create($product, 'Website Delivery', '1.0', $actor);
        $genericFile = $version->folders()->create([
            'type' => 'file',
            'name' => 'logo.svg',
            'resource_type' => 'file',
            'template_name' => null,
            'content' => null,
            'client_visible' => true,
        ]);

        $this->actingAs($actor)->putJson('/admin/client-portal/api/blueprint-folders/' . $genericFile->id . '/document', [
            'title' => 'Should fail',
            'subtitle' => '',
            'document_schema' => ['type' => 'doc', 'content' => []],
            'revision' => 1,
        ])->assertStatus(422);
    }

    public function test_blueprint_version_file_upload_stores_binary_and_creates_file_definition(): void
    {
        Storage::fake('local');

        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $version = $service->create($product, 'Website Delivery', '1.0', $actor);

        $folder = $version->folders()->create([
            'type' => 'folder',
            'name' => 'Assets',
            'client_visible' => true,
        ]);

        $contents = 'HELLO FILE SYSTEM';
        $upload = UploadedFile::fake()->createWithContent('test.txt', $contents);

        $response = $this->actingAs($actor)
            ->withHeader('Accept', 'application/json')
            ->post('/admin/client-portal/api/blueprint-versions/' . $version->id . '/files', [
                'folder_id' => $folder->id,
                'client_visible' => true,
                'file' => $upload,
                'relative_path' => 'test.txt',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('uploaded_count', 1);
        $response->assertJsonPath('failed_count', 0);

        $file = ServiceBlueprintFolderDefinition::query()
            ->where('service_blueprint_version_id', $version->id)
            ->where('type', 'file')
            ->sole();

        $this->assertSame($folder->id, $file->parent_id);
        $this->assertSame('file', $file->resource_type);
        $this->assertSame('test.txt', $file->name);
        $this->assertSame('test.txt', $file->original_filename);
        $this->assertSame('txt', $file->extension);
        $this->assertSame('local', $file->disk);
        $this->assertSame(strlen($contents), (int) $file->size);
        $this->assertNotEmpty($file->storage_path);
        Storage::disk('local')->assertExists($file->storage_path);

        $stored = Storage::disk('local')->get($file->storage_path);
        $this->assertSame($contents, $stored);
        $this->assertSame(hash('sha256', $contents), hash('sha256', $stored));

        $open = $this->actingAs($actor)
            ->get('/admin/client-portal/api/blueprint-folders/' . $file->id . '/open');

        $open->assertOk();
        $this->assertStringContainsString('inline', (string) $open->headers->get('Content-Disposition'));
        $this->assertSame(hash('sha256', $contents), hash('sha256', $open->streamedContent()));

        $download = $this->actingAs($actor)
            ->get('/admin/client-portal/api/blueprint-folders/' . $file->id . '/download');

        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->assertSame(hash('sha256', $contents), hash('sha256', $download->streamedContent()));
    }

    public function test_blueprint_version_upload_creates_nested_folders_from_relative_paths(): void
    {
        Storage::fake('local');

        $actor = User::factory()->create(['is_admin' => true]);
        $product = ServiceProduct::query()->create(['name' => 'Website', 'slug' => 'website', 'active' => true, 'sort_order' => 0]);
        $service = app(ServiceBlueprintVersionService::class);
        $version = $service->create($product, 'Website Delivery', '1.0', $actor);

        $baseFolder = $version->folders()->create([
            'type' => 'folder',
            'name' => 'Assets',
            'client_visible' => true,
        ]);

        $uploadA = UploadedFile::fake()->createWithContent('readme.txt', 'A');
        $uploadB = UploadedFile::fake()->createWithContent('notes.md', 'B');

        $response = $this->actingAs($actor)
            ->withHeader('Accept', 'application/json')
            ->post('/admin/client-portal/api/blueprint-versions/' . $version->id . '/files', [
                'folder_id' => $baseFolder->id,
                'client_visible' => true,
                'files' => [$uploadA, $uploadB],
                'relative_paths' => [
                    'Brand/Guidelines/readme.txt',
                    'Brand/notes.md',
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('uploaded_count', 2);

        $brand = ServiceBlueprintFolderDefinition::query()
            ->where('service_blueprint_version_id', $version->id)
            ->where('type', 'folder')
            ->where('name', 'Brand')
            ->sole();

        $guidelines = ServiceBlueprintFolderDefinition::query()
            ->where('service_blueprint_version_id', $version->id)
            ->where('type', 'folder')
            ->where('name', 'Guidelines')
            ->where('parent_id', $brand->id)
            ->sole();

        ServiceBlueprintFolderDefinition::query()
            ->where('service_blueprint_version_id', $version->id)
            ->where('type', 'file')
            ->where('name', 'readme.txt')
            ->where('parent_id', $guidelines->id)
            ->sole();

        ServiceBlueprintFolderDefinition::query()
            ->where('service_blueprint_version_id', $version->id)
            ->where('type', 'file')
            ->where('name', 'notes.md')
            ->where('parent_id', $brand->id)
            ->sole();
    }
}