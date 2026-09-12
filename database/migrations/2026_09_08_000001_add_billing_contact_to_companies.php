<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $legacyBillingColumns = [
        'billing_email',
        'billing_phone',
        'billing_address_line1',
        'billing_address_line2',
        'billing_address_city',
        'billing_address_postal_code',
        'billing_address_country',
    ];

    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('billing_contact_id')
                ->nullable()
                ->after('address')
                ->constrained('client_contacts')
                ->nullOnDelete();
        });

        $this->backfillBillingContacts();
        $this->backfillCanonicalAddress();

        $existing = array_values(array_filter(
            $this->legacyBillingColumns,
            fn (string $column) => Schema::hasColumn('companies', $column)
        ));

        if ($existing) {
            Schema::table('companies', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }

        if (Schema::hasColumn('companies', 'registered_address')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('registered_address');
            });
        }
    }

    /**
     * Only an exact, case-insensitive email match is adopted - a billing contact
     * is never guessed.
     */
    private function backfillBillingContacts(): void
    {
        if (! Schema::hasColumn('companies', 'billing_email')) {
            return;
        }

        DB::table('companies')
            ->select(['id', 'billing_email'])
            ->whereNotNull('billing_email')
            ->orderBy('id')
            ->chunkById(200, function ($companies): void {
                foreach ($companies as $company) {
                    $email = strtolower(trim((string) $company->billing_email));

                    if ($email === '') {
                        continue;
                    }

                    $contactId = DB::table('client_contacts')
                        ->where('company_id', $company->id)
                        ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                        ->orderBy('id')
                        ->value('id');

                    if ($contactId) {
                        DB::table('companies')
                            ->where('id', $company->id)
                            ->update(['billing_contact_id' => $contactId]);
                    }
                }
            });
    }

    private function backfillCanonicalAddress(): void
    {
        if (
            ! Schema::hasColumn('companies', 'registered_address') ||
            ! Schema::hasColumn('companies', 'address')
        ) {
            return;
        }

        DB::table('companies')
            ->select(['id', 'address', 'registered_address'])
            ->orderBy('id')
            ->chunkById(200, function ($companies): void {
                foreach ($companies as $company) {
                    $address = trim((string) ($company->address ?? ''));
                    $registered = trim((string) ($company->registered_address ?? ''));

                    if ($address === '' && $registered !== '') {
                        DB::table('companies')
                            ->where('id', $company->id)
                            ->update(['address' => $registered]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_contact_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('billing_address_line1')->nullable();
            $table->string('billing_address_line2')->nullable();
            $table->string('billing_address_city')->nullable();
            $table->string('billing_address_postal_code')->nullable();
            $table->string('billing_address_country', 2)->nullable();
        });
    }
};
