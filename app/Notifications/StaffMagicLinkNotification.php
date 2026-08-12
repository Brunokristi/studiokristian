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
        return (new MailMessage)->subject('Your Studio Kristian sign-in link')->greeting('Hello '.$notifiable->name.',')->line('Use this one-time link to sign in. It expires in 10 minutes.')->action('Sign in to workspace', $this->url)->line('If you did not request this link, ignore this email.');
    }
}