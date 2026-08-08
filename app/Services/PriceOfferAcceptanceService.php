<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\PriceOffer;
use App\Models\PriceOfferAcceptance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PriceOfferAcceptanceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function accept(PriceOffer $offer, ClientContact $contact, Request $request, string $requestIdentifier): PriceOfferAcceptance
    {
        return DB::transaction(function () use ($offer, $contact, $request, $requestIdentifier) {
            $locked = PriceOffer::query()->with(['project.company', 'items', 'acceptance'])->lockForUpdate()->findOrFail($offer->id);
            if ($locked->acceptance) {
                if ($locked->acceptance->client_contact_id === $contact->id
                    && hash_equals($locked->acceptance->request_identifier, $requestIdentifier)) {
                    return $locked->acceptance;
                }
                throw new RuntimeException('This price offer has already been accepted.');
            }
            if (! $contact->can('accept', $locked)) { throw new AuthorizationException(); }
            if (! $locked->content_hash || ! hash_equals($locked->content_hash, hash('sha256', (string) $locked->rendered_content))) {
                throw new RuntimeException('Price offer content integrity check failed.');
            }

            $acceptedAt = now('UTC');
            $pdf = Pdf::loadView('pdf.accepted-price-offer', compact('locked', 'contact', 'acceptedAt'))->output();
            $pdfHash = hash('sha256', $pdf);
            $path = 'client-portal/offers/'.$locked->project_id.'/accepted/'.Str::uuid().'.pdf';
            Storage::disk('local')->put($path, $pdf);
            $acceptance = PriceOfferAcceptance::query()->create([
                'price_offer_id' => $locked->id, 'client_contact_id' => $contact->id,
                'company_id' => $contact->company_id, 'signer_name' => $contact->name,
                'signer_email' => $contact->email, 'signer_position' => $contact->position,
                'accepted_at' => $acceptedAt, 'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(), 'document_content_hash' => $locked->content_hash,
                'pdf_hash' => $pdfHash, 'authentication_method' => 'passwordless_magic_link_session',
                'request_identifier' => $requestIdentifier, 'metadata' => ['timezone' => $request->input('timezone')],
            ]);
            $locked->update(['status' => 'accepted', 'accepted_at' => $acceptedAt, 'final_pdf_path' => $path, 'final_pdf_hash' => $pdfHash]);
            $this->audit->record('price_offer.accepted', $contact, $locked, $contact->company_id, $locked->project_id, [
                'number' => $locked->number, 'version' => $locked->version, 'pdf_hash' => $pdfHash,
            ], $request);

            return $acceptance;
        }, 3);
    }
}