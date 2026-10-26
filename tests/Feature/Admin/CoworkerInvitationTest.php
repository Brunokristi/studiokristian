<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CoworkerInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_resend_invitations_for_all_coworker_projects(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $coworker = User::factory()->create([
            'role' => 'coworker',
        ]);
        $project = $this->project('Coworker project');
        $clientProject = $this->project('Client project');

        $project->coworkers()->attach($coworker->id, [
            'access_type' => 'coworker',
        ]);
        $clientProject->clients()->attach($coworker->id, [
            'access_type' => 'client',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/coworkers/{$coworker->id}/resend-invitation")
            ->assertOk()
            ->assertJsonPath('status', 'resent')
            ->assertJsonPath('project_count', 1);

        Notification::assertSentToTimes(
            $coworker,
            ProjectInvitationNotification::class,
            1
        );
    }

    public function test_resending_invitation_requires_an_assigned_project(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $coworker = User::factory()->create([
            'role' => 'coworker',
        ]);

        $this->actingAs($admin)
            ->postJson("/admin/client-portal/api/coworkers/{$coworker->id}/resend-invitation")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'This coworker has no assigned projects to invite them to.'
            );

        Notification::assertNothingSent();
    }

    private function project(string $name): Project
    {
        $company = Company::query()->create([
            'name' => $name.' company',
        ]);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => $name.' service',
            'slug' => str($name)->slug().'-'.uniqid(),
            'active' => true,
        ]);

        return Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => $name,
            'url' => str($name)->slug().'-'.uniqid(),
            'summary' => '',
            'portal_status' => 'draft',
            'is_published' => false,
            'is_saas' => false,
        ]);
    }
}
