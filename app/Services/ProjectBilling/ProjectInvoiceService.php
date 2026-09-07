<?php

namespace App\Services\ProjectBilling;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectBillingItem;
use App\Models\ProjectInvoice;
use App\Models\ProjectInvoiceItem;
use App\Notifications\ProjectInvoiceIssuedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Builds the business-facing invoice locally, then mirrors it into Stripe so the
 * same document/number exists in both systems.
 */
class ProjectInvoiceService
{
    public function __construct(
        private StripeProjectBillingGateway $stripe,
        private ProjectInvoiceNumberService $numbers,
        private ProjectInvoicePdfService $pdf,
    ) {
    }

    /**
     * @param  Collection<int, ProjectBillingItem>  $items
     */
    public function createOneTimeInvoice(
        Project $project,
        Collection $items,
        string $paymentMethod = ProjectInvoice::METHOD_STRIPE_HOSTED,
        array $overrides = []
    ): ProjectInvoice {
        if ($items->isEmpty()) {
            throw new RuntimeException('An invoice needs at least one billing item.');
        }

        $company = $project->company;

        if (! $company) {
            throw new RuntimeException('The project has no client company to invoice.');
        }

        $invoice = DB::transaction(function () use ($project, $company, $items, $paymentMethod, $overrides): ProjectInvoice {
            $issueDate = isset($overrides['issue_date'])
                ? Carbon::parse($overrides['issue_date'])
                : now();
            $dueDate = isset($overrides['due_date'])
                ? Carbon::parse($overrides['due_date'])
                : $issueDate->copy()->addDays((int) config('billing.invoice.due_days'));
            $deliveryDate = isset($overrides['delivery_date'])
                ? Carbon::parse($overrides['delivery_date'])
                : $issueDate->copy();

            $number = $this->numbers->next((int) $issueDate->format('Y'));
            $taxRate = $this->defaultTaxRate();

            $invoice = ProjectInvoice::query()->create(array_merge(
                [
                    'project_id' => $project->id,
                    'company_id' => $company->id,
                    'invoice_number' => $number,
                    'variable_symbol' => $number,
                    'status' => ProjectInvoice::STATUS_DRAFT,
                    'payment_status' => ProjectInvoice::PAYMENT_UNPAID,
                    'payment_method' => $paymentMethod,
                    'collection_method' => $paymentMethod === ProjectInvoice::METHOD_STRIPE_CARD
                        ? 'charge_automatically'
                        : 'send_invoice',
                    'currency' => $items->first()->currency ?: config('billing.currency'),
                    'tax_rate' => $taxRate,
                    'tax_mode' => $this->defaultTaxMode(),
                    'issue_date' => $issueDate->toDateString(),
                    'delivery_date' => $deliveryDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'notes' => $overrides['notes'] ?? null,
                ],
                $this->partySnapshot($company)
            ));

            foreach ($items->values() as $index => $item) {
                ProjectInvoiceItem::query()->create([
                    'project_invoice_id' => $invoice->id,
                    'project_billing_item_id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_amount,
                    'tax_rate' => $taxRate,
                    'amount' => $item->totalAmount(),
                    'sort_order' => $index,
                ]);

                $item->update(['status' => ProjectBillingItem::STATUS_INVOICED]);
            }

            return $this->recalculateTotals($invoice->fresh('items'));
        });

        $this->pushToStripe($invoice, $company);
        $this->pdf->generate($invoice->fresh('items'));

        if ($overrides['send_email'] ?? true) {
            $this->send($invoice->fresh(['items', 'company']));
        }

        return $invoice->fresh(['items', 'company', 'project']);
    }

    /**
     * Emails the custom PDF plus the Stripe payment link to the client's billing address.
     */
    public function send(ProjectInvoice $invoice, array $recipients = []): array
    {
        $recipients = $recipients ?: array_filter([
            $invoice->customer_email ?: $invoice->company?->resolveBillingEmail(),
        ]);

        foreach ($recipients as $email) {
            Notification::route('mail', $email)
                ->notify(new ProjectInvoiceIssuedNotification($invoice->id));
        }

        if ($recipients) {
            $invoice->update([
                'sent_at' => now(),
                'customer_email' => $invoice->customer_email ?: $recipients[0],
            ]);
        }

        return $recipients;
    }

