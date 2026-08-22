<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectFileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_single_file_upload_pipeline_persists_exact_txt_bytes_and_lists_in_folder(): void
    {
        [$project, $admin] = $this->projectAndAdmin();
        $folder = ProjectFolder::query()->create([
            'project_id' => $project->id,
            'name' => 'Uploads',
            'client_visible' => true,
            'created_by' => $admin->id,
        ]);

        $contents = 'HELLO FILE SYSTEM';
        $upload = UploadedFile::fake()->createWithContent('test.txt', $contents);

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post("/admin/client-portal/api/projects/{$project->id}/files", [
                'folder_id' => $folder->id,
                'client_visible' => true,
                'relative_path' => 'test.txt',
                'file' => $upload,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('uploaded_count', 1);
        $response->assertJsonPath('failed_count', 0);
        $response->assertJsonPath('data.0.project_id', $project->id);
        $response->assertJsonPath('data.0.folder_id', $folder->id);
        $response->assertJsonPath('data.0.original_filename', 'test.txt');

        $file = ProjectFile::query()->where('project_id', $project->id)->sole();

        $this->assertSame($folder->id, $file->project_folder_id);
        $this->assertSame('test.txt', $file->original_filename);
        $this->assertSame('txt', $file->extension);
        $this->assertSame('local', $file->disk);
        $this->assertSame(strlen($contents), (int) $file->size);
        $this->assertTrue(Storage::disk($file->disk)->exists($file->storage_path));

        $stored = Storage::disk($file->disk)->get($file->storage_path);
        $this->assertSame($contents, $stored);
        $this->assertSame(hash('sha256', $contents), hash('sha256', $stored));

        $listing = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->getJson("/admin/client-portal/api/projects/{$project->id}/files?folder_id={$folder->id}");

        $listing->assertOk();
        $listing->assertJsonCount(1, 'files');
        $listing->assertJsonPath('files.0.id', $file->id);
        $listing->assertJsonPath('files.0.display_name', 'test.txt');
    }

    public function test_upload_accepts_known_and_unknown_file_types_and_persists_metadata(): void
    {
        [$project, $admin] = $this->projectAndAdmin();
        $folder = ProjectFolder::query()->create([
            'project_id' => $project->id,
            'name' => 'Assets',
            'client_visible' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post("/admin/client-portal/api/projects/{$project->id}/files", [
                'folder_id' => $folder->id,
                'client_visible' => true,
                'files' => [
                    UploadedFile::fake()->create('proposal.pdf', 10, 'application/pdf'),
                    UploadedFile::fake()->create('design.fig', 10, 'application/octet-stream'),
                    UploadedFile::fake()->create('archive.xyz', 10, 'application/octet-stream'),
                    UploadedFile::fake()->create('README', 10, 'text/plain'),
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('uploaded_count', 4);
        $response->assertJsonPath('failed_count', 0);

        $files = ProjectFile::query()->where('project_id', $project->id)->orderBy('id')->get();
        $this->assertCount(4, $files);

        $this->assertSame('pdf', $files[0]->extension);
        $this->assertSame('fig', $files[1]->extension);
        $this->assertSame('xyz', $files[2]->extension);
        $this->assertNull($files[3]->extension);

        foreach ($files as $file) {
            $this->assertSame($folder->id, $file->project_folder_id);
            $this->assertSame('local', $file->disk);
            $this->assertGreaterThan(0, $file->size);
            Storage::disk('local')->assertExists($file->storage_path);
        }

        $listing = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->getJson("/admin/client-portal/api/projects/{$project->id}/files?folder_id={$folder->id}");

        $listing->assertOk();
        $listing->assertJsonCount(4, 'files');
    }

    public function test_multi_upload_reports_per_file_errors_and_keeps_successes(): void
    {
        [$project, $admin] = $this->projectAndAdmin();

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post("/admin/client-portal/api/projects/{$project->id}/files", [
                'client_visible' => true,
                'files' => [
                    UploadedFile::fake()->create('safe.pdf', 10, 'application/pdf'),
                    UploadedFile::fake()->create('dangerous.php', 1, 'text/x-php'),
                ],
            ]);

        $response->assertStatus(207);
        $response->assertJsonPath('uploaded_count', 1);
        $response->assertJsonPath('failed_count', 1);

        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
        $this->assertSame(1, $errors[0]['index']);

        $this->assertDatabaseCount('project_files', 1);
        $file = ProjectFile::query()->firstOrFail();
        $this->assertSame('safe.pdf', $file->original_filename);
        Storage::disk('local')->assertExists($file->storage_path);
    }

    public function test_file_delete_removes_database_record_and_physical_file(): void
    {
        [$project, $admin] = $this->projectAndAdmin();

        $path = 'client-portal/projects/' . $project->id . '/files/' . Str::uuid();
        Storage::disk('local')->put($path, 'payload');

        $file = ProjectFile::query()->create([
            'project_id' => $project->id,
            'project_folder_id' => null,
            'original_filename' => 'payload.bin',
            'display_name' => 'payload.bin',
            'extension' => 'bin',
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/octet-stream',
            'size' => 7,
            'checksum' => hash('sha256', 'payload'),
            'visibility' => 'internal',
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->deleteJson("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}")
            ->assertOk();

        $this->assertDatabaseMissing('project_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_file_delete_removes_referenced_images_from_project_documents(): void
    {
        [$project, $admin] = $this->projectAndAdmin();

        $path = 'client-portal/projects/' . $project->id . '/files/' . Str::uuid();
        Storage::disk('local')->put($path, 'image payload');

        $file = ProjectFile::query()->create([
            'project_id' => $project->id,
            'original_filename' => 'logo.png',
            'display_name' => 'logo.png',
            'extension' => 'png',
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => 'image/png',
            'size' => 13,
            'checksum' => hash('sha256', 'image payload'),
            'visibility' => 'internal',
            'uploaded_by' => $admin->id,
        ]);

        $document = ProjectFolder::query()->create([
            'project_id' => $project->id,
            'type' => 'file',
            'name' => 'Brief',
            'resource_type' => 'document',
            'content' => json_encode([
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'Keep this text.'],
                        ],
                    ],
                    [
                        'type' => 'image',
                        'attrs' => [
                            'src' => "https://example.test/admin/client-portal/api/projects/{$project->id}/files/{$file->id}/open",
                            'projectFileId' => $file->id,
                        ],
                    ],
                ],
            ]),
            'client_visible' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->deleteJson("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}")
            ->assertOk();

        $content = json_decode((string) $document->fresh()->content, true);

        $this->assertSame('doc', $content['type']);
        $this->assertCount(1, $content['content']);
        $this->assertSame('paragraph', $content['content'][0]['type']);
        $this->assertSame('Keep this text.', $content['content'][0]['content'][0]['text']);
    }

    public function test_open_streams_svg_inline_and_download_preserves_original_filename(): void
    {
        [$project, $admin] = $this->projectAndAdmin();

        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><rect width="20" height="20" fill="#f97316"/></svg>
SVG;

        $file = $this->createStoredFile(
            project: $project,
            admin: $admin,
            originalFilename: 'Klientske logo FINAL.svg',
            mimeType: 'image/svg+xml',
            contents: $svg,
            extension: 'svg'
        );

        $open = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->get("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}/open");

        $open->assertOk();
        $this->assertStringContainsString('inline', (string) $open->headers->get('Content-Disposition'));
        $this->assertSame('image/svg+xml', $open->headers->get('Content-Type'));

        $download = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->get("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}/download");

        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Klientske logo FINAL.svg', (string) $download->headers->get('Content-Disposition'));
    }

    public function test_open_uses_attachment_for_unknown_file_types(): void
    {
        [$project, $admin] = $this->projectAndAdmin();

        $file = $this->createStoredFile(
            project: $project,
            admin: $admin,
            originalFilename: 'client-assets.xyz',
            mimeType: 'application/octet-stream',
            contents: 'binary',
            extension: 'xyz'
        );

        $open = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->get("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}/open");

        $open->assertOk();
        $this->assertStringContainsString('attachment', (string) $open->headers->get('Content-Disposition'));
    }

    public function test_rename_updates_names_but_keeps_existing_extension(): void
    {
        [$project, $admin] = $this->projectAndAdmin();

        $file = $this->createStoredFile(
            project: $project,
            admin: $admin,
            originalFilename: 'logo.svg',
            mimeType: 'image/svg+xml',
            contents: '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
            extension: 'svg'
        );

        $rename = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->patchJson("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}", [
                'name' => 'Klientské logo FINAL.v3',
            ]);

        $rename->assertOk();
        $rename->assertJsonPath('display_name', 'Klientské logo FINAL.v3.svg');

        $file->refresh();
        $this->assertSame('Klientské logo FINAL.v3.svg', $file->display_name);
        $this->assertSame('Klientské logo FINAL.v3.svg', $file->original_filename);
        $this->assertSame('svg', $file->extension);
    }

    public function test_rename_rejects_extension_changes_and_path_like_names(): void
    {
        [$project, $admin] = $this->projectAndAdmin();

        $file = $this->createStoredFile(
            project: $project,
            admin: $admin,
            originalFilename: 'logo.svg',
            mimeType: 'image/svg+xml',
            contents: '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
            extension: 'svg'
        );

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->patchJson("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}", ['name' => 'logo.png'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->patchJson("/admin/client-portal/api/projects/{$project->id}/files/{$file->id}", ['name' => '../logo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_open_download_rename_and_delete_are_project_scoped(): void
    {
        [$project, $admin] = $this->projectAndAdmin();
        $otherProject = Project::query()->create([
            'company_id' => $project->company_id,
            'name' => 'Other Project',
            'url' => 'other-' . Str::lower(Str::random(8)),
        ]);

        $file = $this->createStoredFile(
            project: $project,
            admin: $admin,
            originalFilename: 'notes.txt',
            mimeType: 'text/plain',
            contents: 'hello',
            extension: 'txt'
        );

        $this->actingAs($admin)->get("/admin/client-portal/api/projects/{$otherProject->id}/files/{$file->id}/open")->assertNotFound();
        $this->actingAs($admin)->get("/admin/client-portal/api/projects/{$otherProject->id}/files/{$file->id}/download")->assertNotFound();
        $this->actingAs($admin)->patchJson("/admin/client-portal/api/projects/{$otherProject->id}/files/{$file->id}", ['name' => 'renamed'])->assertNotFound();
        $this->actingAs($admin)->deleteJson("/admin/client-portal/api/projects/{$otherProject->id}/files/{$file->id}")->assertNotFound();
    }

    private function projectAndAdmin(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::query()->create(['name' => 'Client Company']);
        $project = Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Website',
            'url' => 'website-' . Str::lower(Str::random(8)),
        ]);

        return [$project, $admin];
    }

    private function createStoredFile(
        Project $project,
        User $admin,
        string $originalFilename,
        string $mimeType,
        string $contents,
        ?string $extension = null,
        ?ProjectFolder $folder = null,
    ): ProjectFile {
        $path = 'client-portal/projects/' . $project->id . '/files/' . Str::uuid();
        Storage::disk('local')->put($path, $contents);

        return ProjectFile::query()->create([
            'project_id' => $project->id,
            'project_folder_id' => $folder?->id,
            'original_filename' => $originalFilename,
            'display_name' => $originalFilename,
            'extension' => $extension,
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => $mimeType,
            'size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'visibility' => 'internal',
            'uploaded_by' => $admin->id,
        ]);
    }
}
