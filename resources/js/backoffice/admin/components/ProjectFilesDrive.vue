<script setup>
import { onMounted, ref } from 'vue'
import api, { errorMessage } from '../composables/useAdminApi'

const props = defineProps({ projectId: { type: [String, Number], required: true } })
const listing = ref({ breadcrumbs: [], folders: [], files: [] })
const folderId = ref(null)
const error = ref('')
const uploading = ref(false)
const clientVisible = ref(true)
const picker = ref(null)
const directoryPicker = ref(null)
const preview = ref(null)

async function load(id = null) {
    folderId.value = id
    try {
        listing.value = (await api.get(`/projects/${props.projectId}/files`, { params: { folder_id: id } })).data
    } catch (exception) {
        error.value = errorMessage(exception)
    }
}

onMounted(() => load())

async function createFolder() {
    const name = prompt('Folder name')
    if (!name) return
    await api.post(`/projects/${props.projectId}/folders`, { parent_id: folderId.value, name, client_visible: clientVisible.value })
    await load(folderId.value)
}

async function upload(event) {
    const files = [...event.target.files]
    if (!files.length) return
    uploading.value = true
    const body = new FormData()
    files.forEach((file, index) => {
        body.append('files[]', file)
        body.append(`relative_paths[${index}]`, file.webkitRelativePath || file.name)
    })
    body.append('folder_id', folderId.value || '')
    body.append('client_visible', clientVisible.value ? '1' : '0')
    try {
        await api.post(`/projects/${props.projectId}/files`, body)
        await load(folderId.value)
    } catch (exception) {
        error.value = errorMessage(exception)
    } finally {
        uploading.value = false
        event.target.value = ''
    }
}

function canPreview(file) {
    return file.mime_type?.startsWith('image/') || ['application/pdf', 'text/plain'].includes(file.mime_type)
}

async function openPreview(file) {
    if (!canPreview(file)) return download(file)
    const response = await api.get(`/projects/${props.projectId}/files/${file.id}/preview`, { responseType: 'blob' })
    preview.value = { file, url: URL.createObjectURL(response.data) }
}

function closePreview() {
    if (preview.value) URL.revokeObjectURL(preview.value.url)
    preview.value = null
}

async function download(file) {
    const response = await api.get(`/projects/${props.projectId}/files/${file.id}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = file.original_filename
    link.click()
    URL.revokeObjectURL(url)
}

function size(bytes) {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1048576).toFixed(1)} MB`
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2 border border-dark bg-light p-3">
            <button class="admin-button" @click="createFolder">New folder</button>
            <button class="admin-button" @click="picker.click()">Upload files</button>
            <button class="admin-button" @click="directoryPicker.click()">Upload folder</button>
            <label class="ml-auto text-sm"><input v-model="clientVisible" type="checkbox"> Client visible</label>
            <input ref="picker" class="hidden" type="file" multiple @change="upload">
            <input ref="directoryPicker" class="hidden" type="file" webkitdirectory multiple @change="upload">
        </div>
        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
        <nav class="flex flex-wrap items-center gap-2 font-mono text-xs font-bold uppercase">
            <button @click="load(null)">Files</button>
            <template v-for="crumb in listing.breadcrumbs" :key="crumb.id"><span>/</span><button @click="load(crumb.id)">{{ crumb.name }}</button></template>
        </nav>
        <div class="border border-dark bg-light">
            <button v-if="folderId" class="grid w-full grid-cols-[32px_1fr] gap-3 border-b p-4 text-left" @click="load(listing.breadcrumbs.at(-2)?.id || null)"><span>↑</span><strong>Parent folder</strong></button>
            <button v-for="folder in listing.folders" :key="folder.id" class="grid w-full grid-cols-[32px_1fr_auto] gap-3 border-b p-4 text-left" @dblclick="load(folder.id)"><span>▣</span><strong>{{ folder.name }}</strong><small>{{ folder.client_visible ? 'Client' : 'Internal' }}</small></button>
            <article v-for="file in listing.files" :key="file.id" class="grid grid-cols-[32px_minmax(0,1fr)_100px_80px_auto] gap-3 border-b p-4">
                <span>□</span><button class="truncate text-left font-bold underline" @click="openPreview(file)">{{ file.display_name }}</button><small>{{ size(file.size) }}</small><small>{{ file.visibility }}</small><button class="font-mono text-xs font-bold uppercase" @click="download(file)">Download</button>
            </article>
            <p v-if="!listing.folders.length && !listing.files.length" class="p-14 text-center text-sm text-neutral-500">{{ uploading ? 'Uploading…' : 'This folder is empty.' }}</p>
        </div>
        <div v-if="preview" class="fixed inset-0 z-50 grid bg-black/75 p-4" @click.self="closePreview">
            <div class="m-auto flex h-[90vh] w-full max-w-6xl flex-col bg-white">
                <header class="flex items-center justify-between border-b border-black p-3"><strong>{{ preview.file.display_name }}</strong><div class="flex gap-2"><button class="admin-button" @click="download(preview.file)">Download</button><button class="admin-button" @click="closePreview">Close</button></div></header>
                <img v-if="preview.file.mime_type.startsWith('image/')" :src="preview.url" class="min-h-0 flex-1 object-contain p-4" :alt="preview.file.display_name">
                <iframe v-else :src="preview.url" class="min-h-0 flex-1" :title="preview.file.display_name"></iframe>
            </div>
        </div>
    </div>
</template>
