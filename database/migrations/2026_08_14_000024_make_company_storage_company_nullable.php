<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE company_storage_folders ALTER COLUMN company_id DROP NOT NULL');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        // Keep rollback safe by assigning null-scoped rows to the first company.
        DB::statement('UPDATE company_storage_folders SET company_id = (SELECT id FROM companies ORDER BY id LIMIT 1) WHERE company_id IS NULL');
        DB::statement('ALTER TABLE company_storage_folders ALTER COLUMN company_id SET NOT NULL');
    }
};
