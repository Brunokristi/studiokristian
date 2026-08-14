<?php

namespace Tests\Feature;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyStorageFolder;
use App\Models\Project;
use App\Models\User;
use App\Notifications\NewClientTicketNotification;
use App\Notifications\ProjectInvitationNotification;
use App\Notifications\StaffMagicLinkNotification;
use App\Notifications\TicketAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CollaborationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_projects_are_exposed_by_portfolio_api(): void
    {
        Project::create(['name' => 'Hidden', 'url' => 'hidden', 'is_published' => false]);
        Project::create(['name' => 'Visible', 'url' => 'visible', 'is_published' => true]);

        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1)->assertJsonPath('0.url', 'visible');
        $this->getJson('/api/projects/hidden')->assertNotFound();
    }

    public function test_staff_magic_link_is_generic_single_use_and_verifies_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['is_admin' => true, 'email_verified_at' => null]);

        $this->post('/login', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, StaffMagicLinkNotification::class);
        $token = $user->loginTokens()->first();
        $this->assertNotNull($token);
    }

    public function test_admin_can_invite_same_coworker_to_multiple_projects(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Client']);
        $first = Project::create(['company_id' => $company->id, 'name' => 'One', 'url' => 'one']);
        $second = Project::create(['company_id' => $company->id, 'name' => 'Two', 'url' => 'two']);

        foreach ([$first, $second] as $project) {
            $this->actingAs($admin)->postJson("/admin/client-portal/api/projects/{$project->id}/coworkers", ['name' => 'Alex Worker', 'email' => 'alex@example.test'])->assertCreated();
        }

        $coworker = User::where('email', 'alex@example.test')->firstOrFail();
        $this->assertCount(2, $coworker->projects);
        Notification::assertSentToTimes($coworker, ProjectInvitationNotification::class, 2);
    }

    public function test_admin_can_manage_coworkers_and_list_internal_files(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'name' => 'Internal work', 'url' => 'internal-work']);

        $this->actingAs($admin)
            ->postJson('/admin/client-portal/api/coworkers', [
                'name' => 'Sam Coworker',
                'email' => 'sam@example.test',
                'project_ids' => [$project->id],
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Sam Coworker');

        $this->actingAs($admin)
            ->getJson('/admin/client-portal/api/coworkers')
            ->assertOk()
            ->assertJsonFragment(['email' => 'sam@example.test']);

        CompanyStorageFolder::create([
            'company_id' => null,
            'name' => 'Brief docs',
            'type' => 'folder',
            'client_visible' => false,
            'sort_order' => 0,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/admin/client-portal/api/internal-storage')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Brief docs']);
    }

    public function test_client_ticket_notifies_admin_and_project_coworker(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $coworker = User::factory()->create(['is_admin' => false]);
        $company = Company::create(['name' => 'Client']);
        $contact = ClientContact::create(['company_id' => $company->id, 'first_name' => 'Eva', 'last_name' => 'Client', 'email' => 'eva@example.test', 'active' => true, 'can_access_portal' => true]);
        $project = Project::create(['company_id' => $company->id, 'name' => 'Portal', 'url' => 'portal']);
        $project->contacts()->attach($contact);
        $project->coworkers()->attach($coworker);

        $this->actingAs($contact, 'client')->post("/client/projects/{$project->id}/tickets", ['title' => 'Checkout fails', 'description' => 'The payment step returns an error.', 'priority' => 'high'])->assertRedirect();

        $this->assertDatabaseHas('project_tickets', ['project_id' => $project->id, 'created_by_client_contact_id' => $contact->id, 'status' => 'new']);
        Notification::assertSentTo([$admin, $coworker], NewClientTicketNotification::class);
    }

    public function test_assigning_ticket_to_coworker_sends_assignment_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $coworker = User::factory()->create(['is_admin' => false]);
        $company = Company::create(['name' => 'Client']);
        $project = Project::create(['company_id' => $company->id, 'name' => 'Portal', 'url' => 'portal']);
        $project->coworkers()->attach($coworker);

        $this->actingAs($admin)->postJson("/admin/client-portal/api/projects/{$project->id}/tickets", [
            'title' => 'Homepage bug',
            'description' => 'CTA is not clickable.',
            'priority' => 'high',
            'assigned_to' => $coworker->id,
        ])->assertCreated();

        Notification::assertSentTo($coworker, TicketAssignedNotification::class);
    }

    public function test_inviting_contact_grants_portal_access_and_sends_invitation(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Client']);
        $contact = ClientContact::create(['company_id' => $company->id, 'first_name' => 'Lea', 'last_name' => 'Client', 'email' => 'lea@example.test', 'active' => false, 'can_access_portal' => false]);
        $project = Project::create(['company_id' => $company->id, 'name' => 'Portal', 'url' => 'client-portal']);

        $this->actingAs($admin)->postJson("/admin/client-portal/api/projects/{$project->id}/contacts/invite", ['contact_id' => $contact->id])->assertCreated();

        $this->assertTrue($contact->fresh()->hasPortalAccess());
        $this->assertTrue($project->contacts()->whereKey($contact->id)->exists());
        Notification::assertSentTo($contact, ProjectInvitationNotification::class);
    }

    public function test_coworker_vue_api_only_returns_assigned_projects_without_storage_paths(): void
    {
        $coworker = User::factory()->create(['is_admin' => false]);
        $assigned = Project::create(['name' => 'Assigned', 'url' => 'assigned']);
        Project::create(['name' => 'Private', 'url' => 'private']);
        $assigned->coworkers()->attach($coworker);

        $this->actingAs($coworker)->getJson('/workspace/api/projects')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Assigned')
            ->assertJsonMissingPath('0.configuration');
    }

    public function test_admin_can_load_and_update_portfolio_through_vue_api(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::create(['name' => 'Original', 'url' => 'original', 'summary' => 'Before']);

        $this->actingAs($admin)->getJson("/admin/client-portal/api/projects/{$project->id}/portfolio")
            ->assertOk()->assertJsonPath('project.name', 'Original');

        $this->actingAs($admin)->putJson("/admin/client-portal/api/projects/{$project->id}/portfolio", [
            'name' => 'Updated', 'name_sk' => 'Aktualizované', 'url' => 'updated',
            'summary' => 'After', 'summary_sk' => 'Potom', 'hex_color' => null,
            'portal_status' => 'active', 'images' => [], 'features' => [],
        ])->assertOk()->assertJsonPath('project.name', 'Updated');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Updated', 'url' => 'updated']);
    }

    public function test_vue_portfolio_editor_can_upload_gallery_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::create(['name' => 'Gallery', 'url' => 'gallery']);

        $this->actingAs($admin)->withHeader('Accept', 'application/json')->post("/admin/client-portal/api/projects/{$project->id}/portfolio", [
            '_method' => 'PUT', 'name' => 'Gallery', 'url' => 'gallery', 'portal_status' => 'active', 'hex_color' => '',
            'images' => [['file' => UploadedFile::fake()->image('cover.jpg'), 'description' => 'Cover', 'sort_order' => 0]],
            'features' => [],
        ])->assertOk()->assertJsonPath('project.images.0.description', 'Cover');

        $image = $project->images()->firstOrFail();
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $image->path));
    }
}