<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_subscriptions', function (Blueprint $table) {
            $table->string('stripe_schedule_id')->nullable()->after('stripe_subscription_id');
            $table->index('stripe_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_subscriptions', function (Blueprint $table) {
            $table->dropColumn('stripe_schedule_id');
        });
    }
};
