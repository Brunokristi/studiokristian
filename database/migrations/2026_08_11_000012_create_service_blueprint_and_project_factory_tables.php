<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_blueprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('service_blueprint_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_blueprint_id')->constrained()->cascadeOnDelete();
            $table->string('version', 32);
            $table->string('status', 32)->default('draft')->index();
            $table->text('change_summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_blueprint_id', 'version']);
        });

        Schema::create('service_blueprint_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_blueprint_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('type', 32);
            $table->boolean('required')->default(false);
            $table->json('default_value')->nullable();
            $table->json('options')->nullable();
            $table->string('section')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['service_blueprint_version_id', 'key']);
            $table->index(['service_blueprint_version_id', 'sort_order'], 'blueprint_fields_order_index');
        });

        Schema::create('service_blueprint_deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_blueprint_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('requirement_level', 32);
            $table->string('expected_resource_type', 32);
            $table->boolean('client_visible')->default(true);
            $table->boolean('default_selected')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['service_blueprint_version_id', 'key'], 'blueprint_deliverables_key_unique');
            $table->index(['service_blueprint_version_id', 'sort_order'], 'blueprint_deliverables_order_index');
        });

        Schema::create('service_blueprint_folder_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_blueprint_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('service_blueprint_folder_definitions')->cascadeOnDelete();
            $table->string('type')->default('folder');
            $table->string('name');
            $table->string('resource_type')->nullable();
            $table->string('requirement_level')->nullable();
            $table->boolean('requires_client_signature')->default(false);
            $table->string('template_name')->nullable();
            $table->string('url')->nullable();
            $table->boolean('client_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['service_blueprint_version_id', 'parent_id', 'sort_order'], 'blueprint_folders_tree_index');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('service_blueprint_version_id')->nullable()->after('service_product_id')->constrained()->nullOnDelete();
            $table->json('configuration')->nullable()->after('internal_notes');
            $table->json('contract_values')->nullable()->after('configuration');
        });

        Schema::create('project_deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_blueprint_deliverable_id')->nullable()->constrained('service_blueprint_deliverables')->nullOnDelete();
            $table->string('key_snapshot', 100);
            $table->string('name_snapshot');
            $table->text('description_snapshot')->nullable();
            $table->string('category_snapshot')->nullable();
            $table->string('requirement_level', 32);
            $table->string('expected_resource_type', 32);
            $table->boolean('client_visible')->default(true);
            $table->string('status', 32)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('project_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('project_folders')->cascadeOnDelete();
            $table->foreignId('source_blueprint_folder_id')->nullable()->constrained('service_blueprint_folder_definitions')->nullOnDelete();
            $table->string('type')->default('folder');
            $table->string('name');
            $table->string('resource_type')->nullable();
            $table->string('requirement_level')->nullable();
            $table->boolean('requires_client_signature')->default(false);
            $table->string('template_name')->nullable();
            $table->string('url')->nullable();
            $table->boolean('client_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['project_id', 'parent_id', 'sort_order'], 'project_folders_tree_index');
        });

        Schema::table('project_files', function (Blueprint $table) {
            $table->foreignId('project_folder_id')->nullable()->after('project_id')->constrained('project_folders')->nullOnDelete();
        });

        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->json('document_schema')->nullable()->after('content');
            $table->json('field_definitions')->nullable()->after('document_schema');
        });

        Schema::create('contract_clauses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->index();
            $table->timestamps();
        });

        Schema::create('contract_clause_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_clause_id')->constrained()->cascadeOnDelete();
            $table->string('version', 32);
            $table->string('status', 32)->default('draft')->index();
            $table->json('content');
            $table->text('change_summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['contract_clause_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_clause_versions');
        Schema::dropIfExists('contract_clauses');
        Schema::table('contract_template_versions', fn (Blueprint $table) => $table->dropColumn(['document_schema', 'field_definitions']));
        Schema::table('project_files', fn (Blueprint $table) => $table->dropConstrainedForeignId('project_folder_id'));
        Schema::dropIfExists('project_folders');
        Schema::dropIfExists('project_deliverables');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_blueprint_version_id');
            $table->dropColumn(['configuration', 'contract_values']);
        });
        Schema::dropIfExists('service_blueprint_folder_definitions');
        Schema::dropIfExists('service_blueprint_deliverables');
        Schema::dropIfExists('service_blueprint_fields');
        Schema::dropIfExists('service_blueprint_versions');
        Schema::dropIfExists('service_blueprints');
    }
};