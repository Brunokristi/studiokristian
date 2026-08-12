<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(private readonly Project $project, private readonly string $url) { $this->afterCommit(); }
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Invitation to '.$this->project->name)->greeting('Hello,')->line('You have been invited to collaborate on '.$this->project->name.'.')->action('Open project', $this->url);
    }
}