<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ClientPortalViewData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function show(Request $request, Project $project, ClientPortalViewData $viewData): View
    {
        $this->authorize('view', $project);
        $project->load([
            'company', 'serviceProduct',
            'contracts' => fn ($query) => $query->whereIn('status', ['sent', 'viewed', 'accepted', 'superseded'])->latest(),
            'priceOffers' => fn ($query) => $query->whereIn('status', ['sent', 'viewed', 'accepted'])->latest(),
            /*
             * Only preload root-level client files.
             *
             * Nested project files are loaded lazily by the client
             * ProjectPage through /client/projects/{project}/files.
             * This keeps the initial HTML/Inertia payload small.
             */
            'files' => fn ($query) => $query
                ->select([
                    'id',
                    'project_id',
                    'project_folder_id',
                    'original_filename',
                    'display_name',
                    'extension',
                    'mime_type',
                    'size',
                    'disk',
                    'visibility',
                    'created_at',
                    'updated_at',
                ])
                ->where(
                    'visibility',
                    'client'
                )
                ->whereNull(
                    'project_folder_id'
                )
                ->latest(),
            'folders' => fn ($query) => $query->orderBy('sort_order'),
            'guides' => fn ($query) => $query->where('client_visible', true)->orderBy('sort_order'),
            'serviceAccounts' => fn ($query) => $query->where('client_visible', true)->with('credential')->orderBy('service_name'),
            'tickets' => fn ($query) => $query->where('created_by_client_contact_id', auth('client')->id())->latest(),
        ]);

        return view('apps.client', [
            'clientPage' => $viewData->project($request, $request->user(), $project),
        ]);
    }
}