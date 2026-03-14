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
            $table->json('name_translations')->nullable()->after('name');
            $table->json('summary_translations')->nullable()->after('summary');
        });

        Schema::table('project_features', function (Blueprint $table) {
            $table->json('title_translations')->nullable()->after('title');
            $table->json('description_translations')->nullable()->after('description');
        });

        Schema::table('project_images', function (Blueprint $table) {
            $table->json('description_translations')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['name_translations', 'summary_translations']);
        });

        Schema::table('project_features', function (Blueprint $table) {
            $table->dropColumn(['title_translations', 'description_translations']);
        });

        Schema::table('project_images', function (Blueprint $table) {
            $table->dropColumn('description_translations');
        });
    }
};