<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('coworker')->after('is_admin')->index();
            $table->foreignId('client_contact_id')->nullable()->after('role')->constrained('client_contacts')->nullOnDelete();
        });

        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);

        Schema::table('project_user', function (Blueprint $table) {
            $table->string('access_type', 32)->default('coworker')->after('user_id')->index();
        });

        DB::table('project_user')->whereNull('access_type')->update(['access_type' => 'coworker']);

        Schema::create('project_folder_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_folder_id')->constrained('project_folders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('signed_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['project_folder_id', 'user_id']);
            $table->index(['project_id', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_folder_signatures');

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropColumn('access_type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_contact_id');
            $table->dropColumn('role');
        });
    }
};
