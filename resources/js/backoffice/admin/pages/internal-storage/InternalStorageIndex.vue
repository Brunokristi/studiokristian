<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { errorMessage } from '../../composables/useAdminApi'
import AdminPageHeader from '../../components/AdminPageHeader.vue'
import DocumentEditor from '../../components/DocumentEditor.vue'
import ServiceFileStructure from '../../components/ServiceFileStructure.vue'

const storageFolders = ref([])
const loading = ref(true)
const error = ref('')
const structureSaveTimer = ref(null)
const structureSaving = ref(false)
const route = useRoute()
const router = useRouter()
const storageFolderKey = ref(null)
const syncingDocumentRoute = ref(false)

const storageInitialFolderId = computed(() => {
    if (
        storageFolderKey.value === null ||
        storageFolderKey.value === undefined ||
        String(storageFolderKey.value).trim() === ''
    ) {
        return null
    }

    const key = String(storageFolderKey.value)

    const folder = (storageFolders.value || []).find(
        item =>
            item?.type === 'folder' &&
            (String(item.client_key || '') === key || String(item.id) === key)
    )

    return folder?.id ?? null
})

const documentEditorOpen = ref(false)
const documentTemplate = ref(null)
const documentBlocks = ref({})
const documentSaveInFlight = ref(false)
const documentSaveError = ref('')
const documentSaveRevision = ref(0)
const documentSavedRevision = ref(0)

function storageDocumentRouteKey(item) {
    return String(item?.client_key || item?.id || '').trim()
}

function findStorageDocumentByRouteKey(key) {
    const value = String(key || '').trim()

    if (!value) {
        return null
    }

    return (storageFolders.value || []).find(
        item =>
            item?.type === 'file' &&
            item?.resource_type === 'document' &&
            (String(item.client_key || '') === value || String(item.id || '') === value)
    )
}

async function setStorageDocumentRoute(key) {
    const value = String(key || '').trim()
    const nextQuery = { ...route.query }

    if (value) {
        nextQuery.document = value
    } else {
        delete nextQuery.document
    }

    await router.replace({ query: nextQuery })
}

async function load() {
    try {
        const response = await api.get('/internal-storage')
        storageFolders.value = normalizeFolders(response.data?.folders || [], storageFolders.value || [])
    } catch (exception) {
        error.value = errorMessage(exception)
    } finally {
        loading.value = false
    }
}

onMounted(load)

function isPersistedFolderId(value) {
    if (value === null || value === undefined) {
        return false
    }

    const numeric = Number(value)
    return Number.isInteger(numeric) && numeric > 0
}

function normalizeFolders(serverFolders = [], previousFolders = []) {
    const source = Array.isArray(serverFolders) ? [...serverFolders] : []
    const previous = Array.isArray(previousFolders) ? [...previousFolders] : []

    source.sort((a, b) => Number(a?.sort_order || 0) - Number(b?.sort_order || 0))
    previous.sort((a, b) => Number(a?.sort_order || 0) - Number(b?.sort_order || 0))

    const normalized = source.map((item, index) => {
        const previousItem = previous[index] || null
        const clientKey = previousItem?.client_key || String(item.id)

        return {
            ...item,
            client_key: clientKey,
            parent_client_key: null,
            client_visible: false,
        }
    })

    const idToClientKey = new Map(normalized.map(item => [String(item.id), item.client_key]))

    return normalized.map(item => ({
        ...item,
        parent_client_key:
            item.parent_id !== null && item.parent_id !== undefined
                ? idToClientKey.get(String(item.parent_id)) || String(item.parent_id)
                : null,
    }))
}

function foldersPayloadForSave() {
    const items = (storageFolders.value || []).map(item => ({
        ...item,
        id: isPersistedFolderId(item.id) ? Number(item.id) : null,
        client_key: String(item.client_key || item.id),
        parent_client_key: item.parent_client_key ?? null,
        client_visible: false,
    }))

    const keyById = new Map(items.map(item => [String(item.id), String(item.client_key)]))

    return items.map(item => ({
        ...item,
        parent_client_key:
            item.parent_id !== null && item.parent_id !== undefined
                ? keyById.get(String(item.parent_id)) || String(item.parent_client_key || item.parent_id)
                : null,
    }))
}

function queueStructureSave(value) {
    storageFolders.value = value

    if (structureSaveTimer.value) {
        clearTimeout(structureSaveTimer.value)
    }

    structureSaveTimer.value = setTimeout(() => {
        structureSaveTimer.value = null
        void saveStructure()
    }, 250)
}

