<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffMagicLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly string $url) { $this->afterCommit(); }
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Your Studio Kristian sign-in link';

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.staff-magic-link', [
                'subject' => $subject,
                'preview' => 'Your secure one-time sign-in link expires in 10 minutes.',
                'recipientName' => $notifiable->name,
                'actionUrl' => $this->url,
            ]);
    }
}