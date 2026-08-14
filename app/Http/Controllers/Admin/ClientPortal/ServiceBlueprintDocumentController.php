<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\ServiceBlueprintFolderDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceBlueprintDocumentController extends Controller
{
    public function update(ServiceBlueprintFolderDefinition $folder, Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:2000'],
            'document_schema' => ['required', 'array'],
            'revision' => ['required', 'integer', 'min:1'],
        ]);

        if ($folder->type !== 'file' || $folder->resource_type !== 'document') {
            throw new HttpException(422, 'Only document files can be edited via this endpoint.');
        }

        $saved = DB::transaction(function () use ($folder, $data) {
            $locked = ServiceBlueprintFolderDefinition::query()
                ->with('blueprintVersion')
                ->lockForUpdate()
                ->findOrFail($folder->id);

            if ($locked->blueprintVersion?->status !== 'draft') {
                throw new HttpException(409, 'Published blueprint documents are immutable.');
            }

            $expectedRevision = ((int) $locked->document_revision) + 1;
            if ((int) $data['revision'] !== $expectedRevision) {
                return [
                    'stale' => true,
                    'expected_revision' => $expectedRevision,
                    'saved_revision' => (int) $locked->document_revision,
                    'content' => (string) ($locked->content ?? ''),
                ];
            }

            $envelope = [
                'version' => 1,
                'title' => $data['title'],
                'subtitle' => (string) ($data['subtitle'] ?? ''),
                'doc' => $data['document_schema'],
            ];

            $locked->fill([
                'name' => $data['title'],
                'template_name' => $data['title'],
                'content' => json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'document_revision' => (int) $data['revision'],
            ]);
            $locked->save();

            return [
                'stale' => false,
                'id' => $locked->id,
                'name' => $locked->name,
                'title' => $envelope['title'],
                'subtitle' => $envelope['subtitle'],
                'document_schema' => $envelope['doc'],
                'content' => $locked->content,
                'saved_revision' => (int) $locked->document_revision,
            ];
        });

        if (($saved['stale'] ?? false) === true) {
            return response()->json([
                'message' => 'Stale document save rejected.',
                ...$saved,
            ], 409);
        }

        return response()->json($saved);
    }
}
