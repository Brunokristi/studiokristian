<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceBlueprintSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_blueprint_factory_schema_is_available_without_breaking_legacy_projects(): void
    {
        foreach ([
            'service_blueprints', 'service_blueprint_versions', 'service_blueprint_fields',
            'service_blueprint_deliverables', 'service_blueprint_folder_definitions',
            'project_deliverables', 'project_folders', 'contract_clauses', 'contract_clause_versions',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}");
        }

        $legacyProject = Project::query()->create([
            'name' => 'Legacy Portfolio Project',
            'url' => 'legacy-portfolio-project',
        ]);

        $this->assertNull($legacyProject->service_blueprint_version_id);
        $this->assertNull($legacyProject->configuration);
    }
}