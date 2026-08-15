<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api, { errorMessage } from '../../composables/useAdminApi'
import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminStatusBadge from '../../components/AdminStatusBadge.vue'
import AdminConfirmDialog from '../../components/AdminConfirmDialog.vue'
import DocumentEditor from '../../components/DocumentEditor.vue'
import ProjectFilesDrive from '../../components/ProjectFilesDrive.vue'
import ServiceFileStructure from '../../components/ServiceFileStructure.vue'

const props = defineProps({ id: String })
const router = useRouter()

const project = ref(null)
const loading = ref(true)
const error = ref('')
const tab = ref('overview')
const confirm = ref(false)
const busy = ref(false)

const tickets = ref([])
const contactOptions = ref([])
const coworker = reactive({ name: '', email: '' })
const ticketForm = reactive({ title: '', description: '', priority: 'normal', assigned_to: null })
const resendingCoworkerId = ref(null)
const resendingContactId = ref(null)

const ticketAssignees = computed(() => {
    const options = []
    const seen = new Set()

    const pushUser = user => {
        const id = Number(user?.id || 0)
        if (!id || seen.has(id)) {
            return
        }

        seen.add(id)
        options.push({
            id,
            name: String(user?.name || 'Unknown user'),
            is_admin: Boolean(user?.is_admin),
        })
    }

    const currentUser = project.value?.current_user || null
    if (currentUser?.is_admin) {
        pushUser(currentUser)
    }

    for (const user of project.value?.coworkers || []) {
        pushUser(user)
    }

    return options
})

const projectFolders = ref([])
const structureSaveTimer = ref(null)
const structureSaving = ref(false)

const documentEditorOpen = ref(false)
const documentTemplate = ref(null)
const documentBlocks = ref({})
const documentSaveInFlight = ref(false)
const documentSaveError = ref('')
const documentSaveRevision = ref(0)
const documentSavedRevision = ref(0)

function isPersistedFolderId(value) {
    if (value === null || value === undefined) {
        return false
    }

    const numeric = Number(value)
    return Number.isInteger(numeric) && numeric > 0
}

