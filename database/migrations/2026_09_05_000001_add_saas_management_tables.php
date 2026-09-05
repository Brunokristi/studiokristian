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
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_saas')->default(false)->after('is_published');
        });

        Schema::create('saas_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('stripe_product_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
        });

        Schema::create('saas_plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_plan_id')->constrained('saas_plans')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('EUR');
            $table->string('interval', 32);
            $table->boolean('active')->default(true);
            $table->string('stripe_price_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('saas_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_plan_id')->nullable()->constrained('saas_plans')->nullOnDelete();
            $table->foreignId('saas_plan_price_id')->nullable()->constrained('saas_plan_prices')->nullOnDelete();
            $table->string('status', 32)->default('incomplete');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->unique(['project_id', 'company_id', 'saas_plan_id'], 'saas_project_company_plan_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_subscriptions');
        Schema::dropIfExists('saas_plan_prices');
        Schema::dropIfExists('saas_plans');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('is_saas');
        });
    }
};