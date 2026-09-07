<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reusable catalog of custom services. Unrelated to SaaS plans/products.
        Schema::create('billing_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('unit_amount')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('billing_type', 16)->default('one_time');
            $table->string('interval', 16)->nullable();
            $table->unsignedInteger('interval_count')->default(1);
            $table->boolean('active')->default(true);
            $table->string('stripe_product_id')->nullable();
            $table->timestamps();

            $table->index(['active', 'billing_type']);
        });

        // Company -> Stripe Customer mapping for THIS domain only.
        Schema::create('project_billing_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_customer_id')->nullable();
            $table->timestamps();

            $table->unique('company_id');
            $table->index('stripe_customer_id');
        });

        Schema::create('project_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('collection_method', 32)->default('charge_automatically');
            $table->string('currency', 3)->default('EUR');
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique('stripe_subscription_id');
            $table->index(['project_id', 'status']);
        });

        // A product enabled for a specific project, at a project-specific price.
        Schema::create('project_billing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('unit_amount')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('billing_type', 16)->default('one_time');
            $table->string('interval', 16)->nullable();
            $table->unsignedInteger('interval_count')->default(1);
            $table->string('status', 32)->default('pending');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->string('stripe_subscription_item_id')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'billing_type']);
        });

        // Race-safe sequential invoice numbering, one shared sequence per year.
        Schema::create('project_invoice_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique('year');
        });

        Schema::create('project_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->string('invoice_number')->nullable();
            $table->string('variable_symbol')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('payment_status', 32)->default('unpaid');
            $table->string('payment_method', 32)->default('stripe_card');
            $table->string('collection_method', 32)->default('charge_automatically');

            $table->string('stripe_invoice_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->text('hosted_invoice_url')->nullable();

            $table->string('currency', 3)->default('EUR');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->string('tax_mode', 32)->default('none');
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedBigInteger('amount_due')->default(0);

            // Stripe processing fees are tracked separately - never a customer discount.
            $table->unsignedBigInteger('stripe_fee_amount')->nullable();
            $table->unsignedBigInteger('stripe_net_amount')->nullable();

            $table->date('issue_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('payment_failed_at')->nullable();
            $table->timestamp('voided_at')->nullable();

            // Immutable snapshot of both parties at issue time, for accounting export.
            $table->string('supplier_name')->nullable();
            $table->string('supplier_registration_number')->nullable();
            $table->string('supplier_tax_number')->nullable();
            $table->string('supplier_vat_number')->nullable();
            $table->text('supplier_address')->nullable();
            $table->string('supplier_iban')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_registration_number')->nullable();
            $table->string('customer_tax_number')->nullable();
            $table->string('customer_vat_number')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_email')->nullable();

            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('invoice_number');
            $table->unique('stripe_invoice_id');
            $table->index(['project_id', 'status']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('project_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_billing_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('stripe_invoice_item_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('project_invoice_id');
        });

        // Independent idempotency ledger so this domain never shares SaaS webhook state.
        Schema::create('project_billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_billing_webhook_events');
        Schema::dropIfExists('project_invoice_items');
        Schema::dropIfExists('project_invoices');
        Schema::dropIfExists('project_invoice_number_sequences');
        Schema::dropIfExists('project_billing_items');
        Schema::dropIfExists('project_subscriptions');
        Schema::dropIfExists('project_billing_customers');
        Schema::dropIfExists('billing_products');
    }
};
