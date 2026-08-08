<?php

namespace App\Notifications;

use App\Models\ContractInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $contractId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $contract = ContractInstance::query()->with(['project.company', 'acceptance'])->findOrFail($this->contractId);

        return (new MailMessage)
            ->subject('Potvrdenie prijatia dokumentu: '.$contract->title)
            ->line('Dokument: '.$contract->title)
            ->line('Verzia: '.$contract->version)
            ->line('Projekt: '.$contract->project->name)
            ->line('Spoločnosť: '.$contract->project->company->name)
            ->line('Akceptoval/a: '.$contract->acceptance->signer_name)
            ->line('Dátum a čas (UTC): '.$contract->accepted_at->toIso8601String())
            ->action('Otvoriť prijatý dokument', route('client.contracts.show', $contract));
    }
}