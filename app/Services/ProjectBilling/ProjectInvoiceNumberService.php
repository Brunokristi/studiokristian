<?php

namespace App\Services\ProjectBilling;

use Illuminate\Support\Facades\DB;

/**
 * One sequential number series per year for every custom project invoice,
 * regardless of payment method or whether the invoice came from Stripe.
 */
class ProjectInvoiceNumberService
{
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        // Row lock inside a transaction makes concurrent issuance collision-free.
        $number = DB::transaction(function () use ($year): int {
            $sequence = DB::table('project_invoice_number_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DB::table('project_invoice_number_sequences')->insert([
                    'year' => $year,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $sequence->last_number + 1;

            DB::table('project_invoice_number_sequences')
                ->where('year', $year)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });

        return $year.str_pad((string) $number, (int) config('billing.invoice.number_padding'), '0', STR_PAD_LEFT);
    }
}
