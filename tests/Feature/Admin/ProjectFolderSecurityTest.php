<?php

namespace Tests\Feature\Admin;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectFolderSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_parent_prevents_client_download_of_visible_file(): void
    {
        Storage::fake('local');
        [$project, $contact] = $this->clientProject();
        $folder = ProjectFolder::query()->create(['project_id' => $project->id, 'name' => 'Internal', 'client_visible' => false]);
        Storage::disk('local')->put('private/test.pdf', 'content');
        $file = ProjectFile::query()->create(['project_id' => $project->id, 'project_folder_id' => $folder->id, 'original_filename' => 'test.pdf', 'display_name' => 'test.pdf', 'storage_path' => 'private/test.pdf', 'mime_type' => 'application/pdf', 'size' => 7, 'checksum' => hash('sha256', 'content'), 'visibility' => 'client']);

        $this->actingAs($contact, 'client')->get("/client/files/{$file->id}/download")->assertForbidden();
    }

    public function test_unauthorized_users_cannot_upload_project_files(): void
    {
        Storage::fake('local');
        [$project] = $this->clientProject();

        $this->withHeader('Accept', 'application/json')->post("/admin/client-portal/api/projects/{$project->id}/files", [
            'client_visible' => true,
            'file' => UploadedFile::fake()->create('test.txt', 1, 'text/plain'),
        ])->assertUnauthorized();

        $this->actingAs(User::factory()->create(['is_admin' => false]))->withHeader('Accept', 'application/json')->post("/admin/client-portal/api/projects/{$project->id}/files", [
            'client_visible' => true,
            'file' => UploadedFile::fake()->create('test.txt', 1, 'text/plain'),
        ])->assertForbidden();

        $this->assertDatabaseCount('project_files', 0);
    }

    public function test_folder_from_another_project_cannot_be_used_as_upload_target(): void
    {
        Storage::fake('local');
        [$project] = $this->clientProject();
        $other = Project::query()->create(['name' => 'Other', 'url' => 'other']);
        $foreignFolder = ProjectFolder::query()->create(['project_id' => $other->id, 'name' => 'Foreign']);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->withHeader('Accept', 'application/json')->post("/admin/client-portal/api/projects/{$project->id}/files", [
            'folder_id' => $foreignFolder->id, 'files' => [UploadedFile::fake()->image('photo.jpg')], 'client_visible' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('folder_id');
        $this->assertDatabaseCount('project_files', 0);
    }

    public function test_folder_upload_rejects_path_traversal_before_storage(): void
    {
        Storage::fake('local');
        [$project] = $this->clientProject();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->withHeader('Accept', 'application/json')->post("/admin/client-portal/api/projects/{$project->id}/files", [
            'files' => [UploadedFile::fake()->image('photo.jpg')], 'relative_paths' => ['../photo.jpg'], 'client_visible' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('relative_paths');
        $this->assertDatabaseCount('project_files', 0);
        Storage::disk('local')->assertDirectoryEmpty('client-portal/projects/'.$project->id.'/files');
    }

    private function clientProject(): array
    {
        $company = Company::query()->create(['name' => 'Client']);
        $project = Project::query()->create(['company_id' => $company->id, 'name' => 'Website', 'url' => 'website']);
        $contact = ClientContact::query()->create(['company_id' => $company->id, 'first_name' => 'Client', 'last_name' => 'User', 'email' => 'client@example.test', 'active' => true, 'can_access_portal' => true]);
        $project->contacts()->attach($contact);
        return [$project, $contact];
    }
}