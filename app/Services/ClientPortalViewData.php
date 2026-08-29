<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ClientPortalViewData
{
    public function __construct(
        private readonly ClientDocumentSignatureService $signatures
    ) {
    }

    public function dashboard(
        Request $request,
        ClientContact $contact,
        Collection $projects
    ): array {
        return $this->page(
            $request,
            'dashboard',
            'Projects',
            $contact,
            [
                'company_name' => $contact->company->name,

                'projects' => $projects
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'name' => $project->name,

                        'service_name' =>
                            $project->serviceProduct?->name ?? 'Project',

                        'status' => $project->portal_status,

                        'pending_signatures_count' => (int) (
                            $project->pending_signatures_count ?? 0
                        ),

                        'action_count' => (int) (
                            $project->pending_signatures_count ?? 0
                        ),

                        'url' => route(
                            'client.projects.show',
                            $project
                        ),
                    ])
                    ->values(),
            ]
        );
    }

    public function project(
        Request $request,
        ClientContact $contact,
        Project $project
    ): array {
        $signatureUserId = $this->signatures
            ->signatureUser($contact)
            ->id;

        $documents = $this->signatures
            ->visibleDocuments($project);

        $signedFolderIds = $this->signatures
            ->signedFolderIds(
                $project,
                $signatureUserId
            );

        $documentPayload = $documents
            ->map(
                function (ProjectFolder $document) use (
                    $project,
                    $contact,
                    $signedFolderIds
                ) {
                    $isSigned = $signedFolderIds->contains(
                        (int) $document->id
                    );

                    return [
                        'id' => $document->id,
                        'name' => $document->name,
                        'content' => $document->content,

                        'requires_signature' => (bool) (
                            $document->requires_client_signature
                        ),

                        'signed' => $isSigned,

                        'can_sign' =>
                            (bool) $contact->can_accept_documents &&
                            (bool) $document->requires_client_signature &&
                            ! $isSigned,

                        'sign_url' => route(
                            'client.projects.documents.sign',
                            [$project, $document]
                        ),

                        'open_url' => $this->projectDocumentOpenUrl(
                            $project,
                            $document
                        ),
                    ];
                }
            )
            ->values();

        $todoSignatures = $documentPayload
            ->filter(
                fn ($document) =>
                    $document['requires_signature'] &&
                    ! $document['signed']
            )
            ->values();

        $visibleStructure = $this->visibleStructure(
            $project,
            $contact,
            $signedFolderIds
        );

        return $this->page(
            $request,
            'project',
            $project->name,
            $contact,
            [
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,

                    /*
                     * Service Product
                     */
                    'service_product' => $project->serviceProduct
                        ? [
                            'id' => $project->serviceProduct->id,
                            'name' => $project->serviceProduct->name,
                            'description' =>
                                $project->serviceProduct->description,

                            'services' => $project
                                ->serviceProduct
                                ->services
                                ->map(fn ($service) => [
                                    'id' => $service->id,
                                    'name' => $service->name,
                                    'description' =>
                                        $service->description,
                                ])
                                ->values(),
                        ]
                        : null,

                    'status' => $project->portal_status,

                    /*
                     * Tickets
                     */
                    'ticket_url' => route(
                        'client.tickets.store',
                        $project
                    ),

                    /*
                     * Signatures
                     */
                    'pending_signatures_count' =>
                        $todoSignatures->count(),

                    /*
                     * Project Files
                     */
                    'files' => $project->files
                        ->map(fn (ProjectFile $file) => [
                            'id' => $file->id,
                            'display_name' => $file->display_name,
                            'size' => $file->size,

                            'url' => route(
                                'client.files.download',
                                $file
                            ),
                        ])
                        ->values(),

                    /*
                     * Project Documents
                     */
                    'document_structure' =>
                        $visibleStructure,

                    'documents' =>
                        $documentPayload,

                    'todo_signatures' =>
                        $todoSignatures,

                    /*
                     * Project Tickets
                     */
                    'tickets' => $project->tickets
                        ->map(fn ($ticket) => [
                            'id' => $ticket->id,
                            'title' => $ticket->title,
                            'description' => $ticket->description,
                            'priority' => $ticket->priority,
                            'status' => $ticket->status,
                        ])
                        ->values(),
                ],
            ]
        );
    }

    private function page(
        Request $request,
        string $page,
        string $title,
        ClientContact $contact,
        array $data
    ): array {
        return [
            'page' => $page,
            'title' => $title,

            'status' => $request
                ->session()
                ->get('status'),

            'error' => $request
                ->session()
                ->get('errors')
                ?->first(),

            'contact' => [
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'company_name' => $contact->company->name,

                'can_accept_documents' =>
                    (bool) $contact->can_accept_documents,
            ],

            'urls' => [
                'dashboard' => route(
                    'client.dashboard'
                ),

                'logout' => route(
                    'client.logout'
                ),
            ],

            ...$data,
        ];
    }

    private function visibleStructure(
        Project $project,
        ClientContact $contact,
        Collection $signedFolderIds
    ): Collection {
        $folders = $project->relationLoaded('folders')
            ? $project->folders
            : $project->folders()->get();

        $files = $project->relationLoaded('files')
            ? $project->files
            : $project->files()->get();

        $visibleFolders = $folders
            ->filter(
                fn (ProjectFolder $folder) =>
                    $folder->isEffectivelyClientVisible()
            )
            ->keyBy(
                fn (ProjectFolder $folder) =>
                    (int) $folder->id
            );

        /*
         * Normal folders
         */
        $folderItems = $visibleFolders
            ->values()
            ->filter(
                fn (ProjectFolder $folder) =>
                    $folder->type === 'folder'
            )
            ->map(fn (ProjectFolder $folder) => [
                'id' => $folder->id,
                'parent_id' => $folder->parent_id,
                'type' => 'folder',
                'name' => $folder->name,
                'resource_type' => 'folder',
            ]);

        /*
         * Documents
         */
        $documentItems = $visibleFolders
            ->values()
            ->filter(
                fn (ProjectFolder $folder) =>
                    $folder->type === 'file'
            )
            ->filter(
                fn (ProjectFolder $folder) =>
                    $folder->resource_type === 'document'
            )
            ->map(
                function (ProjectFolder $folder) use (
                    $project,
                    $contact,
                    $signedFolderIds
                ) {
                    $signed = $signedFolderIds->contains(
                        (int) $folder->id
                    );

                    return [
                        'id' => $folder->id,
                        'parent_id' => $folder->parent_id,
                        'type' => 'file',
                        'name' => $folder->name,
                        'resource_type' => 'document',
                        'content' => $folder->content,

                        'requires_client_signature' =>
                            (bool) $folder->requires_client_signature,

                        'requires_signature' =>
                            (bool) $folder->requires_client_signature,

                        'signed' => $signed,

                        'can_sign' =>
                            (bool) $contact->can_accept_documents &&
                            (bool) $folder->requires_client_signature &&
                            ! $signed,

                        'sign_url' => route(
                            'client.projects.documents.sign',
                            [$project, $folder]
                        ),

                        'open_url' =>
                            $this->projectDocumentOpenUrl(
                                $project,
                                $folder
                            ),

                        'requirement_level' =>
                            $folder->requirement_level,
                    ];
                }
            );

        /*
         * Uploaded files
         */
        $binaryFiles = $files
            ->filter(
                fn (ProjectFile $file) =>
                    $file->isEffectivelyClientVisible()
            )
            ->filter(
                function (ProjectFile $file) use (
                    $visibleFolders
                ) {
                    if (! $file->project_folder_id) {
                        return true;
                    }

                    return $visibleFolders->has(
                        (int) $file->project_folder_id
                    );
                }
            )
            ->map(fn (ProjectFile $file) => [
                'id' => 'project-file-' . $file->id,
                'parent_id' => $file->project_folder_id,
                'type' => 'file',
                'name' => $file->display_name,
                'resource_type' => 'file',
                'size' => $file->size,
                'mime_type' => $file->mime_type,

                'open_url' => route(
                    'client.files.open',
                    $file
                ),

                'download_url' => route(
                    'client.files.download',
                    $file
                ),
            ]);

        return $folderItems
            ->concat($documentItems)
            ->concat($binaryFiles)
            ->values();
    }

    private function projectDocumentOpenUrl(
        Project $project,
        ProjectFolder $document
    ): string {
        return route(
            'client.projects.show',
            $project
        )
            . '?document=' . $document->id
            . '#client-project-documents';
    }
}