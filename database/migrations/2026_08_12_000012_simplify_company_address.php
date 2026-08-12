<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->update([
            'registered_address' => DB::raw("COALESCE(NULLIF(TRIM(registered_address), ''), billing_address)"),
        ]);

        Schema::table('companies', function (Blueprint $table) {
            $table->renameColumn('registered_address', 'address');
            $table->dropColumn(['display_name', 'billing_address', 'billing_details']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->renameColumn('address', 'registered_address');
            $table->string('display_name')->nullable()->after('name');
            $table->text('billing_address')->nullable()->after('registered_address');
            $table->text('billing_details')->nullable()->after('billing_address');
        });
    }
};