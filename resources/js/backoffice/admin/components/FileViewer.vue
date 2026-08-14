<script setup>
import { computed, ref, watch } from 'vue'
import api, { errorMessage } from '../composables/useAdminApi'
import { fileTypeLabel, getFileViewerType } from '../composables/fileViewerType'

const props = defineProps({
    projectId: { type: [String, Number], required: true },
    file: { type: Object, required: true },
})

const emit = defineEmits(['close', 'renamed', 'deleted'])

const loading = ref(false)
const actionLoading = ref('')
const error = ref('')
const objectUrl = ref('')
const textContent = ref('')

const viewerType = computed(() => getFileViewerType(props.file))
const canInlinePreview = computed(() => ['svg', 'image', 'pdf', 'text', 'audio', 'video'].includes(viewerType.value))
const displayName = computed(() => props.file?.display_name || props.file?.original_filename || 'Untitled file')
const displayType = computed(() => fileTypeLabel(props.file))

function readableSize(bytes) {
    const value = Number(bytes || 0)

    if (value < 1024) return `${value} B`
    if (value < 1048576) return `${(value / 1024).toFixed(1)} KB`
    return `${(value / 1048576).toFixed(1)} MB`
}

function clearPreviewData() {
    error.value = ''
    textContent.value = ''

    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value)
        objectUrl.value = ''
    }
}

async function loadPreview() {
    clearPreviewData()

    if (!canInlinePreview.value || !props.file?.id) {
        return
    }

    loading.value = true

    try {
        const response = await api.get(`/projects/${props.projectId}/files/${props.file.id}/open`, {
            responseType: 'blob',
        })

        if (viewerType.value === 'text') {
            textContent.value = await response.data.text()
            return
        }

        objectUrl.value = URL.createObjectURL(response.data)
    } catch (exception) {
        error.value = `Unable to preview this file. ${errorMessage(exception)}`
    } finally {
        loading.value = false
    }
}

async function downloadFile() {
    if (!props.file?.id || actionLoading.value) {
        return
    }

    actionLoading.value = 'download'
    error.value = ''

    try {
        const response = await api.get(`/projects/${props.projectId}/files/${props.file.id}/download`, {
            responseType: 'blob',
        })

        const url = URL.createObjectURL(response.data)
        const link = document.createElement('a')
        link.href = url
        link.download = props.file.original_filename || props.file.display_name || 'download'
        document.body.appendChild(link)
        link.click()
        link.remove()
        URL.revokeObjectURL(url)
    } catch (exception) {
        error.value = `Download failed. ${errorMessage(exception)}`
    } finally {
        actionLoading.value = ''
    }
}

async function renameFile() {
    if (!props.file?.id || actionLoading.value) {
        return
    }

    const proposed = window.prompt('Rename file', displayName.value)

    if (proposed === null) {
        return
    }

    actionLoading.value = 'rename'
    error.value = ''

    try {
        const response = await api.patch(`/projects/${props.projectId}/files/${props.file.id}`, {
            name: proposed,
        })

        emit('renamed', response.data)
    } catch (exception) {
        error.value = `Rename failed. ${errorMessage(exception)}`
    } finally {
        actionLoading.value = ''
    }
}

async function deleteFile() {
    if (!props.file?.id || actionLoading.value) {
        return
    }

    const confirmed = window.confirm(`Delete "${displayName.value}"? This cannot be undone.`)

    if (!confirmed) {
        return
    }

    actionLoading.value = 'delete'
    error.value = ''

    try {
        await api.delete(`/projects/${props.projectId}/files/${props.file.id}`)
        emit('deleted', props.file.id)
    } catch (exception) {
        error.value = `Delete failed. ${errorMessage(exception)}`
    } finally {
        actionLoading.value = ''
    }
}

watch(
    () => props.file?.id,
    () => {
        void loadPreview()
    },
    { immediate: true }
)

watch(
    () => props.file,
    () => {
        error.value = ''
    }
)
</script>

<template>
    <div class="fixed inset-0 z-50 grid bg-black/75 p-3 sm:p-6" @click.self="emit('close')">
        <div class="mx-auto flex h-[94vh] w-full max-w-6xl flex-col rounded border border-dark bg-light">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-dark/20 p-3 sm:p-4">
                <div class="min-w-0">
                    <h2 class="truncate text-base font-semibold">{{ displayName }}</h2>
                    <p class="text-xs text-dark/60">{{ displayType }} · {{ readableSize(file.size) }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="admin-button" :disabled="actionLoading === 'download'" @click="downloadFile">{{ actionLoading === 'download' ? 'Downloading...' : 'Download' }}</button>
                    <button class="admin-button" :disabled="actionLoading === 'rename'" @click="renameFile">{{ actionLoading === 'rename' ? 'Saving...' : 'Rename' }}</button>
                    <button class="admin-button text-red-700" :disabled="actionLoading === 'delete'" @click="deleteFile">{{ actionLoading === 'delete' ? 'Deleting...' : 'Delete' }}</button>
                    <button class="admin-button" @click="emit('close')">Close</button>
                </div>
            </header>

            <main class="min-h-0 flex-1 overflow-auto p-3 sm:p-4">
                <p v-if="loading" class="text-sm text-dark/60">Opening file...</p>
                <p v-else-if="error" class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>

                <template v-else-if="viewerType === 'svg' || viewerType === 'image'">
                    <img v-if="objectUrl" :src="objectUrl" :alt="displayName" class="mx-auto max-h-full w-auto max-w-full object-contain">
                </template>

                <template v-else-if="viewerType === 'pdf'">
                    <iframe v-if="objectUrl" :src="objectUrl" :title="displayName" class="h-full min-h-[60vh] w-full rounded border border-dark/10" />
                </template>

                <template v-else-if="viewerType === 'text'">
                    <pre class="min-h-[50vh] overflow-auto rounded border border-dark/10 bg-neutral-950 p-4 text-xs text-neutral-100">{{ textContent }}</pre>
                </template>

                <template v-else-if="viewerType === 'audio'">
                    <div class="space-y-4">
                        <audio v-if="objectUrl" controls class="w-full" :src="objectUrl" />
                        <p class="text-sm text-dark/60">Audio preview uses your browser's native player.</p>
                    </div>
                </template>

                <template v-else-if="viewerType === 'video'">
                    <div class="space-y-4">
                        <video v-if="objectUrl" controls class="max-h-[72vh] w-full rounded border border-dark/10 bg-black" :src="objectUrl" />
                        <p class="text-sm text-dark/60">Video preview uses your browser's native player and depends on codec support.</p>
                    </div>
                </template>

                <template v-else>
                    <div class="mx-auto mt-6 max-w-xl rounded border border-dark/20 bg-white p-6">
                        <p class="text-sm font-semibold">Preview is not available for this file type.</p>
                        <p class="mt-2 text-sm text-dark/70">The file is still stored safely and can always be downloaded.</p>
                        <dl class="mt-4 grid grid-cols-[140px_1fr] gap-y-2 text-sm">
                            <dt class="text-dark/60">Filename</dt>
                            <dd class="break-all">{{ displayName }}</dd>
                            <dt class="text-dark/60">Type</dt>
                            <dd>{{ displayType }}</dd>
                            <dt class="text-dark/60">Size</dt>
                            <dd>{{ readableSize(file.size) }}</dd>
                            <dt class="text-dark/60">MIME</dt>
                            <dd class="break-all">{{ file.mime_type || 'application/octet-stream' }}</dd>
                        </dl>
                    </div>
                </template>
            </main>
        </div>
    </div>
</template>
