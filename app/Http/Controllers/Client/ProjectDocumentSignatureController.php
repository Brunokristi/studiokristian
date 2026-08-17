<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFolder;
use App\Models\ProjectFolderSignature;
use App\Services\AuditLogger;
use App\Services\ClientDocumentSignatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProjectDocumentSignatureController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        ProjectFolder $folder,
        ClientDocumentSignatureService $signatures,
        AuditLogger $audit
    ): RedirectResponse {
        $contact = $request->user('client');

        abort_unless($contact?->projects()->whereKey($project->id)->exists(), 403);
        abort_unless($folder->project_id === $project->id, 404);
        abort_unless($folder->type === 'file' && $folder->resource_type === 'document', 422);
        abort_unless($folder->isEffectivelyClientVisible(), 403);
        abort_unless((bool) $folder->requires_client_signature, 422);
        abort_unless((bool) $contact?->can_accept_documents, 403);

        if (! Schema::hasTable('project_folder_signatures')) {
            return back()->withErrors(['signature' => 'Document signing is temporarily unavailable.']);
        }

        $signatureUser = $signatures->signatureUser($contact);

        ProjectFolderSignature::query()->updateOrCreate(
            [
                'project_folder_id' => $folder->id,
                'user_id' => $signatureUser->id,
            ],
            [
                'project_id' => $project->id,
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'client_contact_id' => $contact->id,
                    'client_contact_email' => $contact->email,
                ],
            ]
        );

        $audit->record(
            'project.document.signed',
            $contact,
            $folder,
            $project->company_id,
            $project->id,
            ['project_folder_id' => $folder->id],
            $request
        );

        return back()->with('status', 'Document signed successfully.');
    }
}