async function saveStructure() {
    if (structureSaving.value) {
        return
    }

    structureSaving.value = true

    try {
        const response = await api.put('/internal-storage/structure', {
            folders: foldersPayloadForSave(),
        })

        storageFolders.value = normalizeFolders(response.data?.folders || [], storageFolders.value || [])
    } catch (exception) {
        error.value = errorMessage(exception)
    } finally {
        structureSaving.value = false
    }
}

function normalizeOpenUrl(value) {
    const raw = String(value || '').trim()
    if (!raw) return ''
    if (raw.startsWith('/') || raw.startsWith('#')) return raw
    if (/^[a-z][a-z\d+.-]*:/i.test(raw)) return raw
    return `https://${raw}`
}

function readDocumentEnvelope(content) {
    try {
        const parsed = JSON.parse(String(content || ''))

        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
            return {
                title: String(parsed.title || ''),
                subtitle: String(parsed.subtitle || ''),
                doc: parsed.doc || parsed,
            }
        }
    } catch {
        // legacy content is handled by the editor's own loader
    }

    return {
        title: '',
        subtitle: '',
        doc: content || '',
    }
}

async function openStorageDocument(item, options = {}) {
    const { updateRoute = true } = options

    if (!item?.id) {
        return
    }

    if (updateRoute) {
        const key = storageDocumentRouteKey(item)

        if (key && String(route.query.document || '') !== key) {
            syncingDocumentRoute.value = true

            try {
                await setStorageDocumentRoute(key)
            } finally {
                syncingDocumentRoute.value = false
            }
        }
    }

    const envelope = readDocumentEnvelope(item.content || '')

    storageFolderKey.value = item.parent_client_key ?? item.parent_id ?? null

    documentTemplate.value = {
        id: item.id,
        client_key: item.client_key || String(item.id),
        name: item.name || envelope.title || 'Untitled document',
        title: item.name || envelope.title || 'Untitled document',
        subtitle: item.subtitle || envelope.subtitle || '',
        content: item.content || '',
    }

    documentBlocks.value = envelope.doc
    documentSaveRevision.value = Number(item.document_revision || 0)
    documentSavedRevision.value = documentSaveRevision.value
    documentSaveError.value = ''
    documentEditorOpen.value = true

    window.scrollTo({ top: 0, behavior: 'smooth' })
}

function handleStorageOpenFolder(folder) {
    storageFolderKey.value = folder?.client_key ?? folder?.id ?? null
}

function syncStorageDocumentFromRoute() {
    if (syncingDocumentRoute.value) {
        return
    }

    const routeKey = String(route.query.document || '').trim()

    if (!routeKey) {
        if (documentEditorOpen.value) {
            documentEditorOpen.value = false
        }

        return
    }

    const currentKey = storageDocumentRouteKey(documentTemplate.value)

    if (documentEditorOpen.value && currentKey === routeKey) {
        return
    }

    const match = findStorageDocumentByRouteKey(routeKey)

    if (match) {
        void openStorageDocument(match, { updateRoute: false })
    }
}

function updateDocumentBlocks(value) {
    documentBlocks.value = value
}

function updateDocumentTitle(value) {
    if (!documentTemplate.value) {
        return
    }

    const title = String(value || '').trim()
    documentTemplate.value = {
        ...documentTemplate.value,
        name: title,
        title,
    }
}

function updateDocumentSubtitle(value) {
    if (!documentTemplate.value) {
        return
    }

    documentTemplate.value = {
        ...documentTemplate.value,
        subtitle: String(value || ''),
    }
}

async function saveStorageDocument(template) {
    const payload = template || documentTemplate.value

    if (!payload?.id) {
        return
    }

    const title = String(payload.title || payload.name || 'Untitled document').trim() || 'Untitled document'
    const subtitle = String(payload.subtitle || '')
    const content = JSON.stringify({
        title,
        subtitle,
        doc: payload.document_schema || documentBlocks.value || {},
    })

    const index = (storageFolders.value || []).findIndex(item => String(item.id) === String(payload.id) || String(item.client_key) === String(payload.client_key || ''))

    if (index < 0) {
        documentSaveError.value = 'Document file could not be found in internal storage.'
        error.value = documentSaveError.value
        return
    }

    documentSaveInFlight.value = true
    documentSaveError.value = ''

    try {
        const next = [...storageFolders.value]
        next[index] = {
            ...next[index],
            name: title,
            template_name: title,
            content,
        }

        storageFolders.value = next
        documentBlocks.value = payload.document_schema || documentBlocks.value || {}
        documentTemplate.value = {
            ...documentTemplate.value,
            id: next[index].id,
            client_key: next[index].client_key || payload.client_key,
            name: title,
            title,
            subtitle,
            content,
        }

        documentSaveRevision.value += 1
        documentSavedRevision.value = documentSaveRevision.value

        await saveStructure()
    } catch (exception) {
        documentSaveError.value = errorMessage(exception)
        error.value = documentSaveError.value
    } finally {
        documentSaveInFlight.value = false
    }
}

