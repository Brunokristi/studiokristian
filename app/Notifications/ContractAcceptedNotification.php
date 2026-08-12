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
        $subject = 'Potvrdenie prijatia dokumentu: '.$contract->title;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.contract-accepted', [
                'subject' => $subject,
                'details' => [
                    'Dokument' => $contract->title,
                    'Verzia' => $contract->version,
                    'Projekt' => $contract->project->name,
                    'Spoločnosť' => $contract->project->company->name,
                    'Akceptoval/a' => $contract->acceptance->signer_name,
                    'Dátum a čas (UTC)' => $contract->accepted_at->toIso8601String(),
                ],
                'actionUrl' => route('client.contracts.show', $contract),
            ]);
    }
}