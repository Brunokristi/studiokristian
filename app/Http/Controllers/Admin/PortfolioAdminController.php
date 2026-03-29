<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PortfolioAdminController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->withCount(['images', 'features'])
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.portfolio.index', [
            'projects' => $projects,
        ]);
    }

    public function create(): View
    {
        return view('admin.portfolio.form', [
            'project' => null,
            'images' => [['path' => '', 'description' => '', 'description_sk' => '', 'sort_order' => 0]],
            'features' => [['title' => '', 'title_sk' => '', 'description' => '', 'description_sk' => '', 'sort_order' => 0]],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data) {
            $project = Project::create([
                'name' => $data['name'],
                'name_translations' => $this->translationArray($data['name'], $data['name_sk'] ?? null),
                'url' => $data['url'] !== '' ? $data['url'] : Str::slug($data['name']),
                'summary' => $data['summary'] ?: null,
                'summary_translations' => $this->translationArray($data['summary'] ?: null, $data['summary_sk'] ?: null),
                'hex_color' => $data['hex_color'] ?: null,
                'logo_path' => $data['logo_path'] ?: null,
            ]);

            $this->syncImages($project, $data['images'] ?? []);
            $this->syncFeatures($project, $data['features'] ?? []);
        });

        return redirect()
            ->route('admin.portfolio.index')
            ->with('status', 'Project created successfully.');
    }

    public function edit(Project $project): View
    {
        $project->load(['images', 'features']);

        $images = $project->images
            ->map(function ($image) {
                return [
                    'path' => $image->path,
                    'description' => $image->description ?? '',
                    'description_sk' => data_get($image->description_translations, 'sk', ''),
                    'sort_order' => $image->sort_order,
                ];
            })
            ->values()
            ->all();

        $features = $project->features
            ->map(function ($feature) {
                return [
                    'title' => $feature->title,
                    'title_sk' => data_get($feature->title_translations, 'sk', ''),
                    'description' => $feature->description,
                    'description_sk' => data_get($feature->description_translations, 'sk', ''),
                    'sort_order' => $feature->sort_order,
                ];
            })
            ->values()
            ->all();

        if ($images === []) {
            $images = [['path' => '', 'description' => '', 'description_sk' => '', 'sort_order' => 0]];
        }

        if ($features === []) {
            $features = [['title' => '', 'title_sk' => '', 'description' => '', 'description_sk' => '', 'sort_order' => 0]];
        }

        return view('admin.portfolio.form', [
            'project' => $project,
            'images' => $images,
            'features' => $features,
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedData($request, $project->id);

        DB::transaction(function () use ($project, $data) {
            $project->update([
                'name' => $data['name'],
                'name_translations' => $this->translationArray($data['name'], $data['name_sk'] ?? null),
                'url' => $data['url'] !== '' ? $data['url'] : Str::slug($data['name']),
                'summary' => $data['summary'] ?: null,
                'summary_translations' => $this->translationArray($data['summary'] ?: null, $data['summary_sk'] ?: null),
                'hex_color' => $data['hex_color'] ?: null,
                'logo_path' => $data['logo_path'] ?: null,
            ]);

            $this->syncImages($project, $data['images'] ?? []);
            $this->syncFeatures($project, $data['features'] ?? []);
        });

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
            'name' => ['required', 'string', 'max:255'],
            'name_sk' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:projects,url,' . ($projectId ?? 'NULL')],
            'summary' => ['nullable', 'string'],
            'summary_sk' => ['nullable', 'string'],
            'hex_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*.path' => ['nullable', 'string', 'max:255'],
            'images.*.description' => ['nullable', 'string', 'max:255'],
            'images.*.description_sk' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*.title' => ['nullable', 'string', 'max:255'],
            'features.*.title_sk' => ['nullable', 'string', 'max:255'],
            'features.*.description' => ['nullable', 'string'],
            'features.*.description_sk' => ['nullable', 'string'],
            'features.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function syncImages(Project $project, array $images): void
    {
        $project->images()->delete();

        foreach ($images as $image) {
            $path = trim((string) ($image['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $description = trim((string) ($image['description'] ?? ''));
            $descriptionSk = trim((string) ($image['description_sk'] ?? ''));

            $project->images()->create([
                'path' => $path,
                'description' => $description !== '' ? $description : null,
                'description_translations' => $this->translationArray($description !== '' ? $description : null, $descriptionSk !== '' ? $descriptionSk : null),
                'sort_order' => (int) ($image['sort_order'] ?? 0),
            ]);
        }
    }

    private function syncFeatures(Project $project, array $features): void
    {
        $project->features()->delete();

        foreach ($features as $feature) {
            $title = trim((string) ($feature['title'] ?? ''));
            $description = trim((string) ($feature['description'] ?? ''));

            if ($title === '' || $description === '') {
                continue;
            }

            $titleSk = trim((string) ($feature['title_sk'] ?? ''));
            $descriptionSk = trim((string) ($feature['description_sk'] ?? ''));

            $project->features()->create([
                'title' => $title,
                'title_translations' => $this->translationArray($title, $titleSk !== '' ? $titleSk : null),
                'description' => $description,
                'description_translations' => $this->translationArray($description, $descriptionSk !== '' ? $descriptionSk : null),
                'sort_order' => (int) ($feature['sort_order'] ?? 0),
            ]);
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
