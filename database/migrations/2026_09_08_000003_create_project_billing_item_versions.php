<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_subscriptions', function (Blueprint $table) {
            $table->date('starts_at')->nullable()->after('currency');
            $table->date('ends_at')->nullable()->after('starts_at');
        });

        Schema::create('project_billing_item_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_billing_item_id')->constrained()->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('interval', 16)->nullable();
            $table->unsignedInteger('interval_count')->default(1);
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();

            $table->index(['project_billing_item_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_billing_item_versions');

        Schema::table('project_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
