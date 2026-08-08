<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\ContractAcceptance;
use App\Models\ContractInstance;
use App\Notifications\ContractAcceptedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ContractAcceptanceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function accept(ContractInstance $contract, ClientContact $contact, Request $request, string $requestIdentifier): ContractAcceptance
    {
        return DB::transaction(function () use ($contract, $contact, $request, $requestIdentifier) {
            $locked = ContractInstance::query()->with(['project.company', 'acceptance'])->lockForUpdate()->findOrFail($contract->id);

            if ($locked->acceptance) {
                if ($locked->acceptance->client_contact_id === $contact->id
                    && hash_equals($locked->acceptance->request_identifier, $requestIdentifier)) {
                    return $locked->acceptance;
                }

                throw new RuntimeException('This contract has already been accepted.');
            }

            if (! $contact->can('accept', $locked)) {
                throw new AuthorizationException();
            }

            if (! hash_equals($locked->content_hash, hash('sha256', $locked->rendered_content))) {
                throw new RuntimeException('Contract content integrity check failed.');
            }

            $acceptedAt = now('UTC');
            $pdf = Pdf::loadView('pdf.accepted-contract', [
                'contract' => $locked,
                'contact' => $contact,
                'acceptedAt' => $acceptedAt,
            ])->output();
            $pdfHash = hash('sha256', $pdf);
            $path = 'client-portal/contracts/'.$locked->project_id.'/accepted/'.Str::uuid().'.pdf';
            Storage::disk('local')->put($path, $pdf);

            $acceptance = ContractAcceptance::query()->create([
                'contract_instance_id' => $locked->id,
                'client_contact_id' => $contact->id,
                'company_id' => $contact->company_id,
                'signer_name' => $contact->name,
                'signer_email' => $contact->email,
                'signer_position' => $contact->position,
                'accepted_at' => $acceptedAt,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'document_content_hash' => $locked->content_hash,
                'pdf_hash' => $pdfHash,
                'authentication_method' => 'passwordless_magic_link_session',
                'request_identifier' => $requestIdentifier,
                'metadata' => ['timezone' => $request->input('timezone')],
            ]);

            $locked->update([
                'status' => 'accepted',
                'accepted_at' => $acceptedAt,
                'final_pdf_path' => $path,
                'final_pdf_hash' => $pdfHash,
            ]);
            $this->audit->record('contract.accepted', $contact, $locked, $contact->company_id, $locked->project_id, [
                'version' => $locked->version,
                'content_hash' => $locked->content_hash,
                'pdf_hash' => $pdfHash,
            ], $request);

            DB::afterCommit(fn () => $contact->notify(new ContractAcceptedNotification($locked->id)));

            return $acceptance;
        }, 3);
    }
}