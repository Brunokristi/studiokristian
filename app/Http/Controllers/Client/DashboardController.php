<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectInvoice;
use App\Services\ClientDocumentSignatureService;
use App\Services\ClientPortalViewData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        ClientPortalViewData $viewData,
        ClientDocumentSignatureService $signatures
    ): View {
        $contact = $request->user('client');

        $projects = Project::query()
            ->where('company_id', $contact->company_id)
            ->with([
                'serviceProduct',
            ])
            ->get();

        $unpaidInvoiceCounts = ProjectInvoice::query()
            ->where('company_id', $contact->company_id)
            ->whereIn('project_id', $projects->pluck('id'))
            ->where('status', ProjectInvoice::STATUS_OPEN)
            ->where('amount_due', '>', 0)
            ->selectRaw('project_id, count(*) as aggregate')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id');

        $projects->each(function ($project) use ($contact, $signatures, $unpaidInvoiceCounts) {
            $project->pending_signatures_count =
                $signatures->pendingSignatureCount(
                    $project,
                    $contact
                );
            $project->unpaid_invoices_count = (int) ($unpaidInvoiceCounts[$project->id] ?? 0);
        });

        return view('apps.client', [
            'clientPage' => $viewData->dashboard(
                $request,
                $contact,
                $projects
            ),
        ]);
    }
}