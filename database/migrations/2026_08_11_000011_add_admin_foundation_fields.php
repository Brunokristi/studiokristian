<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->text('billing_address')->nullable()->after('registered_address');
            $table->index(['status', 'updated_at']);
            $table->index('tax_number');
            $table->index('vat_number');
        });

        Schema::table('service_products', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('active');
            $table->index(['active', 'sort_order']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_code')->nullable()->after('portal_status');
            $table->text('internal_notes')->nullable()->after('summary');
            $table->date('started_at')->nullable()->after('internal_notes');
            $table->date('completed_at')->nullable()->after('started_at');
            $table->index(['company_id', 'portal_status']);
            $table->index(['service_product_id', 'portal_status']);
            $table->index('project_code');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'portal_status']);
            $table->dropIndex(['service_product_id', 'portal_status']);
            $table->dropIndex(['project_code']);
            $table->dropColumn(['project_code', 'internal_notes', 'started_at', 'completed_at']);
        });

        Schema::table('service_products', function (Blueprint $table) {
            $table->dropIndex(['active', 'sort_order']);
            $table->dropColumn('sort_order');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['status', 'updated_at']);
            $table->dropIndex(['tax_number']);
            $table->dropIndex(['vat_number']);
            $table->dropColumn(['display_name', 'billing_address']);
        });
    }
};