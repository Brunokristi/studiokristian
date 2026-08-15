<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CoworkerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_create_update_and_delete_coworkers(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $company = Company::create(['name' => 'Client']);
        $projectA = Project::create(['company_id' => $company->id, 'name' => 'Alpha', 'url' => 'alpha']);
        $projectB = Project::create(['company_id' => $company->id, 'name' => 'Beta', 'url' => 'beta']);

        $create = $this->actingAs($admin)->postJson('/admin/client-portal/api/coworkers', [
            'name' => 'Sam Worker',
            'email' => 'sam@example.test',
            'project_ids' => [$projectA->id],
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Sam Worker');
        $create->assertJsonPath('data.project_ids.0', $projectA->id);

        $coworker = User::query()->where('email', 'sam@example.test')->sole();
        $this->assertFalse($coworker->is_admin);
        $this->assertTrue($coworker->projects()->whereKey($projectA->id)->exists());

        $list = $this->actingAs($admin)->getJson('/admin/client-portal/api/coworkers');
        $list->assertOk();
        $list->assertJsonPath('data.0.email', 'sam@example.test');
        $list->assertJsonPath('meta.total', 1);

        $show = $this->actingAs($admin)->getJson('/admin/client-portal/api/coworkers/'.$coworker->id);
        $show->assertOk();
        $show->assertJsonPath('data.id', $coworker->id);

        $update = $this->actingAs($admin)->putJson('/admin/client-portal/api/coworkers/'.$coworker->id, [
            'name' => 'Samuel Worker',
            'email' => 'samuel@example.test',
            'project_ids' => [$projectA->id, $projectB->id],
        ]);

        $update->assertOk();
        $update->assertJsonPath('data.email', 'samuel@example.test');
        $this->assertDatabaseHas('users', ['id' => $coworker->id, 'email' => 'samuel@example.test', 'name' => 'Samuel Worker']);
        $this->assertTrue($coworker->fresh()->projects()->whereKey($projectB->id)->exists());

        $delete = $this->actingAs($admin)->deleteJson('/admin/client-portal/api/coworkers/'.$coworker->id);
        $delete->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $coworker->id]);
        $this->assertDatabaseMissing('project_user', ['user_id' => $coworker->id]);
    }
}
