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
        Schema::table('saas_customer_api_credentials', function (Blueprint $table) {
            // Bridges a self-service-provisioned credential back to the SaaS product's own
            // tenant id (e.g. ADOcare's company id) so repeated provisioning is idempotent.
            $table->string('external_reference')->nullable()->after('company_id');
            $table->unique(['project_id', 'external_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_customer_api_credentials', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'external_reference']);
            $table->dropColumn('external_reference');
        });
    }
};
