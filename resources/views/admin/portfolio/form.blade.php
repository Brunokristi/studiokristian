@php
    $isEdit = $project !== null;

    $nameTranslations = $project?->name_translations ?? [];
    $summaryTranslations = $project?->summary_translations ?? [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $isEdit ? 'Edit Project' : 'Create Project' }} | Portfolio Admin</title>
    <style>
        :root {
            --bg: #0d1117;
            --surface: #161b22;
            --muted: #8b949e;
            --text: #e6edf3;
            --accent: #2f81f7;
            --border: #30363d;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 24px;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .title { font-size: 28px; margin: 0; }
        .subtle { color: var(--muted); margin-top: 6px; }
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
        }
        .section-title {
            margin: 0 0 12px;
            font-size: 16px;
            letter-spacing: .02em;
        }
        .row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .row-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }
        @media (max-width: 900px) {
            .row, .row-4 { grid-template-columns: 1fr; }
        }
        label {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        input, textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 11px;
            background: #0f141a;
            color: var(--text);
            font: inherit;
        }
        textarea { min-height: 90px; resize: vertical; }
        .actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            color: var(--text);
            text-decoration: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
        }
        .button.primary { background: var(--accent); border-color: var(--accent); }
        .helper { font-size: 12px; color: var(--muted); margin-top: 6px; }
        .errors {
            margin-bottom: 16px;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #a4282d;
            background: rgba(218, 54, 51, .12);
        }
        .small { font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1 class="title">{{ $isEdit ? 'Edit Project' : 'Create Project' }}</h1>
            <p class="subtle">Manage translated text, images, and features for your portfolio page.</p>
        </div>
        <a class="button" href="{{ route('admin.portfolio.index') }}">Back to list</a>
    </div>

    @if ($errors->any())
        <div class="errors">
            <strong>There are validation errors:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('admin.portfolio.update', $project) : route('admin.portfolio.store') }}">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="panel">
            <h2 class="section-title">Project Basics</h2>
            <div class="row">
                <div>
                    <label for="name">Name (EN)</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $project?->name) }}" required>
                </div>
                <div>
                    <label for="name_sk">Name (SK)</label>
                    <input id="name_sk" name="name_sk" type="text" value="{{ old('name_sk', data_get($nameTranslations, 'sk', '')) }}">
                </div>
                <div>
                    <label for="url">URL slug</label>
                    <input id="url" name="url" type="text" value="{{ old('url', $project?->url) }}" placeholder="leave blank to auto-generate">
                    <p class="helper">Use lowercase letters, numbers, and hyphens.</p>
                </div>
                <div>
                    <label for="hex_color">Brand color</label>
                    <input id="hex_color" name="hex_color" type="text" value="{{ old('hex_color', $project?->hex_color) }}" placeholder="#133EB4">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label for="logo_path">Logo path</label>
                    <input id="logo_path" name="logo_path" type="text" value="{{ old('logo_path', $project?->logo_path) }}" placeholder="/assets/logos/project.svg">
                </div>
                <div>
                    <label for="summary">Summary (EN)</label>
                    <textarea id="summary" name="summary">{{ old('summary', $project?->summary) }}</textarea>
                </div>
                <div>
                    <label for="summary_sk">Summary (SK)</label>
                    <textarea id="summary_sk" name="summary_sk">{{ old('summary_sk', data_get($summaryTranslations, 'sk', '')) }}</textarea>
                </div>
            </div>
        </div>

        <div class="panel">
            <h2 class="section-title">Images</h2>
            <p class="small">Each row needs at least a path to be saved.</p>
            @for($i = 0; $i < max(count(old('images', $images)), 6); $i++)
                <div class="row-4">
                    <div>
                        <label>Path</label>
                        <input name="images[{{ $i }}][path]" type="text" value="{{ old("images.$i.path", data_get($images, "$i.path", '')) }}" placeholder="/storage/projects/project-1.jpg">
                    </div>
                    <div>
                        <label>Description (EN)</label>
                        <input name="images[{{ $i }}][description]" type="text" value="{{ old("images.$i.description", data_get($images, "$i.description", '')) }}">
                    </div>
                    <div>
                        <label>Description (SK)</label>
                        <input name="images[{{ $i }}][description_sk]" type="text" value="{{ old("images.$i.description_sk", data_get($images, "$i.description_sk", '')) }}">
                    </div>
                    <div>
                        <label>Sort</label>
                        <input name="images[{{ $i }}][sort_order]" type="number" min="0" value="{{ old("images.$i.sort_order", data_get($images, "$i.sort_order", 0)) }}">
                    </div>
                </div>
            @endfor
        </div>

        <div class="panel">
            <h2 class="section-title">Features</h2>
            <p class="small">A feature is saved only when both EN title and EN description are filled.</p>
            @for($i = 0; $i < max(count(old('features', $features)), 8); $i++)
                <div class="row-4">
                    <div>
                        <label>Title (EN)</label>
                        <input name="features[{{ $i }}][title]" type="text" value="{{ old("features.$i.title", data_get($features, "$i.title", '')) }}">
                    </div>
                    <div>
                        <label>Title (SK)</label>
                        <input name="features[{{ $i }}][title_sk]" type="text" value="{{ old("features.$i.title_sk", data_get($features, "$i.title_sk", '')) }}">
                    </div>
                    <div>
                        <label>Sort</label>
                        <input name="features[{{ $i }}][sort_order]" type="number" min="0" value="{{ old("features.$i.sort_order", data_get($features, "$i.sort_order", 0)) }}">
                    </div>
                    <div></div>
                    <div style="grid-column: 1 / span 2;">
                        <label>Description (EN)</label>
                        <textarea name="features[{{ $i }}][description]">{{ old("features.$i.description", data_get($features, "$i.description", '')) }}</textarea>
                    </div>
                    <div style="grid-column: 3 / span 2;">
                        <label>Description (SK)</label>
                        <textarea name="features[{{ $i }}][description_sk]">{{ old("features.$i.description_sk", data_get($features, "$i.description_sk", '')) }}</textarea>
                    </div>
                </div>
            @endfor
        </div>

        <div class="actions">
            <a class="button" href="{{ route('admin.portfolio.index') }}">Cancel</a>
            <button class="button primary" type="submit">{{ $isEdit ? 'Save changes' : 'Create project' }}</button>
        </div>
    </form>
</div>
</body>
</html>
