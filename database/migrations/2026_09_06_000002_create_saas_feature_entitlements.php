<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 32);
            $table->string('unit')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });

        Schema::create('saas_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('boolean_value')->nullable();
            $table->unsignedInteger('limit_value')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->timestamps();

            $table->unique(['saas_plan_id', 'saas_feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_plan_features');
        Schema::dropIfExists('saas_features');
    }
};
