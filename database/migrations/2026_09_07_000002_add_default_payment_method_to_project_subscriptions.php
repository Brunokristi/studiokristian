<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_subscriptions', function (Blueprint $table) {
            // Saved from the customer's first hosted-invoice payment.
            $table->string('stripe_default_payment_method_id')->nullable()->after('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_subscriptions', function (Blueprint $table) {
            $table->dropColumn('stripe_default_payment_method_id');
        });
    }
};
