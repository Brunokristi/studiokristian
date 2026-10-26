<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\ServiceProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoworkerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_coworker_can_open_project_workspace_and_list_assigned_projects(): void
    {
        $coworker = User::factory()->create([
            'role' => 'coworker',
        ]);
        $project = $this->project();

        $project->coworkers()->attach($coworker->id, [
            'access_type' => 'coworker',
        ]);

        $this->actingAs($coworker)
            ->get('/admin/client-portal/projects')
            ->assertOk()
            ->assertSee('client-portal-admin')
            ->assertSee($coworker->email);

        $this->actingAs($coworker)
            ->get("/admin/client-portal/projects/{$project->id}/tickets")
            ->assertOk();

        $this->actingAs($coworker)
            ->getJson('/admin/client-portal/coworker-api/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', $project->id);

        $this->actingAs($coworker)
            ->get('/admin/client-portal/projects/create')
            ->assertForbidden();
    }

    public function test_coworker_dashboard_redirects_to_the_project_workspace(): void
    {
        $coworker = User::factory()->create([
            'role' => 'coworker',
        ]);

        $this->actingAs($coworker)
            ->get('/dashboard')
            ->assertRedirect('/admin/client-portal/projects');
    }

    public function test_coworker_cannot_access_admin_only_api(): void
    {
        $coworker = User::factory()->create([
            'role' => 'coworker',
        ]);

        $this->actingAs($coworker)
            ->getJson('/admin/client-portal/api/coworkers')
            ->assertForbidden();
    }

    public function test_coworker_ticket_is_created_even_if_admin_email_cannot_be_delivered(): void
    {
        $this->ensureTicketTagTables();

        $this->mock(
            \Illuminate\Contracts\Notifications\Dispatcher::class,
            fn ($mock) => $mock->shouldReceive('send')
                ->andThrow(new \RuntimeException('554 Recipient domain is reserved'))
        );

        User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $coworker = User::factory()->create(['role' => 'coworker']);
        $project = $this->project();
        $project->coworkers()->attach($coworker->id, ['access_type' => 'coworker']);

        $this->actingAs($coworker)
            ->postJson("/admin/client-portal/coworker-api/projects/{$project->id}/tickets", [
                'description' => "## **Broken** button\n\n- Click it\n- Nothing happens",
                'priority' => 'normal',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('project_tickets', [
            'project_id' => $project->id,
            'title' => 'Broken button',
            'description' => "## **Broken** button\n\n- Click it\n- Nothing happens",
        ]);
    }

    public function test_created_ticket_can_be_deleted(): void
    {
        $this->ensureTicketTagTables();
        $coworker = User::factory()->create(['role' => 'coworker']);
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $project = $this->project();
        $project->coworkers()->attach($coworker->id, ['access_type' => 'coworker']);

        foreach ([[$coworker, 'coworker-api'], [$admin, 'api']] as [$user, $prefix]) {
            $ticketId = $this->actingAs($user)
                ->postJson("/admin/client-portal/{$prefix}/projects/{$project->id}/tickets", [
                    'description' => 'Temporary',
                    'priority' => 'normal',
                ])
                ->assertSuccessful()
                ->json('id');

            $this->actingAs($user)
                ->deleteJson("/admin/client-portal/{$prefix}/projects/{$project->id}/tickets/{$ticketId}")
                ->assertNoContent();

            $this->assertDatabaseMissing('project_tickets', ['id' => $ticketId]);
        }
    }

    // The ticket tag migration is empty, so the test database lacks these tables.
    private function ensureTicketTagTables(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('project_ticket_tag')) {
            \Illuminate\Support\Facades\Schema::create('ticket_tags', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('color')->nullable();
                $table->timestamps();
            });
            \Illuminate\Support\Facades\Schema::create('project_ticket_tag', function ($table) {
                $table->unsignedBigInteger('project_ticket_id');
                $table->unsignedBigInteger('ticket_tag_id');
            });
        }
    }

    private function project(): Project
    {
        $company = Company::query()->create([
            'name' => 'Coworker company',
        ]);

        $serviceProduct = ServiceProduct::query()->create([
            'name' => 'Coworker service',
            'slug' => 'coworker-service',
            'active' => true,
        ]);

        return Project::query()->create([
            'company_id' => $company->id,
            'service_product_id' => $serviceProduct->id,
            'name' => 'Coworker project',
            'url' => 'coworker-project',
            'summary' => '',
            'portal_status' => 'draft',
            'is_published' => false,
            'is_saas' => false,
        ]);
    }
}
