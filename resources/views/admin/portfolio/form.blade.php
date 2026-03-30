@php
    $isEdit = $project !== null;

    $nameTranslations = $project?->name_translations ?? [];
    $summaryTranslations = $project?->summary_translations ?? [];
    $oldImages = old('images', $images);
    $oldFeatures = old('features', $features);
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
        .list { display: flex; flex-direction: column; gap: 12px; }
        .item {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            background: #10161d;
        }
        .item-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .ghost {
            background: transparent;
            border: 1px dashed var(--border);
            color: var(--text);
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer;
            font-weight: 600;
        }
        .remove-btn {
            border: 1px solid #a4282d;
            color: #ff938f;
            background: transparent;
            border-radius: 8px;
            padding: 7px 10px;
            cursor: pointer;
            font-size: 12px;
        }
        .row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 120px;
            gap: 10px;
        }
        .field-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }
        .translate-btn {
            border: 1px solid var(--border);
            background: #0e1a2c;
            color: var(--text);
            border-radius: 8px;
            padding: 6px 9px;
            cursor: pointer;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 700;
        }
        .translate-all {
            margin-bottom: 12px;
        }
        @media (max-width: 900px) {
            .row-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1 class="title">{{ $isEdit ? 'Edit Project' : 'Create Project' }}</h1>
        </div>
        <a class="button" href="{{ route('admin.portfolio.index') }}">Back</a>
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

    <form method="POST" action="{{ $isEdit ? route('admin.portfolio.update', $project) : route('admin.portfolio.store') }}" enctype="multipart/form-data">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="panel">
            <h2 class="section-title">Project</h2>
            <div class="row">
                <div>
                    <div class="field-head">
                        <label for="name">Name (EN)</label>
                    </div>
                    <input id="name" name="name" type="text" value="{{ old('name', $project?->name) }}" required data-translate-source>
                </div>
                <div>
                    <div class="field-head">
                        <label for="name_sk">Name (SK)</label>
                    </div>
                    <input id="name_sk" name="name_sk" type="text" value="{{ old('name_sk', data_get($nameTranslations, 'sk', '')) }}" data-translate-target>
                    <button type="button" class="translate-btn" data-translate-pair="#name|#name_sk">Translate</button>

                </div>
                <div>
                    <label for="live_url">Live project URL</label>
                    <input id="live_url" name="live_url" type="url" value="{{ old('live_url', $project?->live_url) }}" placeholder="https://project-domain.com">
                </div>
                <div>
                    <label for="hex_color">Brand color</label>
                    <input id="hex_color" name="hex_color" type="text" value="{{ old('hex_color', $project?->hex_color) }}" placeholder="#133EB4">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label for="logo_file">Logo upload</label>
                    <input id="logo_file" name="logo_file" type="file" accept="image/*">
                    <input type="hidden" name="existing_logo_path" value="{{ old('existing_logo_path', $project?->logo_path) }}">
                    <p class="helper">Current logo: {{ old('existing_logo_path', $project?->logo_path ?? 'none') }}</p>
                </div>
                <div>
                    <div class="field-head">
                        <label for="summary">Description (EN)</label>
                    </div>
                    <textarea id="summary" name="summary" data-translate-source>{{ old('summary', $project?->summary) }}</textarea>
                </div>
                <div>
                    <div class="field-head">
                        <label for="summary_sk">Description (SK)</label>
                        <button type="button" class="translate-btn" data-translate-pair="#summary|#summary_sk">Translate</button>
                    </div>
                    <textarea id="summary_sk" name="summary_sk" data-translate-target>{{ old('summary_sk', data_get($summaryTranslations, 'sk', '')) }}</textarea>
                </div>
            </div>
        </div>

        <div class="panel">
            <h2 class="section-title">Images</h2>
            <div id="images-list" class="list">
                @foreach($oldImages as $i => $image)
                <div class="item" data-image-item>
                    <div class="item-head">
                        <strong>Image</strong>
                        <button type="button" class="remove-btn" data-remove-image>Remove</button>
                    </div>

                    <input type="hidden" name="images[{{ $i }}][existing_path]" value="{{ data_get($image, 'existing_path', data_get($image, 'path', '')) }}" data-image-existing>

                    <div class="row-3">
                        <div>
                            <label>File upload</label>
                            <input name="images[{{ $i }}][file]" type="file" accept="image/*">
                        </div>
                        <div>
                            <div class="field-head">
                                <label>Description (EN)</label>
                            </div>
                            <input name="images[{{ $i }}][description]" type="text" value="{{ data_get($image, 'description', '') }}" data-translate-source>
                        </div>
                        <div>
                            <label>Sort</label>
                            <input name="images[{{ $i }}][sort_order]" type="number" min="0" value="{{ data_get($image, 'sort_order', 0) }}">
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <div class="field-head">
                                <label>Description (SK)</label>
                                <button type="button" class="translate-btn" data-translate-inline="image">Translate</button>
                            </div>
                            <input name="images[{{ $i }}][description_sk]" type="text" value="{{ data_get($image, 'description_sk', '') }}" placeholder="auto-translated if empty" data-translate-target>
                        </div>
                        <div>
                            <label>Existing image path</label>
                            <input type="text" value="{{ data_get($image, 'existing_path', data_get($image, 'path', '')) }}" readonly>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <button type="button" class="ghost" id="add-image">+ Add image</button>
        </div>

        <div class="panel">
            <h2 class="section-title">Features</h2>
            <p class="small">No fixed count. Add as many feature blocks as you need.</p>
            <div id="features-list" class="list">
                @foreach($oldFeatures as $i => $feature)
                <div class="item" data-feature-item>
                    <div class="item-head">
                        <strong>Feature</strong>
                        <button type="button" class="remove-btn" data-remove-feature>Remove</button>
                    </div>

                    <div class="row-3">
                        <div>
                            <div class="field-head">
                                <label>Title (EN)</label>
                            </div>
                            <input name="features[{{ $i }}][title]" type="text" value="{{ data_get($feature, 'title', '') }}" data-translate-source>
                        </div>
                        <div>
                            <div class="field-head">
                                <label>Title (SK)</label>
                                <button type="button" class="translate-btn" data-translate-inline="feature-title">Translate</button>
                            </div>
                            <input name="features[{{ $i }}][title_sk]" type="text" value="{{ data_get($feature, 'title_sk', '') }}" placeholder="auto-translated if empty" data-translate-target>
                        </div>
                        <div>
                            <label>Sort</label>
                            <input name="features[{{ $i }}][sort_order]" type="number" min="0" value="{{ data_get($feature, 'sort_order', 0) }}">
                        </div>
                    </div>

                    <div class="row">
                        <div>
                            <div class="field-head">
                                <label>Description (EN)</label>
                            </div>
                            <textarea name="features[{{ $i }}][description]" data-translate-source>{{ data_get($feature, 'description', '') }}</textarea>
                        </div>
                        <div>
                            <div class="field-head">
                                <label>Description (SK)</label>
                                <button type="button" class="translate-btn" data-translate-inline="feature-description">Translate</button>
                            </div>
                            <textarea name="features[{{ $i }}][description_sk]" placeholder="auto-translated if empty" data-translate-target>{{ data_get($feature, 'description_sk', '') }}</textarea>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <button type="button" class="ghost" id="add-feature">+ Add feature</button>
        </div>

        <div class="actions">
            <a class="button" href="{{ route('admin.portfolio.index') }}">Cancel</a>
            <button class="button primary" type="submit">{{ $isEdit ? 'Save changes' : 'Create project' }}</button>
        </div>
    </form>
</div>

<template id="image-template">
    <div class="item" data-image-item>
        <div class="item-head">
            <strong>Image</strong>
            <button type="button" class="remove-btn" data-remove-image>Remove</button>
        </div>

        <input type="hidden" data-name="images[__INDEX__][existing_path]" value="" data-image-existing>

        <div class="row-3">
            <div>
                <label>File upload</label>
                <input data-name="images[__INDEX__][file]" type="file" accept="image/*">
            </div>
            <div>
                <div class="field-head">
                    <label>Description (EN)</label>
                </div>
                <input data-name="images[__INDEX__][description]" type="text" data-translate-source>
            </div>
            <div>
                <label>Sort</label>
                <input data-name="images[__INDEX__][sort_order]" type="number" min="0" value="0">
            </div>
        </div>

        <div class="row">
            <div>
                <div class="field-head">
                    <label>Description (SK)</label>
                    <button type="button" class="translate-btn" data-translate-inline="image">Translate</button>
                </div>
                <input data-name="images[__INDEX__][description_sk]" type="text" placeholder="auto-translated if empty" data-translate-target>
            </div>
            <div>
                <label>Existing image path</label>
                <input type="text" value="(new upload)" readonly>
            </div>
        </div>
    </div>
</template>

<template id="feature-template">
    <div class="item" data-feature-item>
        <div class="item-head">
            <strong>Feature</strong>
            <button type="button" class="remove-btn" data-remove-feature>Remove</button>
        </div>

        <div class="row-3">
            <div>
                <div class="field-head">
                    <label>Title (EN)</label>
                </div>
                <input data-name="features[__INDEX__][title]" type="text" data-translate-source>
            </div>
            <div>
                <div class="field-head">
                    <label>Title (SK)</label>
                    <button type="button" class="translate-btn" data-translate-inline="feature-title">Translate</button>
                </div>
                <input data-name="features[__INDEX__][title_sk]" type="text" placeholder="auto-translated if empty" data-translate-target>
            </div>
            <div>
                <label>Sort</label>
                <input data-name="features[__INDEX__][sort_order]" type="number" min="0" value="0">
            </div>
        </div>

        <div class="row">
            <div>
                <div class="field-head">
                    <label>Description (EN)</label>
                </div>
                <textarea data-name="features[__INDEX__][description]" data-translate-source></textarea>
            </div>
            <div>
                <div class="field-head">
                    <label>Description (SK)</label>
                    <button type="button" class="translate-btn" data-translate-inline="feature-description">Translate</button>
                </div>
                <textarea data-name="features[__INDEX__][description_sk]" placeholder="auto-translated if empty" data-translate-target></textarea>
            </div>
        </div>
    </div>
</template>

<script>
    (() => {
        const imagesList = document.getElementById('images-list');
        const featuresList = document.getElementById('features-list');
        const imageTemplate = document.getElementById('image-template');
        const featureTemplate = document.getElementById('feature-template');
        const addImageButton = document.getElementById('add-image');
        const addFeatureButton = document.getElementById('add-feature');
        const translateAllButton = document.getElementById('translate-all');
        const csrfToken = '{{ csrf_token() }}';
        const translateEndpoint = '{{ route('admin.portfolio.translate') }}';

        let imageIndex = imagesList.querySelectorAll('[data-image-item]').length;
        let featureIndex = featuresList.querySelectorAll('[data-feature-item]').length;

        function bindRemoveButtons(container) {
            container.querySelectorAll('[data-remove-image]').forEach((button) => {
                button.addEventListener('click', () => {
                    const item = button.closest('[data-image-item]');
                    item?.remove();
                });
            });

            container.querySelectorAll('[data-remove-feature]').forEach((button) => {
                button.addEventListener('click', () => {
                    const item = button.closest('[data-feature-item]');
                    item?.remove();
                });
            });
        }

        async function translateText(text) {
            if (!text || !text.trim()) {
                return '';
            }

            const response = await fetch(translateEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ text }),
            });

            if (!response.ok) {
                return text;
            }

            const payload = await response.json();
            return typeof payload.translated === 'string' && payload.translated.trim() !== ''
                ? payload.translated
                : text;
        }

        async function translatePair(sourceEl, targetEl) {
            if (!sourceEl || !targetEl) {
                return;
            }

            const sourceText = sourceEl.value || sourceEl.textContent || '';
            const translated = await translateText(sourceText);
            targetEl.value = translated;
        }

        async function handleInlineTranslate(button) {
            const item = button.closest('[data-image-item], [data-feature-item]');
            if (!item) {
                return;
            }

            if (button.dataset.translateInline === 'image') {
                const source = item.querySelector('input[name*="[description]"]');
                const target = item.querySelector('input[name*="[description_sk]"]');
                await translatePair(source, target);
                return;
            }

            if (button.dataset.translateInline === 'feature-title') {
                const source = item.querySelector('input[name*="[title]"]');
                const target = item.querySelector('input[name*="[title_sk]"]');
                await translatePair(source, target);
                return;
            }

            if (button.dataset.translateInline === 'feature-description') {
                const source = item.querySelector('textarea[name*="[description]"]');
                const target = item.querySelector('textarea[name*="[description_sk]"]');
                await translatePair(source, target);
            }
        }

        function applyInputNames(scope, index) {
            scope.querySelectorAll('[data-name]').forEach((input) => {
                input.setAttribute('name', input.getAttribute('data-name').replace('__INDEX__', index));
            });
        }

        addImageButton.addEventListener('click', () => {
            const fragment = imageTemplate.content.cloneNode(true);
            const item = fragment.querySelector('[data-image-item]');
            applyInputNames(item, imageIndex++);
            bindRemoveButtons(item);
            imagesList.appendChild(item);
        });

        addFeatureButton.addEventListener('click', () => {
            const fragment = featureTemplate.content.cloneNode(true);
            const item = fragment.querySelector('[data-feature-item]');
            applyInputNames(item, featureIndex++);
            bindRemoveButtons(item);
            featuresList.appendChild(item);
        });

        translateAllButton.addEventListener('click', async () => {
            translateAllButton.disabled = true;
            translateAllButton.textContent = 'Translating...';

            const pairs = [
                ['#name', '#name_sk'],
                ['#summary', '#summary_sk'],
            ];

            for (const [sourceSelector, targetSelector] of pairs) {
                const source = document.querySelector(sourceSelector);
                const target = document.querySelector(targetSelector);
                await translatePair(source, target);
            }

            const inlineButtons = document.querySelectorAll('[data-translate-inline]');
            for (const button of inlineButtons) {
                await handleInlineTranslate(button);
            }

            translateAllButton.disabled = false;
            translateAllButton.textContent = 'Translate all EN -> SK';
        });

        document.addEventListener('click', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const pairButton = target.closest('[data-translate-pair]');
            if (pairButton instanceof HTMLElement) {
                const [sourceSelector, targetSelector] = pairButton.dataset.translatePair.split('|');
                const source = document.querySelector(sourceSelector);
                const out = document.querySelector(targetSelector);
                await translatePair(source, out);
                return;
            }

            const inlineButton = target.closest('[data-translate-inline]');
            if (inlineButton instanceof HTMLElement) {
                await handleInlineTranslate(inlineButton);
            }
        });

        bindRemoveButtons(document);
    })();
</script>
</body>
</html>
