<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import api, { errorMessage } from '../composables/useAdminApi'
import FileViewer from './FileViewer.vue'
import { extensionOf, fileTypeLabel, getFileViewerType } from '../composables/fileViewerType'

const props = defineProps({ projectId: { type: [String, Number], required: true } })
const listing = ref({ breadcrumbs: [], folders: [], files: [] })
const folderId = ref(null)
const error = ref('')
const uploading = ref(false)
const clientVisible = ref(true)
const picker = ref(null)
const directoryPicker = ref(null)
const viewerFile = ref(null)
const uploadResults = ref([])
const dragActive = ref(false)
const menuFileId = ref(null)
const MAX_FILES_PER_UPLOAD_REQUEST = 20

async function load(id = null) {
    folderId.value = id
    try {
        listing.value = (await api.get(`/projects/${props.projectId}/files`, { params: { folder_id: id } })).data
    } catch (exception) {
        error.value = errorMessage(exception)
    }
}

onMounted(() => load())
onMounted(() => {
    window.addEventListener('click', handleGlobalClick)
})
onBeforeUnmount(() => {
    window.removeEventListener('click', handleGlobalClick)
})

async function createFolder() {
    const name = prompt('Folder name')
    if (!name) return
    await api.post(`/projects/${props.projectId}/folders`, { parent_id: folderId.value, name, client_visible: clientVisible.value })
    await load(folderId.value)
}

function resetUploadResults() {
    uploadResults.value = []
}

function setUploadProgress(files) {
    uploadResults.value = files.map((file, index) => ({
        index,
        name: file.name,
        size: file.size,
        status: 'uploading',
        message: '',
    }))
}

function updateBatchResults(batchEntries = [], failed = []) {
    const failedByIndex = new Map(
        failed.map(item => [Number(item.index), item])
    )

    uploadResults.value = uploadResults.value.map(item => {
        if (!batchEntries.some(entry => Number(entry.index) === Number(item.index))) {
            return item
        }

        const failedItem = failedByIndex.get(Number(item.index))

        if (failedItem) {
            return {
                ...item,
                status: 'failed',
                message: failedItem.message || 'Upload failed.',
            }
        }

        return {
            ...item,
            status: 'done',
            message: 'Uploaded',
        }
    })
}

function chunkEntries(files = [], size = MAX_FILES_PER_UPLOAD_REQUEST) {
    const entries = files.map((file, index) => ({ file, index }))
    const chunks = []

    for (let offset = 0; offset < entries.length; offset += size) {
        chunks.push(entries.slice(offset, offset + size))
    }

    return chunks
}

async function uploadFromFiles(fileList) {
    const files = [...(fileList || [])]
    if (!files.length) return

    uploading.value = true
    error.value = ''
    setUploadProgress(files)

    let anyCreated = false

    try {
        const batches = chunkEntries(files)

        for (const batch of batches) {
            const body = new FormData()

            batch.forEach((entry, batchIndex) => {
                body.append('files[]', entry.file)
                body.append(`relative_paths[${batchIndex}]`, entry.file.webkitRelativePath || entry.file.name)
            })

            body.append('folder_id', folderId.value || '')
            body.append('client_visible', clientVisible.value ? '1' : '0')

            try {
                const response = await api.post(`/projects/${props.projectId}/files`, body)
                const created = response.data?.data || []
                const failed = response.data?.errors || []

                const normalizedFailed = failed.map(item => {
                    const batchEntry = batch[Number(item.index)] || null

                    return {
                        ...item,
                        index: batchEntry ? batchEntry.index : Number(item.index),
                        name: item.name || batchEntry?.file?.name || 'Unknown file',
                    }
                })

                updateBatchResults(batch, normalizedFailed)

                if (created.length > 0) {
                    anyCreated = true
                }
            } catch (exception) {
                const message = errorMessage(exception)
                error.value = message
                updateBatchResults(batch, batch.map(entry => ({
                    index: entry.index,
                    name: entry.file.name,
                    message,
                })))
            }
        }

        if (anyCreated) {
            await load(folderId.value)
        }
    } finally {
        uploading.value = false
    }
}

async function upload(event) {
    await uploadFromFiles(event.target.files)
    event.target.value = ''
}

