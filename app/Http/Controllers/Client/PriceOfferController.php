<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PriceOffer;
use App\Services\PriceOfferAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PriceOfferController extends Controller
{
    public function show(PriceOffer $offer): View
    {
        $this->authorize('view', $offer);
        if ($offer->status === 'sent') { $offer->update(['status' => 'viewed']); }

        return view('client.offers.show', ['offer' => $offer->fresh()->load(['project.company', 'items', 'acceptance'])]);
    }

    public function accept(Request $request, PriceOffer $offer, PriceOfferAcceptanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'read_and_agreed' => ['accepted'], 'authorized_to_act' => ['accepted'],
            'request_identifier' => ['required', 'uuid'], 'timezone' => ['nullable', 'timezone'],
        ]);
        $this->authorize('view', $offer);
        abort_unless($request->user()->can_accept_documents, 403);
        $service->accept($offer, $request->user(), $request, $data['request_identifier']);

        return redirect()->route('client.offers.show', $offer)->with('status', 'Cenová ponuka bola prijatá.');
    }

    public function download(PriceOffer $offer): StreamedResponse
    {
        $this->authorize('view', $offer);
        $path = $offer->status === 'accepted' ? $offer->final_pdf_path : $offer->pdf_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'cenova-ponuka-'.$offer->number.'-v'.$offer->version.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
        ]);
    }
}