<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientAttentionRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $invoiceCount,
        private readonly int $signatureCount
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Your attention is required';

        return (new MailMessage)
            ->subject('Your attention is required')
            ->view('emails.client-attention-required', [
                'subject' => $subject,
                'preview' => 'There are items waiting for you in the client portal.',
                'recipientName' => $notifiable->first_name,
                'invoiceCount' => $this->invoiceCount,
                'signatureCount' => $this->signatureCount,
                'actionUrl' => route('client.dashboard'),
            ]);
    }
}