function onDrop(event) {
    event.preventDefault()
    dragActive.value = false
    void uploadFromFiles(event.dataTransfer?.files || [])
}

function onDragOver(event) {
    event.preventDefault()
    dragActive.value = true
}

function onDragLeave() {
    dragActive.value = false
}

function openFile(file) {
    viewerFile.value = file
}

function closeViewer() {
    viewerFile.value = null
}

async function download(file) {
    try {
        const response = await api.get(`/projects/${props.projectId}/files/${file.id}/download`, { responseType: 'blob' })
        const url = URL.createObjectURL(response.data)
        const link = document.createElement('a')
        link.href = url
        link.download = file.original_filename || file.display_name || 'download'
        document.body.appendChild(link)
        link.click()
        link.remove()
        URL.revokeObjectURL(url)
    } catch (exception) {
        error.value = `Download failed. ${errorMessage(exception)}`
    }
}

async function renameFile(file) {
    if (!file?.id) {
        return
    }

    const name = window.prompt('Rename file', file.display_name || file.original_filename)

    if (name === null) {
        return
    }

    try {
        const response = await api.patch(`/projects/${props.projectId}/files/${file.id}`, { name })
        updateFileInListing(response.data)

        if (viewerFile.value?.id === file.id) {
            viewerFile.value = response.data
        }
    } catch (exception) {
        error.value = `Rename failed. ${errorMessage(exception)}`
    }
}

async function removeFile(file) {
    if (!file?.id) {
        return
    }

    const confirmed = window.confirm(`Delete ${file.display_name || file.original_filename}?`)
    if (!confirmed) {
        return
    }

    try {
        await api.delete(`/projects/${props.projectId}/files/${file.id}`)
        removeFileFromListing(file.id)

        if (viewerFile.value?.id === file.id) {
            closeViewer()
        }
    } catch (exception) {
        error.value = errorMessage(exception)
    }
}

function updateFileInListing(updatedFile) {
    listing.value = {
        ...listing.value,
        files: (listing.value.files || []).map(file => (file.id === updatedFile.id ? updatedFile : file)),
    }
}

function removeFileFromListing(fileId) {
    listing.value = {
        ...listing.value,
        files: (listing.value.files || []).filter(file => file.id !== fileId),
    }
}

function onViewerRenamed(updatedFile) {
    updateFileInListing(updatedFile)
    viewerFile.value = updatedFile
}

function onViewerDeleted(fileId) {
    removeFileFromListing(fileId)
    closeViewer()
}

function iconForType(file) {
    const type = getFileViewerType(file)

    if (type === 'svg' || type === 'image') return '🖼'
    if (type === 'video') return '🎞'
    if (type === 'audio') return '🎵'
    if (type === 'pdf') return '📄'
    if (type === 'text') return '📃'
    if (type === 'office') return '📝'
    if (type === 'archive') return '🗜'

    return '📦'
}

function formatDate(value) {
    if (!value) {
        return ''
    }

    try {
        return new Date(value).toLocaleDateString()
    } catch {
        return ''
    }
}

