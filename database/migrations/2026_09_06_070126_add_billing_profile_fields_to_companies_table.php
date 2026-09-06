<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured billing profile used for Stripe Customer name/email/address - kept
     * separate from `registered_address`/`billing_details` (free text, client-portal use)
     * and from `registration_number`/`tax_number`/`vat_number` (already exist, reused as-is).
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('billing_email')->nullable()->after('vat_number');
            $table->string('billing_phone')->nullable()->after('billing_email');
            $table->string('billing_address_line1')->nullable()->after('billing_phone');
            $table->string('billing_address_line2')->nullable()->after('billing_address_line1');
            $table->string('billing_address_city')->nullable()->after('billing_address_line2');
            $table->string('billing_address_postal_code')->nullable()->after('billing_address_city');
            $table->string('billing_address_country', 2)->nullable()->after('billing_address_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'billing_email',
                'billing_phone',
                'billing_address_line1',
                'billing_address_line2',
                'billing_address_city',
                'billing_address_postal_code',
                'billing_address_country',
            ]);
        });
    }
};
