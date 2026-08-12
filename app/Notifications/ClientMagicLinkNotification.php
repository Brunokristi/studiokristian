<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientMagicLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $url)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Prihlásenie do Client Portal';

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.client-magic-link', [
                'subject' => $subject,
                'preview' => 'Váš jednorazový prihlasovací odkaz platí 10 minút.',
                'recipientName' => $notifiable->first_name,
                'actionUrl' => $this->url,
            ]);
    }
}