<?php

namespace App\Notifications;

use App\Models\ProjectTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ProjectTicket $ticket)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Ticket assigned: '.$this->ticket->title;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.ticket-assigned', [
                'subject' => $subject,
                'projectName' => $this->ticket->project->name,
                'ticketTitle' => $this->ticket->title,
                'ticketDescription' => $this->ticket->description,
                'actionUrl' => url('/admin/client-portal/projects/'.$this->ticket->project_id),
            ]);
    }
}
