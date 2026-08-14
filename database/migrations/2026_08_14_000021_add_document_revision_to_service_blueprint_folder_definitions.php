<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_blueprint_folder_definitions', function (Blueprint $table) {
            $table->unsignedBigInteger('document_revision')->default(0)->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('service_blueprint_folder_definitions', function (Blueprint $table) {
            $table->dropColumn('document_revision');
        });
    }
};
