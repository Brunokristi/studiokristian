<?php

namespace App\Services\ProjectBilling;

use App\Models\ProjectInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Renders StudioKristian's own Slovak invoice PDF from local invoice data.
 * The Stripe PDF is never used as the customer-facing document.
 */
class ProjectInvoicePdfService
{
    public function generate(ProjectInvoice $invoice): string
    {
        $invoice->loadMissing(['items', 'project', 'company']);

        $pdf = Pdf::loadView('pdf.project-invoice', [
            'invoice' => $invoice,
            'items' => $invoice->items,
            'tax' => config('billing.tax'),
            'footerText' => config('billing.invoice.footer_text'),
        ])->setPaper('a4');

        $disk = config('billing.invoice.pdf_disk');
        $path = sprintf(
            '%s/%s.pdf',
            trim((string) config('billing.invoice.pdf_path'), '/'),
            $invoice->invoice_number ?: 'draft-'.$invoice->id
        );

        Storage::disk($disk)->put($path, $pdf->output());

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    public function contents(ProjectInvoice $invoice): ?string
    {
        if (! $invoice->pdf_path) {
            return null;
        }

        $disk = Storage::disk(config('billing.invoice.pdf_disk'));

        return $disk->exists($invoice->pdf_path)
            ? $disk->get($invoice->pdf_path)
            : null;
    }
}
