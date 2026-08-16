<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'

const api = axios.create({
    baseURL: '/portal/api',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
})

const loading = ref(true)
const projectLoading = ref(false)
const error = ref('')
const me = ref(null)
const projects = ref([])
const selectedProject = ref(null)
const selectedProjectId = ref(null)
const preview = ref(null)

const ticketForm = reactive({
    title: '',
    description: '',
    priority: 'normal',
})

const selectedRow = computed(() => projects.value.find(project => project.id === selectedProjectId.value) || null)

function signatureLabel(project) {
    if (!project?.signature) {
        return 'No signature tracking'
    }

    if (project.signature.complete) {
        return 'All signed'
    }

    if (project.signature.pending > 0) {
        return `${project.signature.pending} pending`
    }

    return `${project.signature.signed}/${project.signature.required} signed`
}

async function loadMe() {
    me.value = (await api.get('/me')).data
}

async function loadProjects() {
    projects.value = (await api.get('/projects')).data

    if (!selectedProjectId.value && projects.value.length) {
        selectedProjectId.value = projects.value[0].id
    }
}

async function openProject(projectId) {
    if (!projectId) {
        selectedProject.value = null
        return
    }

    projectLoading.value = true

    try {
        selectedProject.value = (await api.get(`/projects/${projectId}`)).data
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not load project details.'
    } finally {
        projectLoading.value = false
    }
}

async function loadPortalData() {
    loading.value = true
    error.value = ''

    try {
        await loadMe()
        await loadProjects()
        await openProject(selectedProjectId.value)
    } catch (exception) {
        error.value = exception.response?.data?.message || 'Could not load portal data.'
    } finally {
        loading.value = false
    }
}

onMounted(loadPortalData)

async function handleOpenProject(project) {
    selectedProjectId.value = project.id
    await openProject(project.id)
}

async function createTicket() {
    if (!selectedProjectId.value) {
        return
    }

    await api.post(`/projects/${selectedProjectId.value}/tickets`, ticketForm)
    Object.assign(ticketForm, { title: '', description: '', priority: 'normal' })

    await Promise.all([
        openProject(selectedProjectId.value),
        loadProjects(),
    ])
}

async function updateTicketStatus(ticket, status) {
    if (!selectedProjectId.value) {
        return
    }

    await api.put(`/projects/${selectedProjectId.value}/tickets/${ticket.id}`, { status })
    await openProject(selectedProjectId.value)
}

async function signDocument(document) {
    if (!selectedProjectId.value) {
        return
    }

    await api.post(`/projects/${selectedProjectId.value}/documents/${document.id}/sign`)
    await Promise.all([
        openProject(selectedProjectId.value),
        loadProjects(),
    ])
}

function canPreview(file) {
    return file.mime_type?.startsWith('image/') || ['application/pdf', 'text/plain'].includes(file.mime_type)
}

function openFile(file) {
    if (canPreview(file)) {
        preview.value = file
        return
    }

    window.location.href = file.download_url
}
</script>

