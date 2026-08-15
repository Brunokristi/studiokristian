<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_storage_folders', function (Blueprint $table) {
            if (! Schema::hasColumn('company_storage_folders', 'original_filename')) {
                $table->string('original_filename')->nullable()->after('name');
            }

            if (! Schema::hasColumn('company_storage_folders', 'extension')) {
                $table->string('extension', 32)->nullable()->after('original_filename');
            }

            if (! Schema::hasColumn('company_storage_folders', 'disk')) {
                $table->string('disk', 64)->nullable()->after('url');
            }

            if (! Schema::hasColumn('company_storage_folders', 'storage_path')) {
                $table->string('storage_path')->nullable()->after('disk');
            }

            if (! Schema::hasColumn('company_storage_folders', 'mime_type')) {
                $table->string('mime_type', 255)->nullable()->after('storage_path');
            }

            if (! Schema::hasColumn('company_storage_folders', 'size')) {
                $table->unsignedBigInteger('size')->nullable()->after('mime_type');
            }

            if (! Schema::hasColumn('company_storage_folders', 'checksum')) {
                $table->string('checksum', 64)->nullable()->after('size');
            }

            if (! Schema::hasColumn('company_storage_folders', 'uploaded_by')) {
                $table->foreignId('uploaded_by')->nullable()->after('checksum')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_storage_folders', function (Blueprint $table) {
            if (Schema::hasColumn('company_storage_folders', 'uploaded_by')) {
                $table->dropConstrainedForeignId('uploaded_by');
            }

            foreach (['checksum', 'size', 'mime_type', 'storage_path', 'disk', 'extension', 'original_filename'] as $column) {
                if (Schema::hasColumn('company_storage_folders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
