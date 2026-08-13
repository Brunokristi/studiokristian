<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientPortalViewData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ClientPortalViewData $viewData): View
    {
        $contact = $request->user();
        $projects = $contact->projects()
            ->where('projects.company_id', $contact->company_id)
            ->whereHas('company', fn ($query) => $query->where('status', 'active'))
            ->whereNull('archived_at')
            ->with('serviceProduct')
            ->withCount([
                'contracts as pending_contracts_count' => fn ($query) => $query->whereIn('status', ['sent', 'viewed']),
                'priceOffers as pending_offers_count' => fn ($query) => $query->whereIn('status', ['sent', 'viewed']),
            ])->get();

        return view('apps.client', [
            'clientPage' => $viewData->dashboard($request, $contact, $projects),
        ]);
    }
}