<template>
    <div class="space-y-8">
        <p v-if="error" class="border border-red-700 bg-red-50 p-3 text-red-700">{{ error }}</p>

        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-dark pb-5">
            <div>
                <p class="font-mono text-xs uppercase text-dark/55">Portal role</p>
                <h1 class="mt-2 font-mono text-3xl font-bold uppercase">{{ me?.role || 'portal' }}</h1>
            </div>
        </div>

        <section class="border border-dark bg-light">
            <header class="border-b border-dark p-4 font-mono text-xs font-bold uppercase">Projects</header>

            <p v-if="loading" class="p-6 text-sm">Loading projects...</p>

            <table v-else class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-dark bg-dark/5 text-left text-xs uppercase">
                        <th class="p-3">Project</th>
                        <th class="p-3">Client</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Signature</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="project in projects" :key="project.id" class="border-b border-dark/20">
                        <td class="p-3">
                            <strong>{{ project.name }}</strong>
                            <p class="mt-1 text-xs text-dark/55">{{ project.project_code || 'No code' }}</p>
                        </td>

                        <td class="p-3 text-sm">{{ project.company?.name || '-' }}</td>
                        <td class="p-3 text-sm uppercase">{{ project.status }}</td>
                        <td class="p-3 text-sm">{{ signatureLabel(project) }}</td>

                        <td class="p-3 text-right">
                            <button class="border border-dark px-3 py-1 text-xs font-bold uppercase" type="button" @click="handleOpenProject(project)">
                                Open
                            </button>
                        </td>
                    </tr>

                    <tr v-if="!projects.length">
                        <td colspan="5" class="p-6 text-sm text-dark/55">No accessible projects.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-if="selectedProject" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <header class="border border-dark bg-light p-4">
                    <h2 class="font-mono text-2xl font-bold">{{ selectedProject.name }}</h2>
                    <p class="mt-1 text-sm text-dark/60">{{ selectedProject.company?.name || '' }} · {{ selectedProject.status }}</p>
                    <p class="mt-2 font-mono text-xs uppercase">
                        Signature: {{ selectedProject.signature?.signed || 0 }}/{{ selectedProject.signature?.required || 0 }} signed
                    </p>
                </header>

                <article class="border border-dark bg-light p-4">
                    <h3 class="font-mono text-sm font-bold uppercase">Documents</h3>
                    <div class="mt-4 space-y-3">
                        <div v-for="document in selectedProject.documents" :key="document.id" class="border border-dark/20 bg-white p-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <strong>{{ document.name }}</strong>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs uppercase">
                                        {{ document.signed_at ? 'Signed' : (document.requires_signature ? 'Signature required' : 'No signature required') }}
                                    </span>

                                    <button
                                        v-if="document.can_sign && !document.signed_at"
                                        type="button"
                                        class="border border-dark px-2 py-1 text-xs font-bold uppercase"
                                        @click="signDocument(document)"
                                    >
                                        Sign document
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-if="!selectedProject.documents.length" class="text-sm text-dark/55">No project documents.</p>
                    </div>
                </article>

                <article class="border border-dark bg-light p-4">
                    <h3 class="font-mono text-sm font-bold uppercase">Tickets</h3>
                    <div class="mt-4 space-y-3">
                        <div v-for="ticket in selectedProject.tickets" :key="ticket.id" class="border border-dark/20 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <strong>{{ ticket.title }}</strong>
                                    <p class="mt-1 text-sm">{{ ticket.description }}</p>
                                </div>
                                <span class="font-mono text-xs uppercase">{{ ticket.status }}</span>
                            </div>

                            <select
                                v-if="selectedProject.permissions?.edit"
                                class="mt-3 w-full border border-dark/40 p-2 text-sm"
                                :value="ticket.status"
                                @change="updateTicketStatus(ticket, $event.target.value)"
                            >
                                <option value="new">New</option>
                                <option value="in_progress">In progress</option>
                                <option value="finished">Finished</option>
                            </select>
                        </div>

                        <p v-if="!selectedProject.tickets.length" class="text-sm text-dark/55">No tickets yet.</p>
                    </div>
                </article>
            </div>

            <aside class="space-y-6">
                <section class="border border-dark bg-light p-4">
                    <h3 class="font-mono text-sm font-bold uppercase">Files</h3>
                    <div class="mt-4 space-y-2">
                        <button v-for="file in selectedProject.files" :key="file.id" type="button" class="block w-full truncate border border-dark/20 bg-white p-2 text-left text-sm" @click="openFile(file)">
                            {{ file.display_name }}
                        </button>
                        <p v-if="!selectedProject.files.length" class="text-sm text-dark/55">No files available.</p>
                    </div>
                </section>

                <form
                    v-if="selectedProject.permissions?.create_ticket"
                    class="border border-dark bg-light p-4"
                    @submit.prevent="createTicket"
                >
                    <h3 class="font-mono text-sm font-bold uppercase">Create ticket</h3>

                    <input v-model="ticketForm.title" class="mt-4 w-full border border-dark/40 p-2" placeholder="Title" required>
                    <textarea v-model="ticketForm.description" class="mt-3 min-h-28 w-full border border-dark/40 p-2" placeholder="Describe your issue" required></textarea>
                    <select v-model="ticketForm.priority" class="mt-3 w-full border border-dark/40 p-2">
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>

                    <button type="submit" class="mt-4 border border-dark bg-dark px-4 py-2 font-mono text-xs font-bold uppercase text-white">
                        Submit
                    </button>
                </form>
            </aside>
        </section>

        <div v-if="projectLoading" class="text-sm text-dark/60">Loading project...</div>

        <div v-if="preview" class="fixed inset-0 z-50 grid bg-black/75 p-4" @click.self="preview = null">
            <div class="m-auto flex h-[90vh] w-full max-w-6xl flex-col bg-white">
                <header class="flex justify-between border-b p-3">
                    <strong>{{ preview.display_name }}</strong>
                    <div class="flex gap-4">
                        <a :href="preview.download_url">Download</a>
                        <button @click="preview = null">Close</button>
                    </div>
                </header>

                <img
                    v-if="preview.mime_type.startsWith('image/')"
                    :src="preview.open_url"
                    class="min-h-0 flex-1 object-contain p-4"
                    :alt="preview.display_name"
                >

                <iframe
                    v-else
                    :src="preview.open_url"
                    class="min-h-0 flex-1"
                    :title="preview.display_name"
                />
            </div>
        </div>
    </div>
</template>