function normalizeProjectFolders(serverFolders = [], previousFolders = []) {
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
            client_visible: item.client_visible ?? true,
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
    const items = (projectFolders.value || []).map(item => ({
        ...item,
        id: isPersistedFolderId(item.id) ? Number(item.id) : null,
        client_key: String(item.client_key || item.id),
        parent_client_key: item.parent_client_key ?? null,
        client_visible: item.client_visible ?? true,
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

async function load() {
    try {
        project.value = (await api.get(`/projects/${props.id}`)).data.data
        projectFolders.value = normalizeProjectFolders(project.value?.folders || [], projectFolders.value || [])
        tickets.value = (await api.get(`/projects/${props.id}/tickets`)).data
        contactOptions.value = (await api.get(`/companies/${project.value.company.id}/contacts/options`)).data
    } catch (exception) {
        error.value = errorMessage(exception)
    } finally {
        loading.value = false
    }
}

onMounted(load)

async function archive() {
    busy.value = true
    try {
        await api.post(`/projects/${props.id}/archive`)
        router.push({ name: 'projects.index' })
    } finally {
        busy.value = false
    }
}

async function togglePublishing() {
    const response = await api.put(`/projects/${props.id}/publishing`, { is_published: !project.value.is_published })
    project.value.is_published = response.data.data.is_published
}

async function inviteCoworker() {
    await api.post(`/projects/${props.id}/coworkers`, coworker)
    coworker.name = ''
    coworker.email = ''
    await load()
}

async function inviteContact(contactId) {
    if (!contactId) return
    await api.post(`/projects/${props.id}/contacts/invite`, { contact_id: contactId })
    await load()
}

async function resendCoworkerInvitation(userId) {
    if (!userId || resendingCoworkerId.value) {
        return
    }

    resendingCoworkerId.value = userId

    try {
        await api.post(`/projects/${props.id}/coworkers/${userId}/resend-invitation`)
    } catch (exception) {
        error.value = errorMessage(exception)
    } finally {
        resendingCoworkerId.value = null
    }
}

async function resendContactInvitation(contactId) {
    if (!contactId || resendingContactId.value) {
        return
    }

    resendingContactId.value = contactId

    try {
        await api.post(`/projects/${props.id}/contacts/${contactId}/resend-invitation`)
    } catch (exception) {
        error.value = errorMessage(exception)
    } finally {
        resendingContactId.value = null
    }
}

async function createTicket() {
    await api.post(`/projects/${props.id}/tickets`, ticketForm)
    Object.assign(ticketForm, { title: '', description: '', priority: 'normal', assigned_to: null })
    tickets.value = (await api.get(`/projects/${props.id}/tickets`)).data
}

async function moveTicket(ticket, status) {
    await api.put(`/projects/${props.id}/tickets/${ticket.id}`, { status, priority: ticket.priority, assigned_to: ticket.assigned_to })
    tickets.value = (await api.get(`/projects/${props.id}/tickets`)).data
}

const ticketsFor = status => tickets.value.filter(ticket => ticket.status === status)

function queueStructureSave(value) {
    projectFolders.value = value

    if (structureSaveTimer.value) {
        clearTimeout(structureSaveTimer.value)
    }

    structureSaveTimer.value = setTimeout(() => {
        structureSaveTimer.value = null
        void saveProjectStructure()
    }, 250)
}

async function saveProjectStructure() {
    if (structureSaving.value) {
        return
    }

    structureSaving.value = true

    try {
        const response = await api.put(`/projects/${props.id}/structure`, {
            folders: foldersPayloadForSave(),
        })

        projectFolders.value = normalizeProjectFolders(response.data?.folders || [], projectFolders.value || [])
    } catch (exception) {
        error.value = errorMessage(exception)
    } finally {
        structureSaving.value = false
    }
}

function handleProjectStructureOpenFile(item) {
    if (item?.resource_type === 'link') {
        const openUrl = normalizeOpenUrl(item?.url || '')
        if (openUrl) {
            window.open(openUrl, '_blank', 'noopener,noreferrer')
            return
        }
    }

    error.value = 'This project file entry has no storage-backed file to open. Use Project Files below for uploaded binaries.'
}

function handleProjectStructureDownloadFile(item) {
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

    error.value = 'This project file entry has no storage-backed binary to download. Use Project Files below for uploaded binaries.'
}

function openProjectDocument(item) {
    if (!item?.id) {
        return
    }

    const envelope = readDocumentEnvelope(item.content || '')

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

async function saveProjectDocument(template) {
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

    const index = (projectFolders.value || []).findIndex(item => String(item.id) === String(payload.id) || String(item.client_key) === String(payload.client_key || ''))

    if (index < 0) {
        documentSaveError.value = 'Document file could not be found in the project structure.'
        error.value = documentSaveError.value
        return
    }

    documentSaveInFlight.value = true
    documentSaveError.value = ''

    try {
        const next = [...projectFolders.value]
        next[index] = {
            ...next[index],
            name: title,
            template_name: title,
            content,
        }

        projectFolders.value = next
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

        await saveProjectStructure()
    } catch (exception) {
        documentSaveError.value = errorMessage(exception)
        error.value = documentSaveError.value
    } finally {
        documentSaveInFlight.value = false
    }
}

function handleDocumentBack() {
    documentEditorOpen.value = false
}

onBeforeUnmount(() => {
    if (structureSaveTimer.value) {
        clearTimeout(structureSaveTimer.value)
    }
})
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
            @save="saveProjectDocument"
        />

        <template v-else>
            <AdminPageHeader :title="project?.name || 'Project'" :eyebrow="project?.project_code || 'Project workspace'">
                <template v-if="project">
                    <button class="admin-button" :class="project.is_published ? 'bg-accent' : ''" @click="togglePublishing">
                        {{ project.is_published ? 'Shown on website' : 'Show this project on website' }}
                    </button>
                    <RouterLink class="admin-button" :to="{ name: 'portfolio.edit', params: { id } }">Edit website content</RouterLink>
                    <button class="admin-button" @click="confirm = true">Archive</button>
                    <RouterLink class="admin-button bg-dark text-light" :to="{ name: 'projects.edit', params: { id } }">Edit project</RouterLink>
                </template>
            </AdminPageHeader>

            <p v-if="error" class="border border-red-700 bg-red-50 p-4 text-sm text-red-800">{{ error }}</p>
            <p v-if="loading" class="py-20 text-center">Loading...</p>

            <template v-else-if="project">
                <nav class="flex gap-5 overflow-x-auto border-b border-dark font-mono text-xs font-bold uppercase">
                    <button
                        v-for="item in ['overview', 'tickets', 'files', 'team']"
                        :key="item"
                        class="whitespace-nowrap border-b-4 pb-3 capitalize"
                        :class="tab === item ? 'border-accent' : 'border-transparent'"
                        @click="tab = item"
                    >
                        {{ item }}
                    </button>
                </nav>

                <section v-if="tab === 'overview'" class="grid gap-5 lg:grid-cols-2">
                    <article class="border border-dark bg-light p-6">
                        <div class="flex justify-between">
                            <h2 class="font-mono font-bold uppercase">Project dossier</h2>
                            <AdminStatusBadge :status="project.status" />
                        </div>
                        <dl class="mt-6 grid grid-cols-[120px_1fr] gap-y-3 text-sm">
                            <dt>Client</dt>
                            <dd>
                                <RouterLink :to="{ name: 'clients.show', params: { id: project.company.id } }" class="underline">
                                    {{ project.company.name }}
                                </RouterLink>
                            </dd>
                            <dt>Product</dt>
                            <dd>{{ project.service_product?.name || '—' }}</dd>
                            <dt>Blueprint</dt>
                            <dd>{{ project.blueprint_version?.name }} v{{ project.blueprint_version?.version }}</dd>
                            <dt>Portfolio</dt>
                            <dd>{{ project.is_published ? 'Published' : 'Hidden' }}</dd>
                            <dt>Started</dt>
                            <dd>{{ project.started_at || '—' }}</dd>
                        </dl>
                    </article>

                    <article class="border border-dark bg-light p-6">
                        <h2 class="font-mono font-bold uppercase">Context</h2>
                        <p class="mt-5 whitespace-pre-line text-sm">{{ project.summary || '—' }}</p>
                        <dl class="mt-5 grid grid-cols-2 gap-2 text-sm">
                            <template v-for="(value, key) in project.configuration" :key="key">
                                <dt>{{ key.replaceAll('_', ' ') }}</dt>
                                <dd>{{ Array.isArray(value) ? value.join(', ') : value }}</dd>
                            </template>
                        </dl>
                    </article>
                </section>

                <section v-if="tab === 'tickets'" class="space-y-5">
                    <form class="grid gap-3 border border-dark bg-light p-4 lg:grid-cols-[1fr_1.5fr_140px_180px_auto]" @submit.prevent="createTicket">
                        <div class="admin-field">
                            <label>Title</label>
                            <input v-model="ticketForm.title" required>
                        </div>
                        <div class="admin-field">
                            <label>Description</label>
                            <input v-model="ticketForm.description" required>
                        </div>
                        <div class="admin-field">
                            <label>Priority</label>
                            <select v-model="ticketForm.priority">
                                <option>low</option>
                                <option>normal</option>
                                <option>high</option>
                                <option>urgent</option>
                            </select>
                        </div>
                        <div class="admin-field">
                            <label>Assignee</label>
                            <select v-model="ticketForm.assigned_to">
                                <option :value="null">Unassigned</option>
                                <option v-for="user in ticketAssignees" :key="user.id" :value="user.id">
                                    {{ user.name }}{{ user.is_admin ? ' (admin)' : '' }}
                                </option>
                            </select>
                        </div>
                        <button class="admin-button self-end bg-dark text-light">Add ticket</button>
                    </form>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <section
                            v-for="column in [
                                { key: 'new', label: 'New' },
                                { key: 'in_progress', label: 'In progress' },
                                { key: 'finished', label: 'Finished' },
                            ]"
                            :key="column.key"
                            class="border border-dark bg-light"
                        >
                            <h2 class="border-b border-dark p-4 font-mono text-sm font-bold uppercase">
                                {{ column.label }} · {{ ticketsFor(column.key).length }}
                            </h2>
                            <div class="space-y-3 p-3">
                                <article v-for="ticket in ticketsFor(column.key)" :key="ticket.id" class="border p-4">
                                    <div class="flex justify-between gap-3">
                                        <strong>{{ ticket.title }}</strong>
                                        <small class="uppercase">{{ ticket.priority }}</small>
                                    </div>
                                    <p class="mt-2 text-sm">{{ ticket.description }}</p>
                                    <small class="mt-3 block">
                                        By {{ ticket.creator?.name || `${ticket.client_creator?.first_name || 'Client'} ${ticket.client_creator?.last_name || ''}` }}
                                    </small>
                                    <select class="mt-3 w-full border p-2" :value="ticket.status" @change="moveTicket(ticket, $event.target.value)">
                                        <option value="new">New</option>
                                        <option value="in_progress">In progress</option>
                                        <option value="finished">Finished</option>
                                    </select>
                                </article>
                            </div>
                        </section>
                    </div>
                </section>

                <section v-if="tab === 'files'" class="space-y-5">
                    <article class="border border-dark bg-light p-5">
                        <h2 class="font-mono font-bold uppercase">Project structure</h2>
                        <p class="mt-2 text-sm text-dark/60">Copied from blueprint. You can manage folders, documents and links here. Required items cannot be deleted.</p>
                        <div class="mt-4">
                            <ServiceFileStructure
                                :model-value="projectFolders"
                                :allow-upload-control="false"
                                :allow-metadata-editing="false"
                                :prevent-deleting-required="true"
                                @update:model-value="queueStructureSave"
                                @open-document="openProjectDocument"
                                @open-file="handleProjectStructureOpenFile"
                                @download-file="handleProjectStructureDownloadFile"
                            />
                        </div>
                    </article>
                    <ProjectFilesDrive :project-id="id" />
                </section>

                <section v-if="tab === 'team'" class="grid gap-5 lg:grid-cols-2">
                    <div class="space-y-4">
                        <article class="border border-dark bg-light p-5">
                            <h2 class="font-mono font-bold uppercase">Coworkers</h2>
                            <div v-for="user in project.coworkers" :key="user.id" class="mt-3 border-t pt-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <strong>{{ user.name }}</strong>
                                        <small class="block">{{ user.email }}</small>
                                    </div>
                                    <button
                                        class="admin-button"
                                        :disabled="resendingCoworkerId === user.id"
                                        @click="resendCoworkerInvitation(user.id)"
                                    >
                                        {{ resendingCoworkerId === user.id ? 'Resending…' : 'Resend invitation' }}
                                    </button>
                                </div>
                            </div>
                            <form class="mt-5 grid gap-3" @submit.prevent="inviteCoworker">
                                <div class="admin-field">
                                    <label>Name</label>
                                    <input v-model="coworker.name" required>
                                </div>
                                <div class="admin-field">
                                    <label>Email</label>
                                    <input v-model="coworker.email" type="email" required>
                                </div>
                                <button class="admin-button bg-dark text-light">Invite coworker</button>
                            </form>
                        </article>
                    </div>

                    <article class="border border-dark bg-light p-5">
                        <h2 class="font-mono font-bold uppercase">Client contacts</h2>
                        <div v-for="contact in project.contacts" :key="contact.id" class="mt-3 border-t pt-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <strong>{{ contact.name }}</strong>
                                    <small class="block">{{ contact.email }}</small>
                                </div>
                                <button
                                    class="admin-button"
                                    :disabled="resendingContactId === contact.id"
                                    @click="resendContactInvitation(contact.id)"
                                >
                                    {{ resendingContactId === contact.id ? 'Resending…' : 'Resend invitation' }}
                                </button>
                            </div>
                        </div>
                        <div class="admin-field mt-5">
                            <label>Invite another contact</label>
                            <select @change="inviteContact($event.target.value); $event.target.value = ''">
                                <option value="">Select contact…</option>
                                <option v-for="contact in contactOptions" :key="contact.id" :value="contact.id">
                                    {{ contact.first_name }} {{ contact.last_name }} · {{ contact.email }}
                                </option>
                            </select>
                        </div>
                    </article>
                </section>
            </template>

            <AdminConfirmDialog
                :open="confirm"
                title="Archive project?"
                text="The project remains in historical records."
                confirm-label="Archive project"
                :busy="busy"
                @close="confirm = false"
                @confirm="archive"
            />
        </template>
    </div>
</template>
