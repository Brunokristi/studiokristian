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

class ProjectInvoiceService
{
    public function __construct(
        private StripeProjectBillingGateway $stripe,
        private ProjectInvoiceNumberService $numbers,
        private ProjectInvoicePdfService $pdf,
    ) {
    }

    public function createOneTimeInvoice(
        Project $project,
        Collection $items,
        array $overrides = []
    ): ProjectInvoice {
        if ($items->isEmpty()) {
            throw new RuntimeException(
                'An invoice needs at least one billing item.'
            );
        }

        $company = $project->company;

        if (! $company) {
            throw new RuntimeException(
                'The project has no client company to invoice.'
            );
        }

        $company->loadMissing('billingContact');

        if (! $company->billingContact?->email) {
            throw new RuntimeException(
                'Select a billing contact with an email address for this client before invoicing.'
            );
        }

        $invoice = DB::transaction(
            function () use (
                $project,
                $company,
                $items,
                $overrides
            ): ProjectInvoice {
                $issueDate = isset($overrides['issue_date'])
                    ? Carbon::parse(
                        $overrides['issue_date']
                    )
                    : now();

                $dueDate = isset($overrides['due_date'])
                    ? Carbon::parse(
                        $overrides['due_date']
                    )
                    : $issueDate
                        ->copy()
                        ->addDays(
                            (int) config(
                                'billing.invoice.due_days'
                            )
                        );

                $deliveryDate =
                    isset($overrides['delivery_date'])
                        ? Carbon::parse(
                            $overrides['delivery_date']
                        )
                        : $issueDate->copy();

                $number = $this->numbers->next(
                    (int) $issueDate->format('Y')
                );

                $taxRate =
                    $this->defaultTaxRate();

                $invoice =
                    ProjectInvoice::query()->create(
                        array_merge(
                            [
                                'project_id' =>
                                    $project->id,

                                'company_id' =>
                                    $company->id,

                                'invoice_number' =>
                                    $number,

                                'variable_symbol' =>
                                    $number,

                                'status' =>
                                    ProjectInvoice::STATUS_DRAFT,

                                'payment_status' =>
                                    ProjectInvoice::PAYMENT_UNPAID,

                                'payment_method' =>
                                    ProjectInvoice::METHOD_STRIPE_HOSTED,

                                'collection_method' =>
                                    'send_invoice',

                                'currency' =>
                                    $items->first()->currency
                                    ?: config(
                                        'billing.currency'
                                    ),

                                'tax_rate' =>
                                    $taxRate,

                                'tax_mode' =>
                                    $this->defaultTaxMode(),

                                'issue_date' =>
                                    $issueDate->toDateString(),

                                'delivery_date' =>
                                    $deliveryDate->toDateString(),

                                'due_date' =>
                                    $dueDate->toDateString(),

                                'notes' =>
                                    $overrides['notes'] ?? null,
                            ],
                            $this->partySnapshot(
                                $company
                            )
                        )
                    );

                foreach (
                    $items->values()
                    as $index => $item
                ) {
                    ProjectInvoiceItem::query()->create([
                        'project_invoice_id' =>
                            $invoice->id,

                        'project_billing_item_id' =>
                            $item->id,

                        'name' =>
                            $item->name,

                        'description' =>
                            $item->description,

                        'quantity' =>
                            $item->quantity,

                        'unit_amount' =>
                            $item->unit_amount,

                        'tax_rate' =>
                            $taxRate,

                        'amount' =>
                            $item->totalAmount(),

                        'sort_order' =>
                            $index,
                    ]);

                    $item->update([
                        'status' =>
                            ProjectBillingItem::STATUS_INVOICED,
                    ]);
                }

                return $this->recalculateTotals(
                    $invoice->fresh('items')
                );
            }
        );

        $this->pushToStripe(
            $invoice,
            $company
        );

        $this->pdf->generate(
            $invoice->fresh('items')
        );

        if ($overrides['send_email'] ?? true) {
            $this->send(
                $invoice->fresh([
                    'items',
                    'company',
                ])
            );
        }

        return $invoice->fresh([
            'items',
            'company',
            'project',
        ]);
    }

