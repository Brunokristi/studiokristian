<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_customer_id')->unique();
            $table->timestamps();
            $table->unique(['project_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_customers');
    }
};
