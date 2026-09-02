<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactRequestReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly string $requestLocale = 'en'
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->requestLocale === 'sk'
            ? 'Ďakujeme za správu'
            : 'Thanks for reaching out';

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.contact-request-received', [
                'subject' => $subject,
                'name' => $this->data['name'],
                'locale' => $this->requestLocale,
            ]);
    }
}
