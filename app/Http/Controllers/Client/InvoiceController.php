<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProjectInvoice;
use App\Services\ClientPortalViewData;
use App\Services\ProjectBilling\ProjectInvoicePdfService;
use App\Services\ProjectBilling\StripeProjectBillingGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class InvoiceController extends Controller
{
    public function index(Request $request, ClientPortalViewData $viewData): View
    {
        $contact = $request->user('client');

        $invoices = ProjectInvoice::query()
            ->where('company_id', $contact->company_id)
            ->where('status', '!=', ProjectInvoice::STATUS_DRAFT)
            ->with('project')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        return view('apps.client', [
            'clientPage' => $viewData->invoices($request, $contact, $invoices),
        ]);
    }

    /**
     * Sends the customer to Stripe's Hosted Invoice Page - StudioKristian never
     * handles card data and no second payment system exists.
     */
    public function pay(Request $request, ProjectInvoice $invoice, StripeProjectBillingGateway $stripe): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->status !== ProjectInvoice::STATUS_OPEN) {
            return redirect()
                ->route('client.invoices.index')
                ->withErrors(['invoice' => 'This invoice is not payable.']);
        }

        $url = $invoice->hosted_invoice_url ?: $this->refreshHostedUrl($invoice, $stripe);

        if (! $url) {
            return redirect()
                ->route('client.invoices.index')
                ->withErrors(['invoice' => 'Online payment is not available for this invoice yet.']);
        }

        return redirect()->away($url);
    }

    public function downloadPdf(Request $request, ProjectInvoice $invoice, ProjectInvoicePdfService $pdf)
    {
        $this->authorizeInvoice($request, $invoice);

        $disk = Storage::disk(config('billing.invoice.pdf_disk'));

        if (! $invoice->pdf_path || ! $disk->exists($invoice->pdf_path)) {
            $pdf->generate($invoice);
            $invoice->refresh();
        }

        $contents = (string) $disk->get($invoice->pdf_path);

        return response()->streamDownload(
            fn () => print($contents),
            'faktura-'.$invoice->invoice_number.'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function refreshHostedUrl(ProjectInvoice $invoice, StripeProjectBillingGateway $stripe): ?string
    {
        if (! $invoice->stripe_invoice_id) {
            return null;
        }

        try {
            $stripeInvoice = $stripe->retrieveInvoice($invoice->stripe_invoice_id);
            $url = $stripeInvoice->hosted_invoice_url ?? null;

            if ($url) {
                $invoice->update(['hosted_invoice_url' => $url]);
            }

            return $url;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function authorizeInvoice(Request $request, ProjectInvoice $invoice): void
    {
        $contact = $request->user('client');

        abort_unless(
            $contact && $invoice->company_id === $contact->company_id,
            404
        );

        abort_if($invoice->status === ProjectInvoice::STATUS_DRAFT, 404);
    }
}
