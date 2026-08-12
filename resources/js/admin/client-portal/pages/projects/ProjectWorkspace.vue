<script setup>
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api, { errorMessage } from '../../composables/useAdminApi'
import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminStatusBadge from '../../components/AdminStatusBadge.vue'
import AdminConfirmDialog from '../../components/AdminConfirmDialog.vue'
import ProjectFilesDrive from '../../components/ProjectFilesDrive.vue'

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

async function load() {
    try {
        project.value = (await api.get(`/projects/${props.id}`)).data.data
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
    try { await api.post(`/projects/${props.id}/archive`); router.push({ name: 'projects.index' }) } finally { busy.value = false }
}

async function togglePublishing() {
    const response = await api.put(`/projects/${props.id}/publishing`, { is_published: !project.value.is_published })
    project.value.is_published = response.data.data.is_published
}

async function updateDeliverable(item) {
    await api.put(`/projects/${props.id}/deliverables/${item.id}`, { status: item.status, notes: item.notes, sort_order: item.sort_order })
    await load()
}

async function inviteCoworker() {
    await api.post(`/projects/${props.id}/coworkers`, coworker)
    coworker.name = ''; coworker.email = ''
    await load()
}

async function inviteContact(contactId) {
    if (!contactId) return
    await api.post(`/projects/${props.id}/contacts/invite`, { contact_id: contactId })
    await load()
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
</script>

<template>
    <div class="space-y-6">
        <AdminPageHeader :title="project?.name || 'Project'" :eyebrow="project?.project_code || 'Project workspace'">
            <template v-if="project">
                <button class="admin-button" :class="project.is_published ? 'bg-accent' : ''" @click="togglePublishing">{{ project.is_published ? 'Shown on website' : 'Show this project on website' }}</button>
                <RouterLink class="admin-button" :to="{ name: 'portfolio.edit', params: { id } }">Edit website content</RouterLink>
                <button class="admin-button" @click="confirm = true">Archive</button>
                <RouterLink class="admin-button bg-dark text-light" :to="{ name: 'projects.edit', params: { id } }">Edit project</RouterLink>
            </template>
        </AdminPageHeader>
        <p v-if="error" class="border border-red-700 bg-red-50 p-4 text-sm text-red-800">{{ error }}</p>
        <p v-if="loading" class="py-20 text-center">Loading...</p>
        <template v-else-if="project">
            <nav class="flex gap-5 overflow-x-auto border-b border-dark font-mono text-xs font-bold uppercase">
                <button v-for="item in ['overview', 'deliverables', 'tickets', 'files', 'contract', 'team']" :key="item" class="whitespace-nowrap border-b-4 pb-3 capitalize" :class="tab === item ? 'border-accent' : 'border-transparent'" @click="tab = item">{{ item }}</button>
            </nav>
            <section v-if="tab === 'overview'" class="grid gap-5 lg:grid-cols-2">
                <article class="border border-dark bg-light p-6"><div class="flex justify-between"><h2 class="font-mono font-bold uppercase">Project dossier</h2><AdminStatusBadge :status="project.status" /></div><dl class="mt-6 grid grid-cols-[120px_1fr] gap-y-3 text-sm"><dt>Client</dt><dd><RouterLink :to="{ name: 'clients.show', params: { id: project.company.id } }" class="underline">{{ project.company.name }}</RouterLink></dd><dt>Product</dt><dd>{{ project.service_product?.name || '—' }}</dd><dt>Blueprint</dt><dd>{{ project.blueprint_version?.name }} v{{ project.blueprint_version?.version }}</dd><dt>Portfolio</dt><dd>{{ project.is_published ? 'Published' : 'Hidden' }}</dd><dt>Started</dt><dd>{{ project.started_at || '—' }}</dd></dl></article>
                <article class="border border-dark bg-light p-6"><h2 class="font-mono font-bold uppercase">Context</h2><p class="mt-5 whitespace-pre-line text-sm">{{ project.summary || '—' }}</p><dl class="mt-5 grid grid-cols-2 gap-2 text-sm"><template v-for="(value, key) in project.configuration" :key="key"><dt>{{ key.replaceAll('_', ' ') }}</dt><dd>{{ Array.isArray(value) ? value.join(', ') : value }}</dd></template></dl></article>
            </section>
            <section v-if="tab === 'deliverables'" class="space-y-3"><article v-for="item in project.deliverables" :key="item.id" class="grid gap-4 border border-dark bg-light p-4 lg:grid-cols-[1fr_180px_1fr_auto]"><div><strong>{{ item.name_snapshot }}</strong><small class="block uppercase text-neutral-500">{{ item.requirement_level }} · {{ item.expected_resource_type }}</small></div><div class="admin-field"><label>Status</label><select v-model="item.status"><option>pending</option><option>in_progress</option><option>completed</option><option v-if="item.requirement_level !== 'required'">waived</option></select></div><div class="admin-field"><label>Notes</label><textarea v-model="item.notes" rows="2"></textarea></div><button class="admin-button self-end" @click="updateDeliverable(item)">Save</button></article></section>
            <section v-if="tab === 'tickets'" class="space-y-5"><form class="grid gap-3 border border-dark bg-light p-4 lg:grid-cols-[1fr_1.5fr_140px_180px_auto]" @submit.prevent="createTicket"><div class="admin-field"><label>Title</label><input v-model="ticketForm.title" required></div><div class="admin-field"><label>Description</label><input v-model="ticketForm.description" required></div><div class="admin-field"><label>Priority</label><select v-model="ticketForm.priority"><option>low</option><option>normal</option><option>high</option><option>urgent</option></select></div><div class="admin-field"><label>Assignee</label><select v-model="ticketForm.assigned_to"><option :value="null">Unassigned</option><option v-for="user in project.coworkers" :key="user.id" :value="user.id">{{ user.name }}</option></select></div><button class="admin-button self-end bg-dark text-light">Add ticket</button></form><div class="grid gap-4 lg:grid-cols-3"><section v-for="column in [{key:'new',label:'New'},{key:'in_progress',label:'In progress'},{key:'finished',label:'Finished'}]" :key="column.key" class="border border-dark bg-light"><h2 class="border-b border-dark p-4 font-mono text-sm font-bold uppercase">{{ column.label }} · {{ ticketsFor(column.key).length }}</h2><div class="space-y-3 p-3"><article v-for="ticket in ticketsFor(column.key)" :key="ticket.id" class="border p-4"><div class="flex justify-between gap-3"><strong>{{ ticket.title }}</strong><small class="uppercase">{{ ticket.priority }}</small></div><p class="mt-2 text-sm">{{ ticket.description }}</p><small class="mt-3 block">By {{ ticket.creator?.name || `${ticket.client_creator?.first_name || 'Client'} ${ticket.client_creator?.last_name || ''}` }}</small><select class="mt-3 w-full border p-2" :value="ticket.status" @change="moveTicket(ticket, $event.target.value)"><option value="new">New</option><option value="in_progress">In progress</option><option value="finished">Finished</option></select></article></div></section></div></section>
            <ProjectFilesDrive v-if="tab === 'files'" :project-id="id" />
            <section v-if="tab === 'contract'" class="space-y-3"><article v-for="contract in project.contracts" :key="contract.id" class="border border-dark bg-light p-6"><div class="flex justify-between"><h2 class="font-mono font-bold uppercase">{{ contract.title || 'Project contract' }}</h2><AdminStatusBadge :status="contract.status" /></div><p class="mt-3">Version {{ contract.version }}</p></article><p v-if="!project.contracts.length">No contract instance.</p></section>
            <section v-if="tab === 'team'" class="grid gap-5 lg:grid-cols-2"><div class="space-y-4"><article class="border border-dark bg-light p-5"><h2 class="font-mono font-bold uppercase">Coworkers</h2><div v-for="user in project.coworkers" :key="user.id" class="mt-3 border-t pt-3"><strong>{{ user.name }}</strong><small class="block">{{ user.email }}</small></div><form class="mt-5 grid gap-3" @submit.prevent="inviteCoworker"><div class="admin-field"><label>Name</label><input v-model="coworker.name" required></div><div class="admin-field"><label>Email</label><input v-model="coworker.email" type="email" required></div><button class="admin-button bg-dark text-light">Invite coworker</button></form></article></div><article class="border border-dark bg-light p-5"><h2 class="font-mono font-bold uppercase">Client contacts</h2><div v-for="contact in project.contacts" :key="contact.id" class="mt-3 border-t pt-3"><strong>{{ contact.name }}</strong><small class="block">{{ contact.email }}</small></div><div class="admin-field mt-5"><label>Invite another contact</label><select @change="inviteContact($event.target.value); $event.target.value = ''"><option value="">Select contact…</option><option v-for="contact in contactOptions" :key="contact.id" :value="contact.id">{{ contact.first_name }} {{ contact.last_name }} · {{ contact.email }}</option></select></div></article></section>
        </template>
        <AdminConfirmDialog :open="confirm" title="Archive project?" text="The project remains in historical records." confirm-label="Archive project" :busy="busy" @close="confirm = false" @confirm="archive" />
    </div>
</template>
