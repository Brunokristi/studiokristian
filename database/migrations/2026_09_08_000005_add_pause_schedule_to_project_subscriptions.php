<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_subscriptions', function (Blueprint $table) {
            $table->timestamp('pause_at')->nullable()->after('ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('project_subscriptions', function (Blueprint $table) {
            $table->dropColumn('pause_at');
        });
    }
};
