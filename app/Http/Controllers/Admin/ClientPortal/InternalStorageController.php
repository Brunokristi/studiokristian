<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\CompanyStorageFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InternalStorageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('company_storage_folders')) {
            return response()->json([
                'folders' => [],
            ]);
        }

        $folders = CompanyStorageFolder::query()
            ->whereNull('company_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'folders' => $folders,
        ]);
    }

    public function updateStructure(Request $request): JsonResponse
    {
        if (! Schema::hasTable('company_storage_folders')) {
            throw ValidationException::withMessages([
                'storage' => 'Company storage is not available yet. Run database migrations first.',
            ]);
        }

        $data = $request->validate([
            'folders' => ['array'],
            'folders.*.id' => ['nullable', 'integer'],
            'folders.*.client_key' => ['required', 'string', 'max:100'],
            'folders.*.parent_client_key' => ['nullable', 'string', 'max:100'],
            'folders.*.type' => ['nullable', 'in:folder,file'],
            'folders.*.name' => ['required', 'string', 'max:150', 'not_in:.,..'],
            'folders.*.resource_type' => ['nullable', 'in:document,file,link'],
            'folders.*.requirement_level' => ['nullable', 'in:required,recommended,optional'],
            'folders.*.requires_client_signature' => ['nullable', 'boolean'],
            'folders.*.template_name' => ['nullable', 'string', 'max:255'],
            'folders.*.content' => ['nullable', 'string'],
            'folders.*.url' => ['nullable', 'string', 'max:2000'],
            'folders.*.client_visible' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $existing = CompanyStorageFolder::query()->whereNull('company_id')->get()->keyBy('id');
            $processedIds = [];
            $map = [];
            $pending = collect($data['folders'] ?? [])->values();

            while ($pending->isNotEmpty()) {
                $progress = false;

                foreach ($pending as $index => $input) {
                    $parentClientKey = $input['parent_client_key'] ?? null;
                    if ($parentClientKey && ! isset($map[$parentClientKey])) {
                        continue;
                    }

                    $folder = null;
                    if (! empty($input['id'])) {
                        $folder = CompanyStorageFolder::query()->whereNull('company_id')->whereKey((int) $input['id'])->firstOrFail();
                    }

                    if (! $folder) {
                        $folder = new CompanyStorageFolder();
                    }

                    $resourceType = ($input['type'] ?? 'folder') === 'file'
                        ? ($input['resource_type'] ?? 'document')
                        : null;

                    $isDocument = $resourceType === 'document';

                    $folder->fill([
                        'company_id' => null,
                        'parent_id' => $parentClientKey ? $map[$parentClientKey] : null,
                        'type' => $input['type'] ?? 'folder',
                        'name' => $input['name'],
                        'resource_type' => $resourceType,
                        'requirement_level' => $input['requirement_level'] ?? null,
                        'requires_client_signature' => $isDocument
                            ? (bool) ($input['requires_client_signature'] ?? false)
                            : false,
                        'template_name' => $input['template_name'] ?? null,
                        'content' => $input['content'] ?? null,
                        'url' => $resourceType === 'link' ? ($input['url'] ?? null) : null,
                        'client_visible' => false,
                        'sort_order' => $index,
                        'created_by' => $folder->created_by ?: $request->user()?->id,
                    ]);
                    $folder->save();

                    $processedIds[] = $folder->id;
                    $map[$input['client_key']] = $folder->id;
                    $pending->forget($index);
                    $progress = true;
                }

                if (! $progress) {
                    throw ValidationException::withMessages([
                        'folders' => 'Folder tree contains a missing or circular parent.',
                    ]);
                }
            }

            if (! empty($processedIds)) {
                CompanyStorageFolder::query()->whereNull('company_id')->whereNotIn('id', $processedIds)->delete();
            } else {
                CompanyStorageFolder::query()->whereNull('company_id')->delete();
            }
        });

        return response()->json([
            'folders' => CompanyStorageFolder::query()
                ->whereNull('company_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }
}
