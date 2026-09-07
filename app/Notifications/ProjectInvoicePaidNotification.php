<?php

namespace App\Notifications;

use App\Models\ProjectInvoice;
use App\Services\ProjectBilling\ProjectInvoicePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectInvoicePaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $invoiceId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = ProjectInvoice::query()->with(['items', 'project'])->findOrFail($this->invoiceId);
        $subject = 'Potvrdenie platby - faktúra '.$invoice->invoice_number;

        $message = (new MailMessage)
            ->subject($subject)
            ->view('emails.project-invoice', [
                'subject' => $subject,
                'invoice' => $invoice,
                'heading' => 'Platba prijatá',
                'intro' => 'Ďakujeme, platbu za faktúru '.$invoice->invoice_number.' sme prijali.',
                'actionUrl' => null,
            ]);

        $pdf = app(ProjectInvoicePdfService::class)->contents($invoice);

        if ($pdf !== null) {
            $message->attachData($pdf, 'faktura-'.$invoice->invoice_number.'.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $message;
    }
}
