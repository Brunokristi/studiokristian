<?php

namespace App\Services\ProjectBilling;

use App\Models\ProjectInvoice;
use App\Services\Billing\PayBySquareService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Renders StudioKristian's own Slovak invoice PDF from local invoice data.
 * The Stripe PDF is never used as the customer-facing document.
 */
class ProjectInvoicePdfService
{
    public function __construct(
        private PayBySquareService $payBySquare,
    ) {
    }

    /**
     * Generate and store the final invoice PDF.
     */
    public function generate(
        ProjectInvoice $invoice
    ): string {
        $invoice->loadMissing([
            'items',
            'project',
            'company',
        ]);

        $pdf = $this->render(
            $invoice,
            $invoice->items
        );

        $disk = config(
            'billing.invoice.pdf_disk'
        );

        $path = sprintf(
            '%s/%s.pdf',
            trim(
                (string) config(
                    'billing.invoice.pdf_path'
                ),
                '/'
            ),
            $invoice->invoice_number
                ?: 'draft-'.$invoice->id
        );

        Storage::disk(
            $disk
        )->put(
            $path,
            $pdf->output()
        );

        $invoice->update([
            'pdf_path' => $path,
        ]);

        return $path;
    }

    /**
     * Render a temporary invoice preview.
     *
     * The invoice does not need to exist in the database.
     */
    public function preview(
        ProjectInvoice $invoice,
        Collection $items
    ): string {
        return $this->render(
            $invoice,
            $items
        )->output();
    }

    /**
     * Return already-generated PDF contents.
     */
    public function contents(
        ProjectInvoice $invoice
    ): ?string {
        if (! $invoice->pdf_path) {
            return null;
        }

        $disk = Storage::disk(
            config(
                'billing.invoice.pdf_disk'
            )
        );

        return $disk->exists(
            $invoice->pdf_path
        )
            ? $disk->get(
                $invoice->pdf_path
            )
            : null;
    }

    /**
     * Render previews and final invoices using the same
     * Blade template and PAY by square implementation.
     */
    private function render(
        ProjectInvoice $invoice,
        Collection $items
    ): \Barryvdh\DomPDF\PDF {
        $paymentQrDataUri =
            $this->paymentQrDataUri(
                $invoice
            );

        $paymentUrl =
            $this->paymentUrl(
                $invoice
            );

        return Pdf::loadView(
            'pdf.project-invoice',
            [
                'invoice' =>
                    $invoice,

                'items' =>
                    $items,

                'tax' =>
                    config(
                        'billing.tax'
                    ),

                'footerText' =>
                    config(
                        'billing.invoice.footer_text'
                    ),

                'paymentQrDataUri' =>
                    $paymentQrDataUri,

                'paymentUrl' =>
                    $paymentUrl,
            ]
        )->setPaper(
            'a4'
        );
    }

    /**
     * Return the same payment URL that is used
     * by the client portal.
     *
     * Preview invoices do not exist in the database,
     * so they cannot have a working payment URL.
     */
    private function paymentUrl(
        ProjectInvoice $invoice
    ): ?string {
        if (
            ! $invoice->exists
            || ! $invoice->id
            || ! $invoice->project_id
        ) {
            return null;
        }

        if (
            in_array(
                $invoice->status,
                [
                    'paid',
                    'void',
                    'uncollectible',
                ],
                true
            )
        ) {
            return null;
        }

        if ((int) $invoice->total <= 0) {
            return null;
        }

        return route(
            'client.invoices.pay',
            [
                $invoice->project_id,
                $invoice,
            ]
        );
    }

    /**
     * Generate PAY by square for any invoice
     * that has a supplier IBAN and positive total.
     */
    private function paymentQrDataUri(
        ProjectInvoice $invoice
    ): ?string {
        if (
            ! $invoice->supplier_iban
            || (int) $invoice->total <= 0
        ) {
            return null;
        }

        try {
            return $this
                ->payBySquare
                ->dataUri(
                    $invoice,
                    500,
                    20
                );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            return null;
        }
    }
}