function size(bytes) {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1048576).toFixed(1)} MB`
}

function typeBadge(file) {
    const ext = extensionOf(file)
    if (ext) {
        return ext.toUpperCase()
    }

    return fileTypeLabel(file).replace(' file', '').toUpperCase()
}

function toggleFileMenu(fileId) {
    menuFileId.value = menuFileId.value === fileId ? null : fileId
}

function closeFileMenu() {
    menuFileId.value = null
}

function handleGlobalClick() {
    closeFileMenu()
}

function withMenuClosed(action) {
    closeFileMenu()
    action()
}
</script>

<template>
    <div class="space-y-4" @drop="onDrop" @dragover="onDragOver" @dragleave="onDragLeave">
        <div class="flex flex-wrap items-center gap-2 border border-dark bg-light p-3">
            <button class="admin-button" @click="createFolder">New folder</button>
            <button class="admin-button" @click="picker.click()">Upload files</button>
            <button class="admin-button" @click="directoryPicker.click()">Upload folder</button>
            <label class="ml-auto text-sm"><input v-model="clientVisible" type="checkbox"> Client visible</label>
            <input ref="picker" class="hidden" type="file" multiple @change="upload">
            <input ref="directoryPicker" class="hidden" type="file" webkitdirectory multiple @change="upload">
        </div>

        <div
            class="rounded border border-dashed p-4 text-sm"
            :class="dragActive ? 'border-accent bg-accent/5' : 'border-dark/30 bg-light/40'"
        >
            Drop files here to upload into the current folder.
        </div>

        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>

        <div v-if="uploadResults.length" class="space-y-2 rounded border border-dark/20 bg-light p-3">
            <div class="flex items-center justify-between text-xs font-mono font-bold uppercase">
                <span>Upload results</span>
                <button type="button" class="underline" @click="resetUploadResults">Clear</button>
            </div>
            <div v-for="item in uploadResults" :key="`${item.index}-${item.name}`" class="grid grid-cols-[minmax(0,1fr)_90px_90px] items-center gap-3 text-sm">
                <span class="truncate">{{ item.name }}</span>
                <span class="text-xs">{{ size(item.size || 0) }}</span>
                <span
                    class="text-xs font-semibold"
                    :class="item.status === 'done' ? 'text-emerald-700' : item.status === 'failed' ? 'text-red-700' : 'text-dark/60'"
                >
                    {{ item.status === 'done' ? 'Uploaded' : item.status === 'failed' ? item.message : 'Uploading...' }}
                </span>
            </div>
        </div>

        <nav class="flex flex-wrap items-center gap-2 font-mono text-xs font-bold uppercase">
            <button @click="load(null)">Files</button>
            <template v-for="crumb in listing.breadcrumbs" :key="crumb.id"><span>/</span><button @click="load(crumb.id)">{{ crumb.name }}</button></template>
        </nav>
        <div class="border border-dark bg-light">
            <button v-if="folderId" class="grid w-full grid-cols-[32px_1fr] gap-3 border-b p-4 text-left" @click="load(listing.breadcrumbs.at(-2)?.id || null)"><span>↑</span><strong>Parent folder</strong></button>
            <button v-for="folder in listing.folders" :key="folder.id" class="grid w-full grid-cols-[32px_1fr_auto] gap-3 border-b p-4 text-left" @dblclick="load(folder.id)"><span>▣</span><strong>{{ folder.name }}</strong><small>{{ folder.client_visible ? 'Client' : 'Internal' }}</small></button>
            <article v-for="file in listing.files" :key="file.id" class="grid grid-cols-[32px_minmax(0,1fr)_80px_90px_90px_80px_auto] gap-3 border-b p-4">
                <span>{{ iconForType(file) }}</span>
                <button class="truncate text-left font-bold underline" @click="openFile(file)">{{ file.display_name }}</button>
                <small>{{ typeBadge(file) }}</small>
                <small>{{ size(file.size) }}</small>
                <small>{{ formatDate(file.created_at) }}</small>
                <small>{{ file.visibility }}</small>
                <div class="relative flex items-center justify-end" @click.stop>
                    <button class="font-mono text-xs font-bold uppercase" @click="toggleFileMenu(file.id)">•••</button>
                    <div v-if="menuFileId === file.id" class="absolute right-0 top-6 z-20 min-w-[160px] rounded border border-dark/20 bg-white py-1 shadow-xl">
                        <button class="block w-full px-3 py-2 text-left text-sm hover:bg-dark/5" @click="withMenuClosed(() => openFile(file))">Open</button>
                        <button class="block w-full px-3 py-2 text-left text-sm hover:bg-dark/5" @click="withMenuClosed(() => download(file))">Download</button>
                        <button class="block w-full px-3 py-2 text-left text-sm hover:bg-dark/5" @click="withMenuClosed(() => renameFile(file))">Rename</button>
                        <button class="block w-full px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50" @click="withMenuClosed(() => removeFile(file))">Delete</button>
                    </div>
                </div>
            </article>
            <p v-if="!listing.folders.length && !listing.files.length" class="p-14 text-center text-sm text-neutral-500">{{ uploading ? 'Uploading…' : 'This folder is empty.' }}</p>
        </div>

        <FileViewer
            v-if="viewerFile"
            :project-id="props.projectId"
            :file="viewerFile"
            @close="closeViewer"
            @renamed="onViewerRenamed"
            @deleted="onViewerDeleted"
        />
    </div>
</template>
