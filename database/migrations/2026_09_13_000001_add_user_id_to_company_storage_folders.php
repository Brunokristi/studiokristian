<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('company_storage_folders', 'user_id')) {
            return;
        }

        Schema::table('company_storage_folders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('company_id')->constrained('users')->cascadeOnDelete();
            $table->index(['user_id', 'parent_id', 'sort_order'], 'company_storage_user_tree_index');
        });

        // Existing internal storage belongs to whoever created it (fallback: first admin).
        $fallbackOwner = DB::table('users')->where('is_admin', true)->orderBy('id')->value('id');

        DB::table('company_storage_folders')
            ->whereNull('company_id')
            ->update(['user_id' => DB::raw('COALESCE(created_by, uploaded_by'.($fallbackOwner ? ', '.(int) $fallbackOwner : '').')')]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('company_storage_folders', 'user_id')) {
            return;
        }

        Schema::table('company_storage_folders', function (Blueprint $table) {
            $table->dropIndex('company_storage_user_tree_index');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
