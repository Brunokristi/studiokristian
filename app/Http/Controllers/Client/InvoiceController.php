<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectInvoice;
use App\Services\ProjectBilling\ProjectInvoicePdfService;
use App\Services\ProjectBilling\StripeProjectBillingGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InvoiceController extends Controller
{
    /**
     * Sends the customer to Stripe's Hosted Invoice Page - StudioKristian never
     * handles card data and no second payment system exists.
     */
    public function pay(
        Request $request,
        Project $project,
        ProjectInvoice $invoice,
        StripeProjectBillingGateway $stripe
    ): RedirectResponse
    {
        $this->authorizeInvoice($request, $project, $invoice);

        if ($invoice->status !== ProjectInvoice::STATUS_OPEN) {
            return redirect()
                ->route('client.projects.show', $project)
                ->withErrors(['invoice' => 'This invoice is not payable.']);
        }

        $url = $invoice->hosted_invoice_url ?: $this->refreshHostedUrl($invoice, $stripe);

        if (! $url) {
            return redirect()
                ->route('client.projects.show', $project)
                ->withErrors(['invoice' => 'Online payment is not available for this invoice yet.']);
        }

        return redirect()->away($url);
    }

    public function downloadPdf(
        Request $request,
        Project $project,
        ProjectInvoice $invoice,
        ProjectInvoicePdfService $pdf
    )
    {
        $this->authorizeInvoice($request, $project, $invoice);

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

    private function authorizeInvoice(Request $request, Project $project, ProjectInvoice $invoice): void
    {
        $contact = $request->user('client');

        abort_unless(
            $contact &&
            $invoice->project_id === $project->id &&
            $invoice->company_id === $contact->company_id &&
            $project->company_id === $contact->company_id,
            404
        );

        abort_if($invoice->status === ProjectInvoice::STATUS_DRAFT, 404);
    }
}
