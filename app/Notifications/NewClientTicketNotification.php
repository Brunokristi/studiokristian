<?php

namespace App\Notifications;

use App\Models\ProjectTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewClientTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly ProjectTicket $ticket) { $this->afterCommit(); }
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('New client ticket: '.$this->ticket->title)->line($this->ticket->project->name.' received a new client support ticket.')->line($this->ticket->description)->action('Open ticket board', url('/admin/client-portal/projects/'.$this->ticket->project_id));
    }
}