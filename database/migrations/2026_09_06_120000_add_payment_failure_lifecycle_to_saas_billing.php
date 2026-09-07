<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('payment_failure_grace_period_days')->default(7)->after('trial_credits');
        });

        Schema::table('saas_subscriptions', function (Blueprint $table) {
            $table->timestamp('payment_failed_at')->nullable()->after('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('saas_subscriptions', function (Blueprint $table) {
            $table->dropColumn('payment_failed_at');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('payment_failure_grace_period_days');
        });
    }
};
