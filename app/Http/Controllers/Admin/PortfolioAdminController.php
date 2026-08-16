<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PortfolioAdminController extends Controller
{
    public function translate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $translation = $this->translateEnToSk($data['text']);

        if ($translation === null) {
            return response()->json([
                'translated' => $data['text'],
                'fallback' => true,
            ]);
        }

        return response()->json([
            'translated' => $translation,
            'fallback' => false,
        ]);
    }

    public function listing(): JsonResponse
    {
        return response()->json(Project::query()
            ->with(['company:id,name'])
            ->withCount(['images', 'features'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'url' => $project->url,
                'company' => $project->company?->display_label,
                'is_published' => $project->is_published,
                'images_count' => $project->images_count,
                'features_count' => $project->features_count,
                'updated_at' => $project->updated_at?->toIso8601String(),
            ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $slug = $this->resolvedSlug($data['url'] ?? '', $data['name']);
        $this->ensureUniqueSlug($slug);

        DB::transaction(function () use ($request, $data, $slug) {
            $nameSk = $this->resolveSlovakText($data['name'], $data['name_sk'] ?? null);
            $summaryEn = $this->nullableText($data['summary'] ?? null);
            $summarySk = $this->resolveSlovakText($summaryEn, $data['summary_sk'] ?? null);

            $project = Project::create([
                'company_id' => $data['company_id'] ?? null,
                'service_product_id' => $data['service_product_id'] ?? null,
                'portal_status' => $data['portal_status'] ?? 'active',
                'name' => $data['name'],
                'name_translations' => $this->translationArray($data['name'], $nameSk),
                'url' => $slug,
                'live_url' => $this->nullableText($data['live_url'] ?? null),
                'summary' => $summaryEn,
                'summary_translations' => $this->translationArray($summaryEn, $summarySk),
                'hex_color' => $data['hex_color'] ?: null,
                'logo_path' => $this->resolvedLogoPath($request->file('logo_file'), $slug, $this->nullableText($data['existing_logo_path'] ?? null), $this->nullableText($data['logo_path'] ?? null)),
            ]);

            $this->syncImages($request, $project, $data['images'] ?? []);
            $this->syncFeatures($project, $data['features'] ?? []);
        });

        return redirect()
            ->route('admin.portfolio.index')
            ->with('status', 'Project created successfully.');
    }

    public function show(Project $project): JsonResponse
    {
        $project->load(['images', 'features']);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'company_id' => $project->company_id,
                'service_product_id' => $project->service_product_id,
                'portal_status' => $project->portal_status,
                'is_published' => $project->is_published,
                'name' => $project->name,
                'name_sk' => data_get($project->name_translations, 'sk', ''),
                'url' => $project->url,
                'live_url' => $project->live_url,
                'summary' => $project->summary,
                'summary_sk' => data_get($project->summary_translations, 'sk', ''),
                'hex_color' => $project->hex_color,
                'logo_path' => $project->logo_path,
                'images' => $project->images->map(fn ($image) => [
                    'id' => $image->id,
                    'path' => $image->path,
                    'existing_path' => $image->path,
                    'description' => $image->description ?? '',
                    'description_sk' => data_get($image->description_translations, 'sk', ''),
                    'alt' => $image->alt ?? '',
                    'alt_sk' => data_get($image->alt_translations, 'sk', ''),
                    'sort_order' => $image->sort_order,
                ])->values(),
                'features' => $project->features->map(fn ($feature) => [
                    'id' => $feature->id,
                    'title' => $feature->title,
                    'title_sk' => data_get($feature->title_translations, 'sk', ''),
                    'description' => $feature->description,
                    'description_sk' => data_get($feature->description_translations, 'sk', ''),
                    'sort_order' => $feature->sort_order,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $data = $this->validatedData($request, $project->id);
        $slug = $this->resolvedSlug($data['url'] ?? '', $data['name']);
        $this->ensureUniqueSlug($slug, $project->id);

        DB::transaction(function () use ($request, $project, $data, $slug) {
            $nameSk = $this->resolveSlovakText($data['name'], $data['name_sk'] ?? null);
            $summaryEn = $this->nullableText($data['summary'] ?? null);
            $summarySk = $this->resolveSlovakText($summaryEn, $data['summary_sk'] ?? null);

            $project->update([
                'company_id' => $data['company_id'] ?? null,
                'service_product_id' => $data['service_product_id'] ?? null,
                'portal_status' => $data['portal_status'] ?? 'active',
                'name' => $data['name'],
                'name_translations' => $this->translationArray($data['name'], $nameSk),
                'url' => $slug,
                'live_url' => $this->nullableText($data['live_url'] ?? null),
                'summary' => $summaryEn,
                'summary_translations' => $this->translationArray($summaryEn, $summarySk),
                'hex_color' => $data['hex_color'] ?: null,
                'logo_path' => $this->resolvedLogoPath($request->file('logo_file'), $slug, $this->nullableText($data['existing_logo_path'] ?? null), $this->nullableText($data['logo_path'] ?? null)),
            ]);

            $this->syncImages($request, $project, $data['images'] ?? []);
            $this->syncFeatures($project, $data['features'] ?? []);
        });

        if ($request->expectsJson()) {
            return $this->show($project->fresh());
        }

        return redirect()
            ->route('admin.portfolio.index')
            ->with('status', 'Project updated successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()
            ->route('admin.portfolio.index')
            ->with('status', 'Project deleted successfully.');
    }

    private function validatedData(Request $request, ?int $projectId = null): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'service_product_id' => ['nullable', 'exists:service_products,id'],
            'portal_status' => ['nullable', 'in:active,on_hold,completed,archived'],
            'name' => ['required', 'string', 'max:255'],
            'name_sk' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'live_url' => ['nullable', 'url', 'max:2048'],
            'summary' => ['nullable', 'string'],
            'summary_sk' => ['nullable', 'string'],
            'hex_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'existing_logo_path' => ['nullable', 'string', 'max:255'],
            'logo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg,avif', 'max:20480'],
            'images' => ['nullable', 'array'],
            'images.*.path' => ['nullable', 'string', 'max:255'],
            'images.*.existing_path' => ['nullable', 'string', 'max:255'],
            'images.*.file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,svg,avif', 'max:20480'],
            'images.*.description' => ['nullable', 'string', 'max:255'],
            'images.*.description_sk' => ['nullable', 'string', 'max:255'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.alt_sk' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*.title' => ['nullable', 'string', 'max:255'],
            'features.*.title_sk' => ['nullable', 'string', 'max:255'],
            'features.*.description' => ['nullable', 'string'],
            'features.*.description_sk' => ['nullable', 'string'],
            'features.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function syncImages(Request $request, Project $project, array $images): void
    {
        $project->images()->delete();
        $folder = 'projects/' . $project->url;
        Storage::disk('public')->makeDirectory($folder);

        $uploadedImages = $request->file('images', []);
        $indexes = array_unique(array_merge(array_keys($images), array_keys(is_array($uploadedImages) ? $uploadedImages : [])));

        foreach ($indexes as $index) {
            $image = is_array($images[$index] ?? null) ? $images[$index] : [];
            $uploadedFile = $request->file("images.$index.file");
            $existingPath = trim((string) ($image['existing_path'] ?? ''));
            $manualPath = trim((string) ($image['path'] ?? ''));
            $path = $this->resolvedImagePath($uploadedFile, $folder, $existingPath, $manualPath);

            if ($path === '') {
                continue;
            }

            $description = $this->nullableText($image['description'] ?? null);
            $descriptionSk = $this->resolveSlovakText($description, $image['description_sk'] ?? null);
            $alt = $this->nullableText($image['alt'] ?? null);
            $altSk = $this->resolveSlovakText($alt, $image['alt_sk'] ?? null);

            $project->images()->create([
                'path' => $path,
                'description' => $description,
                'description_translations' => $this->translationArray($description, $descriptionSk),
                'alt' => $alt,
                'alt_translations' => $this->translationArray($alt, $altSk),
                'sort_order' => (int) ($image['sort_order'] ?? 0),
            ]);
        }
    }

    private function syncFeatures(Project $project, array $features): void
    {
        $project->features()->delete();

        foreach ($features as $feature) {
            $titleEn = $this->nullableText($feature['title'] ?? null);
            $titleSkInput = $this->nullableText($feature['title_sk'] ?? null);
            $descriptionEn = $this->nullableText($feature['description'] ?? null);
            $descriptionSkInput = $this->nullableText($feature['description_sk'] ?? null);

            $title = $titleEn ?? $titleSkInput;
            $description = $descriptionEn ?? $descriptionSkInput;

            if ($title === null || $description === null) {
                continue;
            }

            $titleSk = $this->resolveSlovakText($title, $titleSkInput);
            $descriptionSk = $this->resolveSlovakText($description, $descriptionSkInput);

            $project->features()->create([
                'title' => $title,
                'title_translations' => $this->translationArray($title, $titleSk),
                'description' => $description,
                'description_translations' => $this->translationArray($description, $descriptionSk),
                'sort_order' => (int) ($feature['sort_order'] ?? 0),
            ]);
        }
    }

    private function resolvedSlug(?string $slug, string $name): string
    {
        $normalized = trim((string) $slug);

        if ($normalized !== '') {
            if (filter_var($normalized, FILTER_VALIDATE_URL)) {
                $path = trim((string) parse_url($normalized, PHP_URL_PATH), '/');

                if ($path !== '') {
                    $normalized = basename($path);
                } else {
                    $normalized = (string) parse_url($normalized, PHP_URL_HOST);
                }
            }

            $slugified = Str::slug($normalized);
            if ($slugified !== '') {
                return $slugified;
            }
        }

        $fromName = Str::slug($name);

        return $fromName !== '' ? $fromName : 'project-' . Str::lower(Str::random(8));
    }

    private function ensureUniqueSlug(string $slug, ?int $ignoreProjectId = null): void
    {
        $exists = Project::query()
            ->where('url', $slug)
            ->when($ignoreProjectId !== null, fn ($query) => $query->where('id', '!=', $ignoreProjectId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'url' => 'This URL/slug is already used by another project.',
            ]);
        }
    }

    private function resolvedImagePath(?UploadedFile $file, string $folder, string $existingPath, string $manualPath): string
    {
        if ($file instanceof UploadedFile) {
            Storage::disk('public')->makeDirectory($folder);
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filename = Str::uuid() . '.' . $extension;
            $storedPath = $file->storeAs($folder, $filename, 'public');

            return '/storage/' . $storedPath;
        }

        if ($existingPath !== '') {
            return $existingPath;
        }

        return $manualPath;
    }

    private function resolvedLogoPath(?UploadedFile $file, string $slug, ?string $existingPath, ?string $manualPath): ?string
    {
        if ($file instanceof UploadedFile) {
            $folder = 'projects/' . $slug;
            Storage::disk('public')->makeDirectory($folder);
            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filename = 'logo-' . Str::uuid() . '.' . $extension;
            $storedPath = $file->storeAs($folder, $filename, 'public');

            return '/storage/' . $storedPath;
        }

        if ($existingPath !== null) {
            return $existingPath;
        }

        return $manualPath;
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resolveSlovakText(?string $englishText, mixed $providedSlovak): ?string
    {
        $manualSlovak = $this->nullableText($providedSlovak);
        if ($manualSlovak !== null) {
            return $manualSlovak;
        }

        $englishText = $this->nullableText($englishText);
        if ($englishText === null) {
            return null;
        }

        $translated = $this->translateEnToSk($englishText);

        return $translated ?? $englishText;
    }

    private function translateEnToSk(string $text): ?string
    {
        try {
            $response = Http::timeout(8)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx',
                'sl' => 'en',
                'tl' => 'sk',
                'dt' => 't',
                'q' => $text,
            ]);

            if (!$response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (!is_array($payload) || !isset($payload[0]) || !is_array($payload[0])) {
                return null;
            }

            $parts = [];
            foreach ($payload[0] as $chunk) {
                if (is_array($chunk) && isset($chunk[0]) && is_string($chunk[0])) {
                    $parts[] = $chunk[0];
                }
            }

            $result = trim(implode('', $parts));

            return $result !== '' ? $result : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function translationArray(?string $en, ?string $sk): ?array
    {
        $translations = [];

        if (is_string($en) && trim($en) !== '') {
            $translations['en'] = trim($en);
        }

        if (is_string($sk) && trim($sk) !== '') {
            $translations['sk'] = trim($sk);
        }

        return $translations === [] ? null : $translations;
    }
}
