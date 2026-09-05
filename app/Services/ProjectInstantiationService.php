<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Project;
use App\Models\ServiceProduct;
use App\Models\ServiceProductTemplateFolder;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProjectInstantiationService
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    public function create(
        array $data,
        User $actor
    ): Project {
        $company =
            Company::query()
                ->findOrFail(
                    $data['company_id']
                );

        $product =
            ServiceProduct::query()
                ->where(
                    'active',
                    true
                )
                ->findOrFail(
                    $data['service_product_id']
                );

        return DB::transaction(
            function () use (
                $data,
                $actor,
                $company,
                $product
            ) {
                $url =
                    ($data['url'] ?? null)
                    ?: $this->uniqueSlug(
                        (string) (
                            $data['name'] ?? ''
                        )
                    );

                $name =
                    (string) (
                        $data['name'] ?? ''
                    );

                $summary =
                    (string) (
                        $data['summary'] ?? ''
                    );

                $project =
                    Project::query()->create([
                        ...collect($data)
                            ->only([
                                'name',
                                'project_code',
                                'summary',
                                'internal_notes',
                                'portal_status',
                                'started_at',
                                'completed_at',
                                'live_url',
                                'hex_color',
                                'logo_path',
                                'is_saas',
                            ])
                            ->all(),

                        'url' =>
                            $url,

                        'name_translations' =>
                            [
                                'en' =>
                                    $name,
                            ],

                        'summary_translations' =>
                            $summary !== ''
                                ? [
                                    'en' =>
                                        $summary,
                                ]
                                : null,

                        'is_published' =>
                            false,

                        'is_saas' =>
                            (bool) ($data['is_saas'] ?? false),

                        'company_id' =>
                            $company->id,

                        'service_product_id' =>
                            $product->id,
                    ]);

                $this->copyTemplateStructure(
                    $product,
                    $project,
                    $actor
                );

                $contactIds =
                    collect(
                        $data['contact_ids'] ?? []
                    )
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->filter(
                            fn ($id) =>
                                $id > 0
                        )
                        ->unique()
                        ->values()
                        ->all();

                $coworkerIds =
                    collect(
                        $data['coworker_ids'] ?? []
                    )
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->filter(
                            fn ($id) =>
                                $id > 0
                        )
                        ->unique()
                        ->values()
                        ->all();

                if (
                    $actor->is_admin
                ) {
                    $coworkerIds =
                        collect(
                            $coworkerIds
                        )
                            ->push(
                                $actor->id
                            )
                            ->unique()
                            ->values()
                            ->all();
                }

                $project->contacts()->sync(
                    $contactIds
                );

                if (
                    User::hasProjectUserAccessTypeColumn()
                ) {
                    $project
                        ->coworkers()
                        ->sync(
                            $this->syncCoworkerMap(
                                $coworkerIds
                            )
                        );
                } else {
                    $project
                        ->coworkers()
                        ->sync(
                            $coworkerIds
                        );
                }

                if (
                    ! empty($contactIds)
                ) {
                    ClientContact::query()
                        ->whereIn(
                            'id',
                            $contactIds
                        )
                        ->update([
                            'active' =>
                                true,

                            'can_access_portal' =>
                                true,

                            'access_revoked_at' =>
                                null,
                        ]);
                }

                DB::afterCommit(
                    function () use (
                        $project,
                        $contactIds,
                        $coworkerIds
                    ) {
                        if (
                            ! empty(
                                $contactIds
                            )
                        ) {
                            $contacts =
                                ClientContact::query()
                                    ->whereIn(
                                        'id',
                                        $contactIds
                                    )
                                    ->get();

                            foreach (
                                $contacts
                                as $contact
                            ) {
                                $contact->notify(
                                    new ProjectInvitationNotification(
                                        $project,
                                        route(
                                            'client.login'
                                        )
                                    )
                                );
                            }
                        }

                        if (
                            ! empty(
                                $coworkerIds
                            )
                        ) {
                            $coworkers =
                                User::query()
                                    ->whereIn(
                                        'id',
                                        $coworkerIds
                                    )
                                    ->where(
                                        'is_admin',
                                        false
                                    );

                            if (
                                User::hasRoleColumn()
                            ) {
                                $coworkers->where(
                                    'role',
                                    'coworker'
                                );
                            }

                            foreach (
                                $coworkers->get()
                                as $coworker
                            ) {
                                $coworker->notify(
                                    new ProjectInvitationNotification(
                                        $project,
                                        route(
                                            'login'
                                        )
                                    )
                                );
                            }
                        }
                    }
                );

                $this->audit->record(
                    'project_created_from_service_product',
                    $actor,
                    $project,
                    $company->id,
                    $project->id
                );

                return $project->fresh();
            }
        );
    }

    private function copyTemplateStructure(
        ServiceProduct $product,
        Project $project,
        User $actor
    ): void {
        $templates =
            ServiceProductTemplateFolder::query()
                ->where(
                    'service_product_id',
                    $product->id
                )
                ->orderBy(
                    'sort_order'
                )
                ->get();

        $map = [];

        /*
         * We copy parentless records first and then
         * recursively copy their descendants.
         */
        $roots =
            $templates
                ->filter(
                    fn ($template) =>
                        $template->parent_id === null
                )
                ->sortBy(
                    'sort_order'
                );

        foreach (
            $roots as $template
        ) {
            $this->copyTemplateFolder(
                $template,
                $templates,
                $project,
                $actor,
                $map
            );
        }

        /*
         * Defensive validation:
         *
         * If anything was left disconnected from the tree,
         * don't silently create an incomplete project.
         */
        if (
            $templates->isNotEmpty() &&
            count($map) !==
                $templates->count()
        ) {
            throw new InvalidArgumentException(
                'Service Product template folder tree is invalid.'
            );
        }
    }

    private function copyTemplateFolder(
        ServiceProductTemplateFolder $template,
        $templates,
        Project $project,
        User $actor,
        array &$map
    ): void {
        $parentId = null;

        if (
            $template->parent_id !== null
        ) {
            $parentId =
                $map[
                    $template->parent_id
                ] ?? null;

            if (
                $parentId === null
            ) {
                throw new InvalidArgumentException(
                    'Service Product template folder tree is invalid.'
                );
            }
        }

        $folder =
            $project->folders()->create([
                'parent_id' =>
                    $parentId,

                'type' =>
                    $template->type
                    ?: 'folder',

                'name' =>
                    $template->name,

                'resource_type' =>
                    $template->resource_type,

                'requirement_level' =>
                    $template->requirement_level,

                'requires_client_signature' =>
                    $template
                        ->requires_client_signature,

                'template_name' =>
                    $template->template_name,

                'content' =>
                    $template->content,

                'url' =>
                    $template->url,

                'client_visible' =>
                    $template->client_visible,

                'sort_order' =>
                    $template->sort_order,

                'created_by' =>
                    $actor->id,
            ]);

        $map[
            $template->id
        ] = $folder->id;

        $children =
            $templates
                ->filter(
                    fn (
                        ServiceProductTemplateFolder $candidate
                    ) =>
                        (int) (
                            $candidate->parent_id
                            ?? 0
                        ) ===
                        (int) $template->id
                )
                ->sortBy(
                    'sort_order'
                );

        foreach (
            $children as $child
        ) {
            $this->copyTemplateFolder(
                $child,
                $templates,
                $project,
                $actor,
                $map
            );
        }
    }

    private function uniqueSlug(
        string $name
    ): string {
        $base =
            Str::slug($name)
            ?: 'project';

        $slug =
            $base;

        $suffix = 2;

        while (
            Project::query()
                ->where(
                    'url',
                    $slug
                )
                ->exists()
        ) {
            $slug =
                $base
                . '-'
                . $suffix++;
        }

        return $slug;
    }

    private function syncCoworkerMap(
        array $userIds
    ): array {
        $map = [];

        foreach (
            $userIds as $userId
        ) {
            $map[
                (int) $userId
            ] = [
                'access_type' =>
                    'coworker',
            ];
        }

        return $map;
    }
}