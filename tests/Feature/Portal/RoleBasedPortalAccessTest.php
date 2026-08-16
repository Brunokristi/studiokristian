<?php

namespace Tests\Feature\Portal;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleBasedPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_projects_in_portal_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $first = $this->createProject('Alpha');
        $second = $this->createProject('Beta');

        $this->actingAs($admin)
            ->getJson('/portal/api/projects')
            ->assertOk()
            ->assertJsonFragment(['id' => $first->id])
            ->assertJsonFragment(['id' => $second->id]);
    }

    public function test_coworker_only_sees_assigned_projects(): void
    {
        $coworker = User::factory()->create(['is_admin' => false, 'role' => 'coworker']);
        $assigned = $this->createProject('Assigned');
        $hidden = $this->createProject('Hidden');

        $assigned->coworkers()->attach($coworker->id, ['access_type' => 'coworker']);

        $this->actingAs($coworker)
            ->getJson('/portal/api/projects')
            ->assertOk()
            ->assertJsonFragment(['id' => $assigned->id])
            ->assertJsonMissing(['id' => $hidden->id]);

        $this->actingAs($coworker)
            ->getJson("/portal/api/projects/{$hidden->id}")
            ->assertForbidden();
    }

    public function test_client_is_read_only_for_project_tickets_status_updates(): void
    {
        $client = User::factory()->create(['is_admin' => false, 'role' => 'client']);
        $project = $this->createProject('Client Project');
        $project->clients()->attach($client->id, ['access_type' => 'client']);

        $ticket = $project->tickets()->create([
            'title' => 'Question',
            'description' => 'Please help',
            'priority' => 'normal',
            'status' => 'new',
            'created_by_user_id' => $client->id,
        ]);

        $this->actingAs($client)
            ->postJson("/portal/api/projects/{$project->id}/tickets", [
                'title' => 'Another request',
                'description' => 'Need details',
                'priority' => 'high',
            ])
            ->assertCreated();

        $this->actingAs($client)
            ->putJson("/portal/api/projects/{$project->id}/tickets/{$ticket->id}", [
                'status' => 'finished',
            ])
            ->assertForbidden();
    }

    public function test_client_can_download_assigned_project_file_but_cannot_open_foreign_project_file(): void
    {
        Storage::fake('local');

        $client = User::factory()->create(['is_admin' => false, 'role' => 'client']);
        $assignedProject = $this->createProject('Assigned');
        $otherProject = $this->createProject('Other');

        $assignedProject->clients()->attach($client->id, ['access_type' => 'client']);

        $assignedFile = $this->createVisibleProjectFile($assignedProject, 'assigned.pdf');
        $otherFile = $this->createVisibleProjectFile($otherProject, 'other.pdf');

        $this->actingAs($client)
            ->get("/portal/api/projects/{$assignedProject->id}/files/{$assignedFile->id}/download")
            ->assertOk();

        $this->actingAs($client)
            ->get("/portal/api/projects/{$otherProject->id}/files/{$otherFile->id}/download")
            ->assertForbidden();
    }

    public function test_client_can_only_sign_documents_in_assigned_projects(): void
    {
        $client = User::factory()->create(['is_admin' => false, 'role' => 'client']);
        $ownProject = $this->createProject('Own');
        $foreignProject = $this->createProject('Foreign');

        $ownProject->clients()->attach($client->id, ['access_type' => 'client']);

        $ownDocument = ProjectFolder::create([
            'project_id' => $ownProject->id,
            'type' => 'file',
            'name' => 'Contract Draft',
            'resource_type' => 'document',
            'requires_client_signature' => true,
            'client_visible' => true,
            'sort_order' => 0,
        ]);

        $foreignDocument = ProjectFolder::create([
            'project_id' => $foreignProject->id,
            'type' => 'file',
            'name' => 'Foreign Contract',
            'resource_type' => 'document',
            'requires_client_signature' => true,
            'client_visible' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($client)
            ->postJson("/portal/api/projects/{$ownProject->id}/documents/{$ownDocument->id}/sign")
            ->assertOk()
            ->assertJsonPath('status', 'signed');

        $this->actingAs($client)
            ->postJson("/portal/api/projects/{$foreignProject->id}/documents/{$foreignDocument->id}/sign")
            ->assertForbidden();
    }

    private function createProject(string $name): Project
    {
        $company = Company::create([
            'name' => $name.' Company',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'name' => $name,
            'url' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'portal_status' => 'active',
        ]);
    }

    private function createVisibleProjectFile(Project $project, string $name): ProjectFile
    {
        $path = 'client-portal/tests/'.uniqid().'-'.$name;
        Storage::disk('local')->put($path, 'example');

        return ProjectFile::create([
            'project_id' => $project->id,
            'project_folder_id' => null,
            'original_filename' => $name,
            'display_name' => $name,
            'extension' => 'pdf',
            'storage_path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => 7,
            'checksum' => hash('sha256', 'example'),
            'visibility' => 'client',
            'uploaded_by' => null,
        ]);
    }
}
