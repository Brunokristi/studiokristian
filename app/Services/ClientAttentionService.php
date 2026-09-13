<?php

namespace App\Services;

use App\Models\ClientAttentionReminder;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\ProjectInvoice;
use App\Notifications\ClientAttentionRequiredNotification;
use Illuminate\Support\Facades\DB;

class ClientAttentionService
{
    public function __construct(
        private readonly ClientDocumentSignatureService $signatures
    ) {
    }

    public function notifyCompany(Company $company): int
    {
        $company->loadMissing(['billingContact.company', 'contacts.company', 'projects.folders']);

        return $company->contacts
            ->filter(fn (ClientContact $contact) =>
                $contact->email &&
                $contact->hasPortalAccess() &&
                ($contact->id === $company->billing_contact_id || $contact->can_accept_documents)
            )
            ->sum(fn (ClientContact $contact) => (int) $this->notifyContact($company, $contact));
    }

    public function notifyContact(Company $company, ClientContact $contact): bool
    {
        $projectIds = $company->projects->pluck('id');

        $invoiceIds = $contact->id === $company->billing_contact_id
            ? ProjectInvoice::query()
                ->where('company_id', $company->id)
                ->whereIn('project_id', $projectIds)
                ->where('status', ProjectInvoice::STATUS_OPEN)
                ->where('amount_due', '>', 0)
                ->orderBy('id')
                ->pluck('id')
            : collect();

        $signatureIds = $contact->can_accept_documents
            ? $company->projects
                ->whereIn('id', $projectIds)
                ->flatMap(function ($project) use ($contact) {
                    $signedIds = $this->signatures->signedFolderIds(
                        $project,
                        $this->signatures->signatureUser($contact)->id
                    );

                    return $this->signatures->visibleDocuments($project)
                        ->filter(fn ($folder) =>
                            $folder->requires_client_signature &&
                            ! $signedIds->contains((int) $folder->id)
                        )
                        ->pluck('id');
                })
                ->sort()
                ->values()
            : collect();

        if ($invoiceIds->isEmpty() && $signatureIds->isEmpty()) {
            ClientAttentionReminder::query()
                ->where('client_contact_id', $contact->id)
                ->update([
                    'action_fingerprint' => null,
                    'action_keys' => null,
                    'last_notified_at' => null,
                ]);

            return false;
        }

        $actionKeys = $invoiceIds
            ->map(fn ($id) => 'invoice:'.$id)
            ->concat($signatureIds->map(fn ($id) => 'signature:'.$id))
            ->sort()
            ->values()
            ->all();
        $fingerprint = hash('sha256', json_encode($actionKeys, JSON_THROW_ON_ERROR));

        $shouldNotify = DB::transaction(function () use ($company, $contact, $fingerprint, $actionKeys): bool {
            ClientContact::query()
                ->whereKey($contact->id)
                ->lockForUpdate()
                ->firstOrFail();

            $state = ClientAttentionReminder::query()
                ->where('client_contact_id', $contact->id)
                ->lockForUpdate()
                ->first();

            $reminderDue = ! $state?->last_notified_at || $state->last_notified_at->lte(
                now()->subDays((int) config('billing.client_attention.reminder_days', 3))
            );
            $hasNewAction = ! $state || collect($actionKeys)
                ->diff($state->action_keys ?? [])
                ->isNotEmpty();

            $shouldNotify = $hasNewAction || $reminderDue;

            ClientAttentionReminder::query()->updateOrCreate(
                ['client_contact_id' => $contact->id],
                [
                    'company_id' => $company->id,
                    'action_fingerprint' => $fingerprint,
                    'action_keys' => $actionKeys,
                    'last_notified_at' => $shouldNotify
                        ? now()
                        : $state?->last_notified_at,
                ]
            );

            return $shouldNotify;
        });

        if ($shouldNotify) {
            $contact->notify(new ClientAttentionRequiredNotification(
                $invoiceIds->count(),
                $signatureIds->count()
            ));
        }

        return $shouldNotify;
    }
}