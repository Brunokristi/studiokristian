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
        return (new MailMessage)
            ->subject('Prihlásenie do Client Portal')
            ->greeting('Dobrý deň, '.$notifiable->first_name.',')
            ->line('Tento jednorazový odkaz vás bezpečne prihlási do Client Portal. Platí 10 minút.')
            ->action('Otvoriť Client Portal', $this->url)
            ->line('Ak ste o prihlásenie nežiadali, tento email môžete ignorovať.');
    }
}