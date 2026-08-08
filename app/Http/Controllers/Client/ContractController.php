<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ContractInstance;
use App\Services\AuditLogger;
use App\Services\ContractAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function show(Request $request, ContractInstance $contract, AuditLogger $audit): View
    {
        $this->authorize('view', $contract);

        if ($contract->status === 'sent') {
            $contract->update(['status' => 'viewed', 'viewed_at' => now()]);
            $audit->record('contract.viewed', $request->user(), $contract, $contract->project->company_id, $contract->project_id, request: $request);
        }

        return view('client.contracts.show', [
            'contract' => $contract->fresh()->load(['project.company', 'acceptance']),
        ]);
    }

    public function accept(Request $request, ContractInstance $contract, ContractAcceptanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'read_and_agreed' => ['accepted'],
            'authorized_to_act' => ['accepted'],
            'request_identifier' => ['required', 'uuid'],
            'timezone' => ['nullable', 'timezone'],
        ]);
        $this->authorize('view', $contract);
        abort_unless($request->user()->can_accept_documents, 403);
        $service->accept($contract, $request->user(), $request, $data['request_identifier']);

        return redirect()->route('client.contracts.show', $contract)->with('status', 'Zmluva bola uzatvorená.');
    }

    public function download(Request $request, ContractInstance $contract): StreamedResponse
    {
        $this->authorize('view', $contract);
        $path = $contract->status === 'accepted' ? $contract->final_pdf_path : $contract->generated_pdf_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            str($contract->title)->slug().'-v'.$contract->version.'.pdf',
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'],
        );
    }
}