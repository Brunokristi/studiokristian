<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\ContractInstance;
use App\Models\PriceOffer;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClientPortalViewData
{
    public function dashboard(Request $request, ClientContact $contact, Collection $projects): array
    {
        return $this->page($request, 'dashboard', 'Projects', $contact, [
            'company_name' => $contact->company->name,
            'projects' => $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'service_name' => $project->serviceProduct?->name ?? 'Project',
                'status' => $project->portal_status,
                'action_count' => $project->pending_contracts_count + $project->pending_offers_count,
                'url' => route('client.projects.show', $project),
            ])->values(),
        ]);
    }

    public function project(Request $request, ClientContact $contact, Project $project): array
    {
        return $this->page($request, 'project', $project->name, $contact, [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'service_name' => $project->serviceProduct?->name ?? 'Project',
                'status' => $project->portal_status,
                'ticket_url' => route('client.tickets.store', $project),
                'contracts' => $project->contracts->map(fn ($contract) => [
                    'id' => $contract->id,
                    'title' => $contract->title,
                    'version' => $contract->version,
                    'status' => $contract->status,
                    'accepted_at' => $contract->accepted_at?->toIso8601String(),
                    'url' => route('client.contracts.show', $contract),
                ])->values(),
                'offers' => $project->priceOffers->map(fn ($offer) => [
                    'id' => $offer->id,
                    'number' => $offer->number,
                    'version' => $offer->version,
                    'status' => $offer->status,
                    'total' => $offer->total,
                    'currency' => $offer->currency,
                    'url' => route('client.offers.show', $offer),
                ])->values(),
                'files' => $project->files->map(fn ($file) => [
                    'id' => $file->id,
                    'display_name' => $file->display_name,
                    'size' => $file->size,
                    'url' => route('client.files.download', $file),
                ])->values(),
                'services' => $project->serviceAccounts->map(fn ($account) => [
                    'id' => $account->id,
                    'name' => $account->service_name,
                    'account_owner' => $account->account_owner,
                    'billing_owner' => $account->billing_owner,
                    'renewal_responsibility' => $account->renewal_responsibility,
                    'login_url' => $account->login_url,
                    'access_instructions' => $account->credential?->access_instructions,
                ])->values(),
                'tickets' => $project->tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'description' => $ticket->description,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                ])->values(),
            ],
        ]);
    }

    public function contract(Request $request, ClientContact $contact, ContractInstance $contract): array
    {
        return $this->page($request, 'contract', $contract->title, $contact, [
            'contract' => [
                'id' => $contract->id,
                'title' => $contract->title,
                'version' => $contract->version,
                'status' => $contract->status,
                'rendered_content' => $contract->rendered_content,
                'accepted_at' => $contract->accepted_at?->toIso8601String(),
                'download_url' => route('client.contracts.download', $contract),
                'accept_url' => route('client.contracts.accept', $contract),
                'request_identifier' => (string) Str::uuid(),
                'project' => [
                    'name' => $contract->project->name,
                    'url' => route('client.projects.show', $contract->project),
                    'company_name' => $contract->project->company->name,
                ],
                'acceptance' => $contract->acceptance ? [
                    'signer_name' => $contract->acceptance->signer_name,
                ] : null,
            ],
        ]);
    }

    public function offer(Request $request, ClientContact $contact, PriceOffer $offer): array
    {
        return $this->page($request, 'offer', 'Offer '.$offer->number, $contact, [
            'offer' => [
                'id' => $offer->id,
                'number' => $offer->number,
                'version' => $offer->version,
                'status' => $offer->status,
                'total' => $offer->total,
                'currency' => $offer->currency,
                'accepted_at' => $offer->accepted_at?->toIso8601String(),
                'download_url' => ($offer->pdf_path || $offer->final_pdf_path)
                    ? route('client.offers.download', $offer)
                    : null,
                'accept_url' => route('client.offers.accept', $offer),
                'request_identifier' => (string) Str::uuid(),
                'project' => [
                    'name' => $offer->project->name,
                    'url' => route('client.projects.show', $offer->project),
                    'company_name' => $offer->project->company->name,
                ],
                'items' => $offer->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'total' => $item->total,
                ])->values(),
                'acceptance' => $offer->acceptance ? [
                    'signer_name' => $offer->acceptance->signer_name,
                ] : null,
            ],
        ]);
    }

    private function page(Request $request, string $page, string $title, ClientContact $contact, array $data): array
    {
        return [
            'page' => $page,
            'title' => $title,
            'status' => $request->session()->get('status'),
            'error' => $request->session()->get('errors')?->first(),
            'contact' => [
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'company_name' => $contact->company->name,
                'can_accept_documents' => $contact->can_accept_documents,
            ],
            'urls' => [
                'dashboard' => route('client.dashboard'),
                'logout' => route('client.logout'),
            ],
            ...$data,
        ];
    }
}
