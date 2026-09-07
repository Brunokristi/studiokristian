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
        Schema::table('saas_subscriptions', function (Blueprint $table) {
            $table->boolean('cancel_at_period_end')->default(false);
            $table->foreignId('scheduled_saas_plan_id')->nullable()->constrained('saas_plans')->nullOnDelete();
            $table->foreignId('scheduled_saas_plan_price_id')->nullable()->constrained('saas_plan_prices')->nullOnDelete();
            $table->string('stripe_schedule_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scheduled_saas_plan_id');
            $table->dropConstrainedForeignId('scheduled_saas_plan_price_id');
            $table->dropColumn(['cancel_at_period_end', 'stripe_schedule_id']);
        });
    }
};
