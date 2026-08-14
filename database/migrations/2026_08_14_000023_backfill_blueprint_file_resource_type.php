<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_blueprint_folder_definitions')
            ->where('type', 'file')
            ->whereNull('resource_type')
            ->update(['resource_type' => 'document']);

        DB::table('project_folders')
            ->where('type', 'file')
            ->whereNull('resource_type')
            ->update(['resource_type' => 'document']);
    }

    public function down(): void
    {
        // Intentionally left blank: do not erase explicit resource typing on rollback.
    }
};
