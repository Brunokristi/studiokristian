<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContactRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'New contact request from '.$this->data['name'];

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.new-contact-request', [
                'subject' => $subject,
                'name' => $this->data['name'],
                'service' => $this->data['service'] ?? null,
                'contactMethod' => $this->data['contactMethod'],
                'email' => $this->data['email'] ?? null,
                'phone' => $this->data['phone'] ?? null,
                'instagram' => $this->data['instagram'] ?? null,
                'message' => $this->data['message'] ?? null,
            ]);
    }
}