    public function previewOneTimeInvoice(Project $project, Collection $items, array $overrides = []): string
    {
        if ($items->isEmpty()) {
            throw new RuntimeException('An invoice preview needs at least one billing item.');
        }

        $company = $project->company;

        if (! $company) {
            throw new RuntimeException('The project has no client company to invoice.');
        }

        $company->loadMissing('billingContact');

        $issueDate = isset($overrides['issue_date'])
            ? Carbon::parse($overrides['issue_date'])
            : now();
        $deliveryDate = isset($overrides['delivery_date'])
            ? Carbon::parse($overrides['delivery_date'])
            : $issueDate->copy();
        $dueDate = isset($overrides['due_date'])
            ? Carbon::parse($overrides['due_date'])
            : $issueDate->copy()->addDays((int) config('billing.invoice.due_days'));
        $taxRate = $this->defaultTaxRate();

        $lineItems = $items->values()->map(fn (ProjectBillingItem $item, int $index) => ProjectInvoiceItem::make([
            'project_billing_item_id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_amount' => $item->unit_amount,
            'tax_rate' => $taxRate,
            'amount' => $item->totalAmount(),
            'sort_order' => $index,
        ]));

        $subtotal = (int) $lineItems->sum('amount');
        $taxAmount = (int) round($subtotal * ($taxRate / 100));

        $invoice = ProjectInvoice::make(array_merge([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => 'PREVIEW',
            'variable_symbol' => 'PREVIEW',
            'status' => ProjectInvoice::STATUS_DRAFT,
            'payment_status' => ProjectInvoice::PAYMENT_UNPAID,
            'payment_method' => ProjectInvoice::METHOD_STRIPE_HOSTED,
            'collection_method' => 'send_invoice',
            'currency' => $items->first()->currency ?: config('billing.currency'),
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'tax_mode' => $this->defaultTaxMode(),
            'total' => $subtotal + $taxAmount,
            'amount_due' => $subtotal + $taxAmount,
            'issue_date' => $issueDate->toDateString(),
            'delivery_date' => $deliveryDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'notes' => $overrides['notes'] ?? null,
        ], $this->partySnapshot($company)));

        return $this->pdf->preview($invoice, $lineItems);
    }

    public function send(
        ProjectInvoice $invoice,
        array $recipients = []
    ): array {
        $recipients = $recipients ?: array_filter([
            $invoice->customer_email,
        ]);

        foreach ($recipients as $email) {
            Notification::route(
                'mail',
                $email
            )->notify(
                new ProjectInvoiceIssuedNotification(
                    $invoice->id
                )
            );
        }

        if ($recipients) {
            $invoice->update([
                'sent_at' => now(),
                'customer_email' =>
                    $invoice->customer_email
                    ?: $recipients[0],
            ]);
        }

        return $recipients;
    }

    public function createDebitNote(Project $project, string $description, int $amount): ProjectInvoice
    {
        if ($amount < 1 || ! $project->company) {
            throw new RuntimeException('A debit note needs a positive amount and a client company.');
        }

        $company = $project->company->loadMissing('billingContact');
        $number = $this->numbers->next((int) now()->format('Y'));
        $invoice = ProjectInvoice::query()->create(array_merge([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'invoice_number' => $number,
            'variable_symbol' => $number,
            'status' => ProjectInvoice::STATUS_DRAFT,
            'payment_status' => ProjectInvoice::PAYMENT_UNPAID,
            'payment_method' => ProjectInvoice::METHOD_STRIPE_HOSTED,
            'collection_method' => 'send_invoice',
            'currency' => config('billing.currency'),
            'subtotal' => $amount,
            'total' => $amount,
            'amount_due' => $amount,
            'issue_date' => today(),
            'delivery_date' => today(),
            'due_date' => today()->addDays((int) config('billing.invoice.due_days')),
            'tax_rate' => 0,
            'tax_mode' => ProjectInvoice::TAX_MODE_NONE,
        ], $this->partySnapshot($company)));

        $invoice->items()->create([
            'name' => $description,
            'quantity' => 1,
            'unit_amount' => $amount,
            'amount' => $amount,
            'tax_rate' => 0,
        ]);

        $this->pushToStripe($invoice->fresh('items'), $company);
        $this->pdf->generate($invoice->fresh('items'));
        $this->send($invoice->fresh(['items', 'company']));

        return $invoice->fresh(['items', 'company', 'project']);
    }

    public function recalculateTotals(
        ProjectInvoice $invoice
    ): ProjectInvoice {
        $subtotal =
            (int) $invoice->items->sum(
                'amount'
            );

        $taxAmount =
            (int) round(
                $subtotal *
                ((float) $invoice->tax_rate / 100)
            );

        $total =
            $subtotal +
            $taxAmount;

        $invoice->update([
            'subtotal' =>
                $subtotal,

            'tax_amount' =>
                $taxAmount,

            'total' =>
                $total,

            'amount_due' =>
                max(
                    0,
                    $total -
                    (int) $invoice->amount_paid
                ),
        ]);

        return $invoice->fresh('items');
    }

    public function partySnapshot(
        Company $company
    ): array {
        $company->loadMissing(
            'billingContact'
        );

        $supplier =
            config('billing.supplier');

        return [
            'supplier_name' =>
                $supplier['name'] ?? null,

            'supplier_registration_number' =>
                $supplier['registration_number'] ?? null,

            'supplier_tax_number' =>
                $supplier['tax_number'] ?? null,

            'supplier_vat_number' =>
                $supplier['vat_number'] ?? null,

            'supplier_address' =>
                $this->supplierAddress(
                    $supplier
                ),

            'supplier_iban' =>
                $supplier['iban'] ?? null,

            'customer_name' =>
                $company->name,

            'customer_registration_number' =>
                $company->registration_number,

            'customer_tax_number' =>
                $company->tax_number,

            'customer_vat_number' =>
                $company->vat_number,

            'customer_address' =>
                $this->customerAddress(
                    $company
                ),

            'customer_email' =>
                $company->billingContact?->email,
        ];
    }

