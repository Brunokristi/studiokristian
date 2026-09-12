<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_billing_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32); // refund, credit, debit_note
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('EUR');
            $table->string('reason')->nullable();
            $table->string('stripe_refund_id')->nullable();
            $table->string('stripe_credit_note_id')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_charge_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique('stripe_refund_id');
            $table->unique('stripe_credit_note_id');
            $table->index(['project_id', 'type', 'status']);
            $table->index('stripe_charge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_billing_adjustments');
    }
};
