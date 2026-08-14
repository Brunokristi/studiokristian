<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_storage_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('company_storage_folders')->cascadeOnDelete();
            $table->string('type')->default('folder');
            $table->string('name');
            $table->string('resource_type')->nullable();
            $table->string('requirement_level')->nullable();
            $table->boolean('requires_client_signature')->default(false);
            $table->string('template_name')->nullable();
            $table->text('content')->nullable();
            $table->string('url')->nullable();
            $table->boolean('client_visible')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'parent_id', 'sort_order'], 'company_storage_tree_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_storage_folders');
    }
};