    private function supplierAddress(
        array $supplier
    ): string {
        return collect([
            $supplier['address_line1'] ?? null,
            $supplier['address_line2'] ?? null,
            trim(
                ($supplier['postal_code'] ?? '') .
                ' ' .
                ($supplier['city'] ?? '')
            ),
            $supplier['country'] ?? null,
        ])
            ->filter(
                fn ($line) =>
                    trim((string) $line) !== ''
            )
            ->implode("\n");
    }

    private function customerAddress(
        Company $company
    ): string {
        return trim(
            (string) $company->address
        );
    }

    private function defaultTaxRate(): float
    {
        return config('billing.tax.vat_payer')
            ? (float) config(
                'billing.tax.default_tax_rate'
            )
            : 0.0;
    }

    private function defaultTaxMode(): string
    {
        return config('billing.tax.vat_payer')
            ? ProjectInvoice::TAX_MODE_STANDARD
            : ProjectInvoice::TAX_MODE_NONE;
    }

    /**
     * A previous failed push can leave an empty Stripe draft holding this invoice
     * number. Removing it frees the number so the retry can reuse it.
     */
    private function discardStaleDrafts(
        string $customerId,
        ProjectInvoice $invoice
    ): void {
        foreach ($this->stripe->listDraftInvoices($customerId) as $draft) {
            $belongsToInvoice =
                ($draft->metadata?->project_invoice_id ?? null)
                    === (string) $invoice->id
                || ($draft->number ?? null) === $invoice->invoice_number;

            if ($belongsToInvoice) {
                $this->stripe->deleteInvoice($draft->id);
            }
        }
    }


    /**
     * Re-mirrors an invoice whose original Stripe push failed, keeping its number.
     */
    public function syncToStripe(
        ProjectInvoice $invoice
    ): ProjectInvoice {
        $invoice->loadMissing([
            'items',
            'company',
            'project',
        ]);

        if (! $invoice->company) {
            throw new RuntimeException(
                'This invoice has no client company.'
            );
        }

        if ($invoice->stripe_invoice_id) {
            throw new RuntimeException(
                'This invoice already exists in Stripe.'
            );
        }

        $this->pushToStripe(
            $invoice,
            $invoice->company
        );

        return $invoice->fresh([
            'items',
            'company',
            'project',
        ]);
    }

    private function pushToStripe(
        ProjectInvoice $invoice,
        Company $company
    ): void {
        try {
            $customerId =
                $this->stripe->resolveCustomer(
                    $company
                );

            $this->discardStaleDrafts(
                $customerId,
                $invoice
            );

            $stripeInvoice =
                $this->stripe->createInvoice(
                    $customerId,
                    array_filter([
                        'collection_method' =>
                            $invoice->collection_method,

                        'number' =>
                            $invoice->invoice_number,

                        'currency' =>
                            strtolower(
                                $invoice->currency
                            ),

                        'days_until_due' =>
                            $invoice->collection_method ===
                            'send_invoice'
                                ? (int) config(
                                    'billing.invoice.due_days'
                                )
                                : null,

                        'description' =>
                            $invoice->project?->name,

                        'metadata' =>
                            $this->stripe->domainMetadata([
                                'project_invoice_id' =>
                                    (string) $invoice->id,

                                'project_id' =>
                                    (string) $invoice->project_id,

                                'company_id' =>
                                    (string) $invoice->company_id,

                                'invoice_number' =>
                                    (string) $invoice->invoice_number,
                            ]),
                    ], fn ($value) =>
                        $value !== null
                    )
                );

            foreach ($invoice->items as $item) {
                $stripeItem =
                    $this->stripe->createInvoiceItem(
                        $customerId,
                        $stripeInvoice->id,
                        [
                            'description' =>
                                $item->name,

                            'quantity' =>
                                $item->quantity,

                            // Stripe invoice items take unit_amount_decimal, not unit_amount.
                            'unit_amount_decimal' =>
                                (string) $item->unit_amount,

                            'currency' =>
                                strtolower(
                                    $invoice->currency
                                ),
                        ]
                    );

                $item->update([
                    'stripe_invoice_item_id' =>
                        $stripeItem->id,
                ]);
            }

            $finalized =
                $this->stripe->finalizeInvoice(
                    $stripeInvoice->id
                );

            $invoice->update([
                'stripe_invoice_id' =>
                    $finalized->id,

                'stripe_customer_id' =>
                    $customerId,

                'hosted_invoice_url' =>
                    $finalized->hosted_invoice_url
                    ?? null,

                'status' =>
                    ProjectInvoice::STATUS_OPEN,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            Log::warning(
                'Custom project invoice could not be mirrored into Stripe.',
                [
                    'project_invoice_id' =>
                        $invoice->id,

                    'invoice_number' =>
                        $invoice->invoice_number,

                    'message' =>
                        $exception->getMessage(),
                ]
            );
        }
    }
}