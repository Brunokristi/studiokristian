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
        Schema::table('saas_plans', function (Blueprint $table) {
            $table->json('features')->nullable()->after('description');
            $table->boolean('trial_enabled')->default(false)->after('active');
            $table->unsignedInteger('trial_credits')->default(0)->after('trial_enabled');
            $table->unsignedInteger('trial_validity_days')->default(0)->after('trial_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_plans', function (Blueprint $table) {
            $table->dropColumn([
                'features',
                'trial_enabled',
                'trial_credits',
                'trial_validity_days',
            ]);
        });
    }
};