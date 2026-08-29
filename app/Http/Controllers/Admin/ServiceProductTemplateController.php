<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceProduct;
use App\Models\ServiceProductTemplateFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceProductTemplateController extends Controller
{
    public function show(
        ServiceProduct $serviceProduct
    ): JsonResponse {
        $folders =
            ServiceProductTemplateFolder::query()
                ->where(
                    'service_product_id',
                    $serviceProduct->id
                )
                ->orderBy('sort_order')
                ->get();

        return response()->json([
            'service_product' => [
                'id' =>
                    $serviceProduct->id,

                'name' =>
                    $serviceProduct->name,
            ],

            'folders' =>
                $this->buildTree(
                    $folders
                ),
        ]);
    }

    public function update(
        Request $request,
        ServiceProduct $serviceProduct
    ): JsonResponse {
        $validated =
            $request->validate([
                'folders' => [
                    'nullable',
                    'array',
                ],

                'folders.*.id' => [
                    'nullable',
                    'integer',
                ],

                'folders.*.client_key' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'folders.*.parent_id' => [
                    'nullable',
                    'integer',
                ],

                'folders.*.parent_client_key' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'folders.*.type' => [
                    'nullable',
                    'string',
                    'max:32',
                ],

                'folders.*.name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'folders.*.resource_type' => [
                    'nullable',
                    'string',
                    'max:64',
                ],

                'folders.*.requirement_level' => [
                    'nullable',
                    'string',
                    'max:64',
                ],

                'folders.*.requires_client_signature' => [
                    'nullable',
                    'boolean',
                ],

                'folders.*.template_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'folders.*.content' => [
                    'nullable',
                    'string',
                ],

                'folders.*.document_revision' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],

                'folders.*.url' => [
                    'nullable',
                    'string',
                    'max:2048',
                ],

                'folders.*.client_visible' => [
                    'nullable',
                    'boolean',
                ],

                'folders.*.sort_order' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],
            ]);

        $folders =
            $validated['folders'] ?? [];

        $savedFolders =
            DB::transaction(
                function () use (
                    $serviceProduct,
                    $folders,
                    $request
                ) {
                    $existing =
                        ServiceProductTemplateFolder::query()
                            ->where(
                                'service_product_id',
                                $serviceProduct->id
                            )
                            ->get()
                            ->keyBy('id');

                    $existingByClientKey =
                        ServiceProductTemplateFolder::query()
                            ->where(
                                'service_product_id',
                                $serviceProduct->id
                            )
                            ->get()
                            ->keyBy('client_key');

                    $resolved = [];

                    /*
                     * First pass:
                     *
                     * Create/update every node without
                     * assigning its parent.
                     */
                    foreach (
                        $folders as $index => $item
                    ) {
                        $clientKey =
                            trim(
                                (string) (
                                    $item['client_key']
                                    ?? ''
                                )
                            );

                        if (
                            $clientKey === ''
                        ) {
                            $clientKey =
                                (string) Str::uuid();
                        }

                        $folder = null;

                        if (
                            ! empty(
                                $item['id']
                            )
                        ) {
                            $id =
                                (int) $item['id'];

                            $folder =
                                $existing->get(
                                    $id
                                );
                        }

                        if (
                            ! $folder
                        ) {
                            $folder =
                                $existingByClientKey->get(
                                    $clientKey
                                );
                        }

                        if (
                            ! $folder
                        ) {
                            $folder =
                                new ServiceProductTemplateFolder();

                            $folder->service_product_id =
                                $serviceProduct->id;

                            $folder->client_key =
                                $clientKey;

                            $folder->document_revision =
                                0;
                        }

                        $folder->fill([
                            'client_key' =>
                                $clientKey,

                            'type' =>
                                $item['type']
                                ?? 'folder',

                            'name' =>
                                trim(
                                    (string) (
                                        $item['name']
                                        ?? 'Untitled'
                                    )
                                ),

                            'resource_type' =>
                                $item[
                                    'resource_type'
                                ] ?? null,

                            'requirement_level' =>
                                $item[
                                    'requirement_level'
                                ] ?? null,

                            'requires_client_signature' =>
                                (bool) (
                                    $item[
                                        'requires_client_signature'
                                    ] ?? false
                                ),

                            'template_name' =>
                                $item[
                                    'template_name'
                                ] ?? null,

                            'content' =>
                                $item[
                                    'content'
                                ] ?? null,

                            'url' =>
                                $this->normalizeUrl(
                                    $item['url'] ?? null
                                ),

                            'client_visible' =>
                                (bool) (
                                    $item[
                                        'client_visible'
                                    ] ?? true
                                ),

                            'sort_order' =>
                                (int) (
                                    $item[
                                        'sort_order'
                                    ] ?? $index
                                ),
                        ]);

                        if (
                            isset(
                                $item[
                                    'document_revision'
                                ]
                            )
                        ) {
                            $folder->document_revision =
                                max(
                                    (int) (
                                        $item[
                                            'document_revision'
                                        ]
                                    ),
                                    (int) (
                                        $folder->document_revision
                                        ?? 0
                                    )
                                );
                        }

                        $folder->save();

                        $resolved[
                            $clientKey
                        ] = $folder;

                        $existingByClientKey[
                            $clientKey
                        ] = $folder;
                    }

                    /*
                     * Second pass:
                     *
                     * Resolve parents using client_key.
                     * This works even when a child was created
                     * before its parent in the frontend array.
                     */
                    foreach (
                        $folders as $item
                    ) {
                        $clientKey =
                            (string) (
                                $item['client_key']
                                ?? ''
                            );

                        $folder =
                            $resolved[
                                $clientKey
                            ] ?? null;

                        if (
                            ! $folder
                        ) {
                            continue;
                        }

                        $parentId = null;

                        $parentClientKey =
                            $item[
                                'parent_client_key'
                            ] ?? null;

                        if (
                            $parentClientKey !== null &&
                            $parentClientKey !== ''
                        ) {
                            $parent =
                                $resolved[
                                    (string) (
                                        $parentClientKey
                                    )
                                ] ?? null;

                            if (
                                $parent &&
                                $parent->id !==
                                    $folder->id
                            ) {
                                $parentId =
                                    $parent->id;
                            }
                        }

                        /*
                         * Fallback for persisted records coming
                         * from older frontend payloads.
                         */
                        if (
                            $parentId === null &&
                            ! empty(
                                $item['parent_id']
                            )
                        ) {
                            $candidate =
                                $existing->get(
                                    (int) (
                                        $item[
                                            'parent_id'
                                        ]
                                    )
                                );

                            if (
                                $candidate &&
                                $candidate->id !==
                                    $folder->id
                            ) {
                                $parentId =
                                    $candidate->id;
                            }
                        }

                        $folder->parent_id =
                            $parentId;

                        $folder->save();
                    }

                    /*
                     * Delete nodes which disappeared from
                     * the editor.
                     */
                    $keptIds =
                        collect(
                            $resolved
                        )
                            ->map(
                                fn ($folder) =>
                                    $folder->id
                            )
                            ->filter()
                            ->unique()
                            ->values();

                    $removed =
                        $existing
                            ->except(
                                $keptIds->all()
                            );

                    foreach (
                        $removed as $folder
                    ) {
                        if (
                            $folder->storage_path
                        ) {
                            Storage::disk(
                                $folder->disk
                                ?: 'local'
                            )->delete(
                                $folder->storage_path
                            );
                        }
                    }

                    if (
                        $removed->isNotEmpty()
                    ) {
                        ServiceProductTemplateFolder::query()
                            ->where(
                                'service_product_id',
                                $serviceProduct->id
                            )
                            ->whereIn(
                                'id',
                                $removed->keys()
                                    ->all()
                            )
                            ->delete();
                    }

                    return ServiceProductTemplateFolder::query()
                        ->where(
                            'service_product_id',
                            $serviceProduct->id
                        )
                        ->orderBy('sort_order')
                        ->get();
                }
            );

        return response()->json([
            'folders' =>
                $this->buildTree(
                    $savedFolders
                ),
        ]);
    }

    public function upload(
        Request $request,
        ServiceProduct $serviceProduct
    ): JsonResponse {
        $request->validate([
            'files' => [
                'required',
                'array',
                'max:50',
            ],

            'files.*' => [
                'required',
                'file',
                'max:51200',
            ],

            'folder_id' => [
                'nullable',
                'integer',
            ],

            'parent_client_key' => [
                'nullable',
                'string',
                'max:255',
            ],

            'relative_paths' => [
                'nullable',
                'array',
            ],

            'relative_paths.*' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'client_visible' => [
                'nullable',
                'boolean',
            ],
        ]);

        $parent =
            $this->resolveParent(
                $request,
                $serviceProduct
            );

        $created = [];

        foreach (
            $request->file('files', [])
            as $index => $upload
        ) {
            $relativePath =
                (string) (
                    $request->input(
                        "relative_paths.{$index}"
                    )
                    ?: $upload->getClientOriginalName()
                );

            $relativePath =
                trim(
                    str_replace(
                        '\\',
                        '/',
                        $relativePath
                    ),
                    '/'
                );

            $segments =
                array_values(
                    array_filter(
                        explode(
                            '/',
                            $relativePath
                        ),
                        fn ($segment) =>
                            $segment !== '' &&
                            $segment !== '.' &&
                            $segment !== '..'
                    )
                );

            if (
                empty($segments)
            ) {
                continue;
            }

            $filename =
                array_pop(
                    $segments
                );

            $currentParent =
                $parent;

            foreach (
                $segments as $segment
            ) {
                $currentParent =
                    $this->findOrCreateFolder(
                        $serviceProduct,
                        $currentParent,
                        $segment,
                        $request->user()->id
                    );
            }

            $disk =
                'local';

            $directory =
                'service-products/'
                . $serviceProduct->id
                . '/templates';

            $storedPath =
                $upload->store(
                    $directory,
                    $disk
                );

            $mimeType =
                $upload->getMimeType()
                ?: 'application/octet-stream';

            $extension =
                strtolower(
                    $upload->getClientOriginalExtension()
                );

            $checksum =
                hash_file(
                    'sha256',
                    $upload->getRealPath()
                );

            $record =
                ServiceProductTemplateFolder::query()
                    ->create([
                        'service_product_id' =>
                            $serviceProduct->id,

                        'parent_id' =>
                            $currentParent?->id,

                        'client_key' =>
                            (string) Str::uuid(),

                        'type' =>
                            'file',

                        'name' =>
                            $filename,

                        'original_filename' =>
                            $filename,

                        'extension' =>
                            $extension,

                        'resource_type' =>
                            $this->resourceTypeForMime(
                                $mimeType
                            ),

                        'disk' =>
                            $disk,

                        'storage_path' =>
                            $storedPath,

                        'mime_type' =>
                            $mimeType,

                        'size' =>
                            (int) (
                                $upload->getSize()
                                ?? 0
                            ),

                        'checksum' =>
                            $checksum,

                        'uploaded_by' =>
                            $request->user()->id,

                        'client_visible' =>
                            $request->boolean(
                                'client_visible',
                                true
                            ),

                        'sort_order' =>
                            $this->nextSortOrder(
                                $serviceProduct,
                                $currentParent?->id
                            ),
                    ]);

            $created[] =
                $this->payload(
                    $record
                );
        }

        return response()->json([
            'data' =>
                $created,
        ], 201);
    }

    public function open(
        ServiceProduct $serviceProduct,
        ServiceProductTemplateFolder $folder
    ): StreamedResponse {
        $this->assertOwnership(
            $serviceProduct,
            $folder
        );

        if (
            $folder->isLink()
        ) {
            abort_unless(
                filled($folder->url),
                404
            );

            return redirect()->away(
                $folder->url
            );
        }

        abort_unless(
            filled(
                $folder->storage_path
            ),
            404
        );

        $disk =
            Storage::disk(
                $folder->disk ?: 'local'
            );

        abort_unless(
            $disk->exists(
                $folder->storage_path
            ),
            404
        );

        return $disk->response(
            $folder->storage_path,
            $folder->original_filename
                ?: $folder->name,
            [
                'Content-Type' =>
                    $folder->mime_type
                    ?: 'application/octet-stream',

                'Content-Disposition' =>
                    $this->shouldInline(
                        $folder
                    )
                        ? 'inline'
                        : 'attachment',

                'Cache-Control' =>
                    'private, no-store',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    public function download(
        ServiceProduct $serviceProduct,
        ServiceProductTemplateFolder $folder
    ): StreamedResponse {
        $this->assertOwnership(
            $serviceProduct,
            $folder
        );

        abort_unless(
            filled(
                $folder->storage_path
            ),
            404
        );

        $disk =
            Storage::disk(
                $folder->disk ?: 'local'
            );

        abort_unless(
            $disk->exists(
                $folder->storage_path
            ),
            404
        );

        return $disk->download(
            $folder->storage_path,
            $folder->original_filename
                ?: $folder->name
        );
    }

    public function document(
        Request $request,
        ServiceProduct $serviceProduct,
        ServiceProductTemplateFolder $folder
    ): JsonResponse {
        $this->assertOwnership(
            $serviceProduct,
            $folder
        );

        abort_unless(
            $folder->isDocument(),
            422,
            'This template entry is not a document.'
        );

        $data =
            $request->validate([
                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'subtitle' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'document_schema' => [
                    'required',
                    'array',
                ],

                'revision' => [
                    'required',
                    'integer',
                    'min:0',
                ],
            ]);

        $incomingRevision =
            (int) $data['revision'];

        $currentRevision =
            (int) (
                $folder->document_revision
                ?? 0
            );

        if (
            $incomingRevision <
            $currentRevision
        ) {
            return response()->json([
                'message' =>
                    'The document has changed on the server.',

                'expected_revision' =>
                    $currentRevision + 1,

                'saved_revision' =>
                    $currentRevision,
            ], 409);
        }

        $nextRevision =
            $currentRevision + 1;

        $content =
            json_encode(
                [
                    'title' =>
                        trim(
                            $data['title']
                        ),

                    'subtitle' =>
                        trim(
                            (string) (
                                $data[
                                    'subtitle'
                                ] ?? ''
                            )
                        ),

                    'doc' =>
                        $data[
                            'document_schema'
                        ],
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            );

        $folder->update([
            'name' =>
                trim(
                    $data['title']
                ),

            'template_name' =>
                trim(
                    $data['title']
                ),

            'content' =>
                $content,

            'document_revision' =>
                $nextRevision,
        ]);

        return response()->json([
            'id' =>
                $folder->id,

            'title' =>
                $folder->name,

            'subtitle' =>
                $data['subtitle'] ?? '',

            'content' =>
                $content,

            'document_schema' =>
                $data['document_schema'],

            'saved_revision' =>
                $nextRevision,
        ]);
    }

    private function buildTree(
        $folders
    ): array {
        $items =
            $folders
                ->map(
                    fn ($folder) =>
                        $this->payload(
                            $folder
                        )
                )
                ->keyBy('id');

        $tree = [];

        foreach (
            $folders as $folder
        ) {
            if (
                $folder->parent_id !== null &&
                isset(
                    $items[
                        $folder->parent_id
                    ]
                )
            ) {
                continue;
            }

            $tree[] =
                $this->buildNode(
                    $folder,
                    $folders
                );
        }

        usort(
            $tree,
            fn ($a, $b) =>
                $a['sort_order']
                <=> $b['sort_order']
        );

        return $tree;
    }

    private function buildNode(
        ServiceProductTemplateFolder $folder,
        $folders
    ): array {
        $payload =
            $this->payload(
                $folder
            );

        $children =
            $folders
                ->filter(
                    fn ($candidate) =>
                        (int) (
                            $candidate->parent_id
                            ?? 0
                        ) ===
                        (int) $folder->id
                )
                ->sortBy('sort_order')
                ->values();

        $payload['children'] =
            $children
                ->map(
                    fn ($child) =>
                        $this->buildNode(
                            $child,
                            $folders
                        )
                )
                ->all();

        return $payload;
    }

    private function payload(
        ServiceProductTemplateFolder $folder
    ): array {
        return [
            'id' =>
                $folder->id,

            'client_key' =>
                $folder->client_key,

            'parent_id' =>
                $folder->parent_id,

            'parent_client_key' =>
                $folder->parent_id
                    ? $folder->parent?->client_key
                    : null,

            'type' =>
                $folder->type
                ?: 'folder',

            'name' =>
                $folder->name,

            'original_filename' =>
                $folder->original_filename,

            'extension' =>
                $folder->extension,

            'resource_type' =>
                $folder->resource_type,

            'requirement_level' =>
                $folder->requirement_level,

            'requires_client_signature' =>
                (bool) (
                    $folder->requires_client_signature
                ),

            'template_name' =>
                $folder->template_name,

            'content' =>
                $folder->content,

            'document_revision' =>
                (int) (
                    $folder->document_revision
                    ?? 0
                ),

            'url' =>
                $folder->url,

            'disk' =>
                $folder->disk,

            'storage_path' =>
                $folder->storage_path,

            'mime_type' =>
                $folder->mime_type,

            'size' =>
                $folder->size,

            'client_visible' =>
                (bool) (
                    $folder->client_visible
                ),

            'sort_order' =>
                (int) (
                    $folder->sort_order
                ),

            'children' =>
                [],

            'open_url' =>
                route(
                    'admin.client-portal.api.service-products.template.open',
                    [
                        'serviceProduct' =>
                            $folder->service_product_id,

                        'folder' =>
                            $folder->id,
                    ]
                ),

            'download_url' =>
                route(
                    'admin.client-portal.api.service-products.template.download',
                    [
                        'serviceProduct' =>
                            $folder->service_product_id,

                        'folder' =>
                            $folder->id,
                    ]
                ),
        ];
    }

    private function resolveParent(
        Request $request,
        ServiceProduct $serviceProduct
    ): ?ServiceProductTemplateFolder {
        if (
            $request->filled(
                'parent_client_key'
            )
        ) {
            return ServiceProductTemplateFolder::query()
                ->where(
                    'service_product_id',
                    $serviceProduct->id
                )
                ->where(
                    'client_key',
                    $request->string(
                        'parent_client_key'
                    )->toString()
                )
                ->where(
                    'type',
                    'folder'
                )
                ->first();
        }

        if (
            $request->filled(
                'folder_id'
            )
        ) {
            return ServiceProductTemplateFolder::query()
                ->where(
                    'service_product_id',
                    $serviceProduct->id
                )
                ->where(
                    'id',
                    $request->integer(
                        'folder_id'
                    )
                )
                ->where(
                    'type',
                    'folder'
                )
                ->firstOrFail();
        }

        return null;
    }

    private function findOrCreateFolder(
        ServiceProduct $serviceProduct,
        ?ServiceProductTemplateFolder $parent,
        string $name,
        int $userId
    ): ServiceProductTemplateFolder {
        $existing =
            ServiceProductTemplateFolder::query()
                ->where(
                    'service_product_id',
                    $serviceProduct->id
                )
                ->where(
                    'parent_id',
                    $parent?->id
                )
                ->where(
                    'type',
                    'folder'
                )
                ->where(
                    'name',
                    $name
                )
                ->first();

        if (
            $existing
        ) {
            return $existing;
        }

        return ServiceProductTemplateFolder::query()
            ->create([
                'service_product_id' =>
                    $serviceProduct->id,

                'parent_id' =>
                    $parent?->id,

                'client_key' =>
                    (string) Str::uuid(),

                'type' =>
                    'folder',

                'name' =>
                    $name,

                'client_visible' =>
                    true,

                'sort_order' =>
                    $this->nextSortOrder(
                        $serviceProduct,
                        $parent?->id
                    ),

                'uploaded_by' =>
                    $userId,
            ]);
    }

    private function nextSortOrder(
        ServiceProduct $serviceProduct,
        ?int $parentId
    ): int {
        return (
            (int) (
                ServiceProductTemplateFolder::query()
                    ->where(
                        'service_product_id',
                        $serviceProduct->id
                    )
                    ->where(
                        'parent_id',
                        $parentId
                    )
                    ->max('sort_order')
                ?? -1
            )
        ) + 1;
    }

    private function resourceTypeForMime(
        string $mime
    ): string {
        if (
            str_starts_with(
                $mime,
                'image/'
            )
        ) {
            return 'image';
        }

        if (
            $mime === 'application/pdf'
        ) {
            return 'pdf';
        }

        return 'file';
    }

    private function normalizeUrl(
        ?string $url
    ): ?string {
        $url =
            trim(
                (string) $url
            );

        if (
            $url === ''
        ) {
            return null;
        }

        if (
            preg_match(
                '/^[a-z][a-z\d+.-]*:/i',
                $url
            )
        ) {
            return $url;
        }

        return 'https://' . $url;
    }

    private function shouldInline(
        ServiceProductTemplateFolder $folder
    ): bool {
        $mime =
            strtolower(
                (string) (
                    $folder->mime_type
                    ?? ''
                )
            );

        return
            str_starts_with(
                $mime,
                'image/'
            ) ||
            in_array(
                $mime,
                [
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'application/json',
                    'application/xml',
                    'text/xml',
                ],
                true
            );
    }

    private function assertOwnership(
        ServiceProduct $serviceProduct,
        ServiceProductTemplateFolder $folder
    ): void {
        abort_unless(
            (int) $folder->service_product_id ===
                (int) $serviceProduct->id,
            404
        );
    }
}