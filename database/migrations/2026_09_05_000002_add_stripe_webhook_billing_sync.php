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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('status')->index();
        });

        Schema::table('saas_subscriptions', function (Blueprint $table) {
            $table->timestamp('canceled_at')->nullable()->after('current_period_end');
            $table->timestamp('ended_at')->nullable()->after('canceled_at');
            $table->unique('stripe_subscription_id');
        });

        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type')->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('saas_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('saas_subscription_id')->nullable()->constrained('saas_subscriptions')->nullOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->unsignedInteger('amount_due')->default(0);
            $table->unsignedInteger('amount_paid')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 64)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_invoices');
        Schema::dropIfExists('stripe_webhook_events');

        Schema::table('saas_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['stripe_subscription_id']);
            $table->dropColumn([
                'canceled_at',
                'ended_at',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
    }
};