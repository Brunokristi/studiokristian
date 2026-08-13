<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->ensureColumnsExist('service_blueprint_folder_definitions', [
            ['type', 'string', 'default', 'folder'],
            ['name', 'string', null, null],
            ['resource_type', 'string', 'nullable', null],
            ['requirement_level', 'string', 'nullable', null],
            ['requires_client_signature', 'boolean', 'default', false],
            ['template_name', 'string', 'nullable', null],
            ['content', 'text', 'nullable', null],
            ['url', 'string', 'nullable', null],
            ['client_visible', 'boolean', 'default', true],
            ['sort_order', 'unsignedInteger', 'default', 0],
        ]);

        $this->ensureColumnsExist('project_folders', [
            ['type', 'string', 'default', 'folder'],
            ['name', 'string', null, null],
            ['resource_type', 'string', 'nullable', null],
            ['requirement_level', 'string', 'nullable', null],
            ['requires_client_signature', 'boolean', 'default', false],
            ['template_name', 'string', 'nullable', null],
            ['content', 'text', 'nullable', null],
            ['url', 'string', 'nullable', null],
            ['client_visible', 'boolean', 'default', true],
            ['sort_order', 'unsignedInteger', 'default', 0],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['service_blueprint_folder_definitions', 'project_folders'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $tableBuilder) use ($table) {
                    foreach (['type', 'resource_type', 'requirement_level', 'requires_client_signature', 'template_name', 'content', 'url', 'client_visible', 'sort_order'] as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            $tableBuilder->dropColumn($column);
                        }
                    }
                });
            }
        }
    }

    protected function ensureColumnsExist(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBuilder) use ($table, $columns) {
            foreach ($columns as [$column, $type, $modifier, $value]) {
                if (Schema::hasColumn($table, $column)) {
                    continue;
                }

                $columnDef = $tableBuilder->$type($column);

                if ($modifier === 'nullable') {
                    $columnDef->nullable();
                } elseif ($modifier === 'default') {
                    $columnDef->default($value);
                }
            }
        });
    }
};