function handleStorageOpenFile(item) {
    const openUrl = normalizeOpenUrl(item?.open_url || item?.url || '')

    if (openUrl) {
        window.open(openUrl, '_blank', 'noopener,noreferrer')
        return
    }

    if (item?.resource_type === 'link') {
        const openUrl = normalizeOpenUrl(item?.url || '')
        if (openUrl) {
            window.open(openUrl, '_blank', 'noopener,noreferrer')
            return
        }
    }

    error.value = 'This internal storage entry has no binary to open. Use documents or links in this space.'
}

function handleStorageDownloadFile(item) {
    const downloadUrl = String(item?.download_url || '')

    if (downloadUrl) {
        const link = document.createElement('a')
        link.href = downloadUrl
        link.download = String(item?.name || 'download')
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        return
    }

    error.value = 'This internal storage entry has no storage-backed binary to download.'
}

async function handleDocumentBack() {
    documentEditorOpen.value = false

    syncingDocumentRoute.value = true

    try {
        await setStorageDocumentRoute('')
    } finally {
        syncingDocumentRoute.value = false
    }
}

function isPersistedFolder(value) {
    return isPersistedFolderId(value)
}

async function handleStorageFileUpload(payload = {}) {
    const files = Array.from(payload?.files || [])
    if (!files.length) {
        return
    }

    const parent = payload?.parent || null

    let folderId = null
    if (isPersistedFolder(payload?.folderId)) {
        folderId = Number(payload.folderId)
    } else if (parent?.client_key) {
        const match = (storageFolders.value || []).find(item => String(item.client_key) === String(parent.client_key))
        if (isPersistedFolder(match?.id)) {
            folderId = Number(match.id)
        }
    }

    if (payload?.folderId && !folderId) {
        await saveStructure()
        const refreshed = (storageFolders.value || []).find(item => String(item.client_key) === String(parent?.client_key || payload.folderId))
        if (isPersistedFolder(refreshed?.id)) {
            folderId = Number(refreshed.id)
        }
    }

    const maxFilesPerRequest = 20

    try {
        const chunks = []
        for (let offset = 0; offset < files.length; offset += maxFilesPerRequest) {
            chunks.push(files.slice(offset, offset + maxFilesPerRequest))
        }

        for (const chunk of chunks) {
            const body = new FormData()

            chunk.forEach((file, index) => {
                body.append('files[]', file)
                body.append(`relative_paths[${index}]`, file.webkitRelativePath || file.name)
            })

            if (folderId) {
                body.append('folder_id', String(folderId))
            }

            await api.post('/internal-storage/files', body)
        }

        await load()
    } catch (exception) {
        error.value = errorMessage(exception)
    }
}

onBeforeUnmount(() => {
    if (structureSaveTimer.value) {
        clearTimeout(structureSaveTimer.value)
    }
})

watch(
    () => [route.query.document, storageFolders.value.length],
    () => {
        syncStorageDocumentFromRoute()
    },
    {
        immediate: true,
    }
)
</script>

<template>
    <div class="space-y-6">
        <DocumentEditor
            v-if="documentEditorOpen"
            :model-value="documentBlocks"
            :template="documentTemplate"
            :title="documentTemplate?.name || ''"
            :subtitle="documentTemplate?.subtitle || ''"
            :editable="true"
            :saving="documentSaveInFlight"
            :save-revision="documentSaveRevision"
            :saved-revision="documentSavedRevision"
            :save-error="documentSaveError"
            @update:title="updateDocumentTitle"
            @update:subtitle="updateDocumentSubtitle"
            @update:model-value="updateDocumentBlocks"
            @back="handleDocumentBack"
            @save="saveStorageDocument"
        />

        <template v-else>
            <AdminPageHeader
                title="Internal storage"
                eyebrow="Private cloud drive"
            />

            <p v-if="error" class="border border-red-700 bg-red-50 p-4 text-sm text-red-800">{{ error }}</p>
            <p v-if="loading" class="py-20 text-center">Loading...</p>

            <div v-else>
                <ServiceFileStructure
                    :model-value="storageFolders"
                    :initial-folder-id="storageInitialFolderId"
                    :allow-upload-control="true"
                    :allow-metadata-editing="false"
                    :prevent-deleting-required="false"
                    :disabled="structureSaving"
                    @update:model-value="queueStructureSave"
                    @open-folder="handleStorageOpenFolder"
                    @open-document="openStorageDocument"
                    @open-file="handleStorageOpenFile"
                    @download-file="handleStorageDownloadFile"
                    @upload-files="handleStorageFileUpload"
                />
            </div>
        </template>
    </div>
</template>
