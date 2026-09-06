<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_invoices', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->index()->after('stripe_invoice_id');
            $table->timestamp('invoice_date')->nullable()->after('status');
            $table->timestamp('period_start')->nullable()->after('invoice_date');
            $table->timestamp('period_end')->nullable()->after('period_start');
            $table->string('hosted_invoice_url', 2048)->nullable()->after('period_end');
            $table->string('invoice_pdf_url', 2048)->nullable()->after('hosted_invoice_url');
        });

        Schema::create('saas_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('saas_subscription_id')->nullable()->constrained('saas_subscriptions')->nullOnDelete();
            $table->foreignId('saas_invoice_id')->nullable()->constrained('saas_invoices')->nullOnDelete();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_charge_id')->nullable()->index();
            $table->unsignedInteger('amount')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 64)->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method_type')->nullable();
            $table->string('payment_method_brand')->nullable();
            $table->string('payment_method_last4', 4)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_payments');

        Schema::table('saas_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_number',
                'invoice_date',
                'period_start',
                'period_end',
                'hosted_invoice_url',
                'invoice_pdf_url',
            ]);
        });
    }
};
