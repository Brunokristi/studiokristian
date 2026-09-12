<?php

namespace App\Services\ProjectBilling;

use App\Models\ProjectInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
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

        $pdf = $this->render($invoice, $invoice->items);

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

    public function preview(ProjectInvoice $invoice, Collection $items): string
    {
        return $this->render($invoice, $items)->output();
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

    private function render(ProjectInvoice $invoice, Collection $items): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.project-invoice', [
            'invoice' => $invoice,
            'items' => $items,
            'tax' => config('billing.tax'),
            'footerText' => config('billing.invoice.footer_text'),
        ])->setPaper('a4');
    }
}
