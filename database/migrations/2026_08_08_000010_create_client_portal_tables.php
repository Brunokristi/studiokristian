<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_number')->nullable()->index();
            $table->string('tax_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->text('registered_address')->nullable();
            $table->text('billing_details')->nullable();
            $table->string('status')->default('active')->index();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->boolean('can_access_portal')->default(false)->index();
            $table->boolean('can_accept_documents')->default(false);
            $table->timestamp('access_revoked_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('client_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_contact_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->string('request_identifier', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('service_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedBigInteger('default_contract_template_id')->nullable();
            $table->json('recommended_document_type_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('service_products', function (Blueprint $table) {
            $table->foreign('default_contract_template_id')->references('id')->on('contract_templates')->nullOnDelete();
        });

        Schema::create('contract_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_id')->constrained()->restrictOnDelete();
            $table->string('version', 32);
            $table->longText('content');
            $table->string('status')->default('draft')->index();
            $table->string('change_policy')->default('future_only');
            $table->text('change_summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['contract_template_id', 'version']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('service_product_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->string('portal_status')->default('active')->after('service_product_id')->index();
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('client_contact_project', function (Blueprint $table) {
            $table->foreignId('client_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['client_contact_id', 'project_id']);
        });

        Schema::create('contract_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('contract_template_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->nullable();
            $table->string('title');
            $table->string('version', 32);
            $table->string('status')->default('draft')->index();
            $table->longText('rendered_content');
            $table->string('content_hash', 64);
            $table->string('generated_pdf_path')->nullable();
            $table->string('final_pdf_path')->nullable();
            $table->string('final_pdf_hash', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        Schema::create('contract_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_instance_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('client_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('signer_name');
            $table->string('signer_email');
            $table->string('signer_position')->nullable();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('document_content_hash', 64);
            $table->string('pdf_hash', 64);
            $table->string('authentication_method');
            $table->string('request_identifier', 64)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('price_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->string('version', 32);
            $table->string('status')->default('draft')->index();
            $table->date('valid_until')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->longText('rendered_content')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('final_pdf_path')->nullable();
            $table->string('final_pdf_hash', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'number', 'version']);
        });

        Schema::create('price_offer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_offer_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('one_time');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->string('period')->nullable();
            $table->decimal('total', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('price_offer_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_offer_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('client_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('signer_name');
            $table->string('signer_email');
            $table->string('signer_position')->nullable();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('document_content_hash', 64);
            $table->string('pdf_hash', 64);
            $table->string('authentication_method');
            $table->string('request_identifier', 64)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('source_type');
            $table->string('storage_path')->nullable();
            $table->text('external_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->boolean('client_visible')->default(false)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('file_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('file_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_file_id')->nullable()->constrained('project_files')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('original_filename');
            $table->string('display_name');
            $table->string('storage_path')->unique();
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64);
            $table->string('visibility')->default('internal')->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['parent_file_id', 'version']);
        });

        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->string('category')->nullable();
            $table->boolean('client_visible')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('guide_project_file', function (Blueprint $table) {
            $table->foreignId('guide_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_file_id')->constrained()->cascadeOnDelete();
            $table->primary(['guide_id', 'project_file_id']);
        });

        Schema::create('service_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('service_name');
            $table->string('category')->nullable();
            $table->text('login_url')->nullable();
            $table->string('account_identifier')->nullable();
            $table->string('account_owner')->default('client');
            $table->string('billing_owner')->nullable();
            $table->string('renewal_responsibility')->nullable();
            $table->string('provider')->nullable();
            $table->date('renewal_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('client_visible')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('service_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider_type')->default('none');
            $table->text('external_reference')->nullable();
            $table->text('access_instructions')->nullable();
            $table->boolean('client_visible')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('service_credentials');
        Schema::dropIfExists('service_accounts');
        Schema::dropIfExists('guide_project_file');
        Schema::dropIfExists('guides');
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('file_categories');
        Schema::dropIfExists('project_documents');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('price_offer_acceptances');
        Schema::dropIfExists('price_offer_items');
        Schema::dropIfExists('price_offers');
        Schema::dropIfExists('contract_acceptances');
        Schema::dropIfExists('contract_instances');
        Schema::dropIfExists('client_contact_project');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('service_product_id');
            $table->dropColumn(['portal_status', 'archived_at']);
        });

        Schema::table('service_products', function (Blueprint $table) {
            $table->dropForeign(['default_contract_template_id']);
        });

        Schema::dropIfExists('contract_template_versions');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('service_products');
        Schema::dropIfExists('client_login_tokens');
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('companies');
    }
};