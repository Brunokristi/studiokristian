<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceBlueprintFolderDefinition;
use App\Models\ServiceBlueprintVersion;
use App\Models\ServiceProduct;
use App\Services\ServiceBlueprintVersionService;
use App\Services\ServiceProductReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ServiceBlueprintController extends Controller
{
    public function show(ServiceProduct $serviceProduct, ServiceProductReadinessService $readiness): JsonResponse
    {
        $serviceProduct->load(['blueprint.versions' => fn ($query) => $query->latest(), 'defaultContractTemplate.versions' => fn ($query) => $query->latest()]);
        $editable = $serviceProduct->blueprint?->versions()->where('status', 'draft')->latest()->first()
            ?? $serviceProduct->blueprint?->versions()->where('status', 'published')->latest('published_at')->first();
        return response()->json(['product' => $serviceProduct, 'readiness' => $readiness->inspect($serviceProduct), 'version' => $editable?->load(['fields', 'deliverables', 'folders'])]);
    }

    public function create(ServiceProduct $serviceProduct, Request $request, ServiceBlueprintVersionService $service): JsonResponse
    {
        abort_if($serviceProduct->blueprint()->exists(), 409);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'version' => ['required', 'regex:/^\d+\.\d+$/']]);
        return response()->json($service->create($serviceProduct, $data['name'], $data['version'], $request->user())->load(['fields', 'deliverables', 'folders']), 201);
    }

    public function draft(ServiceProduct $serviceProduct, Request $request, ServiceBlueprintVersionService $service): JsonResponse
    {
        abort_unless($serviceProduct->blueprint, 404);
        $data = $request->validate(['version' => ['required', 'regex:/^\d+\.\d+$/', Rule::unique('service_blueprint_versions', 'version')->where('service_blueprint_id', $serviceProduct->blueprint->id)]]);
        return response()->json($service->createDraft($serviceProduct->blueprint, $data['version'], $request->user())->load(['fields', 'deliverables', 'folders']), 201);
    }

    public function update(ServiceBlueprintVersion $version, Request $request): JsonResponse
    {
        abort_unless($version->status === 'draft', 409);
        $data = $request->validate([
            'fields' => ['array'], 'fields.*.id' => ['nullable', 'integer'], 'fields.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/', 'max:100'], 'fields.*.label' => ['required', 'string', 'max:255'], 'fields.*.description' => ['nullable', 'string', 'max:2000'], 'fields.*.type' => ['required', 'in:text,textarea,number,date,checkbox,select,multi_select,radio'], 'fields.*.required' => ['required', 'boolean'], 'fields.*.default_value' => ['nullable'], 'fields.*.options' => ['nullable', 'array'], 'fields.*.section' => ['nullable', 'string', 'max:100'],
            'deliverables' => ['array'], 'deliverables.*.id' => ['nullable', 'integer'], 'deliverables.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]*$/'], 'deliverables.*.name' => ['required', 'string', 'max:255'], 'deliverables.*.description' => ['nullable', 'string'], 'deliverables.*.category' => ['nullable', 'string', 'max:100'], 'deliverables.*.requirement_level' => ['required', 'in:required,recommended,optional'], 'deliverables.*.expected_resource_type' => ['required', 'in:file,folder,document,guide,service_account,external_link,manual_confirmation'], 'deliverables.*.client_visible' => ['required', 'boolean'], 'deliverables.*.default_selected' => ['required', 'boolean'],
            'folders' => ['array'], 'folders.*.id' => ['nullable', 'integer'], 'folders.*.client_key' => ['required', 'string', 'max:100'], 'folders.*.parent_client_key' => ['nullable', 'string', 'max:100'], 'folders.*.type' => ['nullable', 'in:folder,file'], 'folders.*.name' => ['required', 'string', 'max:150', 'not_in:.,..'], 'folders.*.resource_type' => ['nullable', 'in:document,file,link'], 'folders.*.requirement_level' => ['nullable', 'in:required,recommended,optional'], 'folders.*.requires_client_signature' => ['nullable', 'boolean'], 'folders.*.template_name' => ['nullable', 'string', 'max:255'], 'folders.*.content' => ['nullable', 'string'], 'folders.*.url' => ['nullable', 'string', 'max:2000'], 'folders.*.client_visible' => ['required', 'boolean'],
        ]);

        $removedFolderFiles = [];

        DB::transaction(function () use ($version, $data, &$removedFolderFiles) {
            $kept = [];
            foreach ($data['fields'] ?? [] as $order => $input) {
                $field = isset($input['id']) ? $version->fields()->findOrFail($input['id']) : $version->fields()->make();
                if ($field->exists && $field->key !== $input['key']) {
                    abort(422, 'Published field keys copied into a draft cannot be renamed.');
                }
                $field->fill($input + ['sort_order' => $order]);
                $field->save();
                $kept[] = $field->id;
            }
            $version->fields()->whereNotIn('id', $kept ?: [0])->delete();

            $kept = [];
            foreach ($data['deliverables'] ?? [] as $order => $input) {
                $item = isset($input['id']) ? $version->deliverables()->findOrFail($input['id']) : $version->deliverables()->make();
                unset($input['id']);
                $item->fill($input + ['sort_order' => $order]);
                $item->save();
                $kept[] = $item->id;
            }
            $version->deliverables()->whereNotIn('id', $kept ?: [0])->delete();

            $existingFolders = $version->folders()->get()->keyBy('id');
            $processedIds = [];
            $map = [];
            $pending = collect($data['folders'] ?? [])->values();

            while ($pending->isNotEmpty()) {
                $progress = false;

                foreach ($pending as $index => $input) {
                    $parentClientKey = $input['parent_client_key'] ?? null;
                    if ($parentClientKey && !isset($map[$parentClientKey])) {
                        continue;
                    }

                    $folder = null;

                    if (!empty($input['id'])) {
                        $folder = $version->folders()->findOrFail((int) $input['id']);
                    }

                    if (! $folder) {
                        $folder = $version->folders()->make();
                    }

                    $folder->fill([
                        'parent_id' => $parentClientKey ? $map[$parentClientKey] : null,
                        'type' => $input['type'] ?? 'folder',
                        'name' => $input['name'],
                        'resource_type' => $input['resource_type'] ?? null,
                        'requirement_level' => $input['requirement_level'] ?? null,
                        'requires_client_signature' => (bool) ($input['requires_client_signature'] ?? false),
                        'template_name' => $input['template_name'] ?? null,
                        'content' => $input['content'] ?? null,
                        'url' => ($input['resource_type'] ?? null) === 'link'
                            ? $this->normalizeExternalUrl($input['url'] ?? null)
                            : null,
                        'client_visible' => $input['client_visible'],
                        'sort_order' => $index,
                    ]);
                    $folder->save();

                    $processedIds[] = $folder->id;
                    $map[$input['client_key']] = $folder->id;
                    $pending->forget($index);
                    $progress = true;
                }

                if (! $progress) {
                    abort(422, 'Folder tree contains a missing or circular parent.');
                }
            }

            $removed = $existingFolders
                ->filter(fn (ServiceBlueprintFolderDefinition $folder) => !in_array($folder->id, $processedIds, true))
                ->values();

            foreach ($removed as $folder) {
                if ($folder->storage_path) {
                    $removedFolderFiles[] = [
                        'disk' => $folder->disk ?: 'local',
                        'storage_path' => $folder->storage_path,
                    ];
                }
            }

            if (!empty($processedIds)) {
                $version->folders()->whereNotIn('id', $processedIds)->delete();
            } else {
                $version->folders()->delete();
            }
        });

        foreach ($removedFolderFiles as $fileRef) {
            Storage::disk($fileRef['disk'])->delete($fileRef['storage_path']);
        }

        return response()->json($version->fresh()->load(['fields','deliverables','folders']));
    }

    public function publish(ServiceBlueprintVersion $version, Request $request, ServiceBlueprintVersionService $service): JsonResponse
    {
        $data=$request->validate(['change_summary'=>['required','string','max:5000']]);
        return response()->json($service->publish($version,$data['change_summary'],$request->user()));
    }

    private function normalizeExternalUrl(?string $value): ?string
    {
        $url = trim((string) $value);

        if ($url === '') {
            return null;
        }

        if (preg_match('/^[a-z][a-z\d+.-]*:/i', $url) === 1) {
            return $url;
        }

        return 'https://'.$url;
    }
}