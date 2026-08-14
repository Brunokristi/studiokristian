<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import api, { errorMessage } from '../../composables/useAdminApi'
import AdminPageHeader from '../../components/AdminPageHeader.vue'
import ServiceFileStructure from '../../components/ServiceFileStructure.vue'

const storageFolders = ref([])
const loading = ref(true)
const error = ref('')
const structureSaveTimer = ref(null)
const structureSaving = ref(false)

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
            // Company private storage is always internal-only.
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

function handleOpenFile(item) {
    if (item?.resource_type === 'document') {
        error.value = 'This is a simple company cloud drive view. Document editing is disabled here.'
        return
    }

    if (item?.resource_type === 'link') {
        const openUrl = normalizeOpenUrl(item?.url || '')
        if (openUrl) {
            window.open(openUrl, '_blank', 'noopener,noreferrer')
            return
        }
    }

    error.value = 'This company storage entry is structure-only. Use documents and links in this space.'
}

function handleDownloadFile() {
    error.value = 'Downloads are not available for structure-only company storage entries.'
}

onBeforeUnmount(() => {
    if (structureSaveTimer.value) {
        clearTimeout(structureSaveTimer.value)
    }
})
</script>

<template>
    <div class="space-y-6">
        <AdminPageHeader
            title="Internal storage"
            eyebrow="Private cloud drive"
            description="Admin-only private cloud drive for your internal documents."
        />

        <div v-if="error" class="border border-red-700 bg-red-50 p-4 text-sm text-red-800">{{ error }}</div>

        <article class="space-y-5 border border-dark bg-light p-5">
            <div v-if="loading" class="py-10 text-center text-sm">Loading...</div>

            <div v-else>
                <ServiceFileStructure
                    :model-value="storageFolders"
                    :allow-upload-control="false"
                    :allow-metadata-editing="true"
                    :prevent-deleting-required="false"
                    :disabled="structureSaving"
                    @update:model-value="queueStructureSave"
                    @open-document="handleOpenFile"
                    @open-file="handleOpenFile"
                    @download-file="handleDownloadFile"
                />
            </div>
        </article>
    </div>
</template>
