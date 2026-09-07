<?php

namespace App\Notifications;

use App\Models\ProjectInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectInvoicePaymentFailedNotification extends Notification implements ShouldQueue
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
        $invoice = ProjectInvoice::query()->with('project')->findOrFail($this->invoiceId);
        $subject = 'Platba zlyhala - faktúra '.$invoice->invoice_number;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.project-invoice', [
                'subject' => $subject,
                'invoice' => $invoice,
                'heading' => 'Platba zlyhala',
                'intro' => 'Platbu za faktúru '.$invoice->invoice_number.' sa nepodarilo spracovať. Skúste ju uhradiť znova.',
                'actionUrl' => $invoice->hosted_invoice_url,
            ]);
    }
}