    /**
     * Mirrors the local invoice into Stripe. A Stripe failure must not destroy the
     * already-valid local invoice, so it stays as a local draft for retry.
     */
    private function pushToStripe(ProjectInvoice $invoice, Company $company): void
    {
        try {
            $customerId = $this->stripe->resolveCustomer($company);

            $stripeInvoice = $this->stripe->createInvoice($customerId, array_filter([
                'collection_method' => $invoice->collection_method,
                'number' => $invoice->invoice_number,
                'currency' => strtolower($invoice->currency),
                'days_until_due' => $invoice->collection_method === 'send_invoice'
                    ? (int) config('billing.invoice.due_days')
                    : null,
                'description' => $invoice->project?->name,
                // Lets webhooks map the Stripe invoice back without guessing.
                'metadata' => $this->stripe->domainMetadata([
                    'project_invoice_id' => (string) $invoice->id,
                    'project_id' => (string) $invoice->project_id,
                    'company_id' => (string) $invoice->company_id,
                    'invoice_number' => (string) $invoice->invoice_number,
                ]),
            ], fn ($value) => $value !== null));

            foreach ($invoice->items as $item) {
                $stripeItem = $this->stripe->createInvoiceItem($customerId, $stripeInvoice->id, [
                    'description' => $item->name,
                    'quantity' => $item->quantity,
                    'unit_amount' => $item->unit_amount,
                    'currency' => strtolower($invoice->currency),
                ]);

                $item->update(['stripe_invoice_item_id' => $stripeItem->id]);
            }

            $finalized = $this->stripe->finalizeInvoice($stripeInvoice->id);

            $invoice->update([
                'stripe_invoice_id' => $finalized->id,
                'stripe_customer_id' => $customerId,
                'hosted_invoice_url' => $finalized->hosted_invoice_url ?? null,
                'status' => ProjectInvoice::STATUS_OPEN,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            Log::warning('Custom project invoice could not be mirrored into Stripe.', [
                'project_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function recalculateTotals(ProjectInvoice $invoice): ProjectInvoice
    {
        $subtotal = (int) $invoice->items->sum('amount');
        $taxAmount = (int) round($subtotal * ((float) $invoice->tax_rate / 100));
        $total = $subtotal + $taxAmount;

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'amount_due' => max(0, $total - (int) $invoice->amount_paid),
        ]);

        return $invoice->fresh('items');
    }

    public function partySnapshot(Company $company): array
    {
        $supplier = config('billing.supplier');

        return [
            'supplier_name' => $supplier['name'] ?? null,
            'supplier_registration_number' => $supplier['registration_number'] ?? null,
            'supplier_tax_number' => $supplier['tax_number'] ?? null,
            'supplier_vat_number' => $supplier['vat_number'] ?? null,
            'supplier_address' => $this->supplierAddress($supplier),
            'supplier_iban' => $supplier['iban'] ?? null,
            'customer_name' => $company->name,
            'customer_registration_number' => $company->registration_number,
            'customer_tax_number' => $company->tax_number,
            'customer_vat_number' => $company->vat_number,
            'customer_address' => $this->customerAddress($company),
            'customer_email' => $company->resolveBillingEmail(),
        ];
    }

    private function supplierAddress(array $supplier): string
    {
        return collect([
            $supplier['address_line1'] ?? null,
            $supplier['address_line2'] ?? null,
            trim(($supplier['postal_code'] ?? '').' '.($supplier['city'] ?? '')),
            $supplier['country'] ?? null,
        ])->filter(fn ($line) => trim((string) $line) !== '')->implode("\n");
    }

    private function customerAddress(Company $company): string
    {
        $lines = collect([
            $company->billing_address_line1,
            $company->billing_address_line2,
            trim((string) $company->billing_address_postal_code.' '.(string) $company->billing_address_city),
            $company->billing_address_country,
        ])->filter(fn ($line) => trim((string) $line) !== '');

        return $lines->isEmpty()
            ? (string) $company->address
            : $lines->implode("\n");
    }

    private function defaultTaxRate(): float
    {
        return config('billing.tax.vat_payer')
            ? (float) config('billing.tax.default_tax_rate')
            : 0.0;
    }

    private function defaultTaxMode(): string
    {
        return config('billing.tax.vat_payer')
            ? ProjectInvoice::TAX_MODE_STANDARD
            : ProjectInvoice::TAX_MODE_NONE;
    }
}
