<?php

namespace App\Notifications;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientContactInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Company $company, private readonly string $url)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Invitation to Client Portal';

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.client-contact-invitation', [
                'subject' => $subject,
                'preview' => 'You have been invited to access your company portal.',
                'recipientName' => $notifiable->first_name,
                'companyName' => $this->company->name,
                'actionUrl' => $this->url,
            ]);
    }
}
