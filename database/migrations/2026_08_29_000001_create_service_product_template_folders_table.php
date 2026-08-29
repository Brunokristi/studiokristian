<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_product_template_folders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('service_product_template_folders')
                ->cascadeOnDelete();

            $table->string('client_key', 255);

            $table->string('type')
                ->default('folder');

            $table->string('name');

            $table->string('original_filename')
                ->nullable();

            $table->string('extension')
                ->nullable();

            $table->string('resource_type')
                ->nullable();

            $table->string('requirement_level')
                ->nullable();

            $table->boolean('requires_client_signature')
                ->default(false);

            $table->string('template_name')
                ->nullable();

            $table->text('content')
                ->nullable();

            $table->unsignedInteger('document_revision')
                ->default(0);

            $table->string('url')
                ->nullable();

            $table->string('disk')
                ->nullable();

            $table->string('storage_path')
                ->nullable();

            $table->string('mime_type')
                ->nullable();

            $table->unsignedBigInteger('size')
                ->nullable();

            $table->string('checksum', 64)
                ->nullable();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('client_visible')
                ->default(true);

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->unique([
                'service_product_id',
                'client_key',
            ]);

            $table->index([
                'service_product_id',
                'parent_id',
                'sort_order',
            ], 'service_product_template_tree_index');

            $table->index([
                'service_product_id',
                'resource_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'service_product_template_folders'
        );
    }
};