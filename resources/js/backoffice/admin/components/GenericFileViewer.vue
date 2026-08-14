<script setup>
import { computed, ref, watch } from 'vue'
import { fileTypeLabel, getFileViewerType } from '../composables/fileViewerType'

const props = defineProps({
    file: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits([
    'close',
    'rename',
    'delete',
    'download',
])

const textContent = ref('')
const loading = ref(false)
const previewError = ref('')

const viewerType = computed(() => getFileViewerType(props.file || {}))
const displayName = computed(() => String(props.file?.name || props.file?.display_name || props.file?.original_filename || 'Untitled file'))
const sourceUrl = computed(() => String(props.file?.open_url || props.file?.local_preview_url || props.file?.url || ''))
const downloadUrl = computed(() => String(props.file?.download_url || props.file?.local_preview_url || sourceUrl.value || ''))

function readableSize(value) {
    const size = Number(value || 0)

    if (size <= 0) {
        return 'Unknown size'
    }

    if (size < 1024) return `${size} B`
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`
    return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

async function loadTextIfNeeded() {
    textContent.value = ''
    previewError.value = ''

    if (viewerType.value !== 'text') {
        return
    }

    if (typeof props.file?.content === 'string' && props.file.content.length > 0) {
        textContent.value = props.file.content
        return
    }

    if (!sourceUrl.value) {
        previewError.value = 'Unable to preview this file.'
        return
    }

    loading.value = true

    try {
        const response = await fetch(sourceUrl.value, { credentials: 'same-origin' })

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`)
        }

        textContent.value = await response.text()
    } catch {
        previewError.value = 'Unable to preview this file.'
    } finally {
        loading.value = false
    }
}

watch(
    () => [props.file?.id, props.file?.open_url, props.file?.local_preview_url],
    () => {
        void loadTextIfNeeded()
    },
    { immediate: true }
)

function download() {
    emit('download', props.file)

    if (!downloadUrl.value) {
        return
    }

    const link = document.createElement('a')
    link.href = downloadUrl.value
    link.download = displayName.value
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}
</script>

<template>
    <div class="fixed inset-0 z-50 grid bg-black/75 p-4" @click.self="emit('close')">
        <div class="m-auto flex h-[90vh] w-full max-w-6xl flex-col bg-white">
            <header class="flex items-center justify-between border-b border-black p-3">
                <div class="min-w-0">
                    <strong class="block truncate">{{ displayName }}</strong>
                    <small class="block text-dark/50">{{ fileTypeLabel(file) }} · {{ readableSize(file?.size) }}</small>
                </div>
                <div class="flex gap-2">
                    <button class="admin-button" @click="download">Download</button>
                    <button class="admin-button" @click="emit('rename', file)">Rename</button>
                    <button class="admin-button text-red-700" @click="emit('delete', file)">Delete</button>
                    <button class="admin-button" @click="emit('close')">Close</button>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-auto p-4">
                <p v-if="loading" class="text-sm text-dark/60">Opening file...</p>
                <p v-else-if="previewError" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ previewError }}</p>

                <img
                    v-else-if="(viewerType === 'svg' || viewerType === 'image') && sourceUrl"
                    :src="sourceUrl"
                    :alt="displayName"
                    class="mx-auto max-h-full w-auto max-w-full object-contain"
                >

                <iframe
                    v-else-if="viewerType === 'pdf' && sourceUrl"
                    :src="sourceUrl"
                    class="h-full min-h-[60vh] w-full border border-dark/10"
                    :title="displayName"
                />

                <pre v-else-if="viewerType === 'text'" class="min-h-[50vh] overflow-auto rounded border border-dark/10 bg-neutral-950 p-4 text-xs text-neutral-100">{{ textContent }}</pre>

                <audio
                    v-else-if="viewerType === 'audio' && sourceUrl"
                    controls
                    class="w-full"
                    :src="sourceUrl"
                />

                <video
                    v-else-if="viewerType === 'video' && sourceUrl"
                    controls
                    class="max-h-[72vh] w-full rounded border border-dark/10 bg-black"
                    :src="sourceUrl"
                />

                <div v-else class="mx-auto max-w-xl rounded border border-dark/20 bg-white p-6">
                    <p class="text-sm font-semibold">Preview is not available for this file type.</p>
                    <p class="mt-2 text-sm text-dark/70">You can still download, rename, or delete this file.</p>
                    <dl class="mt-4 grid grid-cols-[120px_1fr] gap-y-2 text-sm">
                        <dt class="text-dark/60">Filename</dt>
                        <dd class="break-all">{{ displayName }}</dd>
                        <dt class="text-dark/60">Type</dt>
                        <dd>{{ fileTypeLabel(file) }}</dd>
                        <dt class="text-dark/60">Size</dt>
                        <dd>{{ readableSize(file?.size) }}</dd>
                    </dl>
                </div>
            </main>
        </div>
    </div>
</template>
