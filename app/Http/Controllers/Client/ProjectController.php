<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ClientPortalViewData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function show(
        Request $request,
        Project $project,
        ClientPortalViewData $viewData
    ): View {
        $contact = $request->user('client');

        $this->authorize('view', $project);

        $project->load([
            'company',

            /*
             * Service Product
             */
            'serviceProduct.services',

            /*
             * Root-level client files
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
                ->where('visibility', 'client')
                ->whereNull('project_folder_id')
                ->latest(),

            /*
             * Project folders / documents
             */
            'folders' => fn ($query) =>
                $query->orderBy('sort_order'),

            /*
             * Client's own tickets
             */
            'tickets' => fn ($query) =>
                $query
                    ->where(
                        'created_by_client_contact_id',
                        $contact->id
                    )
                    ->latest(),
        ]);

        return view('apps.client', [
            'clientPage' => $viewData->project(
                $request,
                $contact,
                $project
            ),
        ]);
    }
}