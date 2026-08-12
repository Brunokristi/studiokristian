<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'

const api = axios.create({ baseURL: '/workspace/api', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
const projects = ref([])
const selectedId = ref(null)
const loading = ref(true)
const error = ref('')
const preview = ref(null)
const form = reactive({ title: '', description: '', priority: 'normal' })
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''
const selected = computed(() => projects.value.find(project => project.id === selectedId.value))
const ticketsFor = status => selected.value?.tickets.filter(ticket => ticket.status === status) || []

async function load() {
    try {
        projects.value = (await api.get('/projects')).data
        if (!selectedId.value && projects.value.length) selectedId.value = projects.value[0].id
    } catch (exception) { error.value = exception.response?.data?.message || 'Could not load your projects.' }
    finally { loading.value = false }
}

onMounted(load)

async function createTicket() {
    await api.post(`/projects/${selectedId.value}/tickets`, form)
    Object.assign(form, { title: '', description: '', priority: 'normal' })
    await load()
}

async function move(ticket, status) {
    await api.put(`/projects/${selectedId.value}/tickets/${ticket.id}`, { status })
    await load()
}

function fileUrl(file) { return `/workspace/api/projects/${selectedId.value}/files/${file.id}` }
function canPreview(file) { return file.mime_type?.startsWith('image/') || ['application/pdf', 'text/plain'].includes(file.mime_type) }
function openFile(file) { if (canPreview(file)) preview.value = file; else window.location.href = fileUrl(file) }
</script>

<template>
    <div class="min-h-screen bg-[#f5f5f2] text-dark">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-dark bg-light px-5"><strong class="font-mono text-sm uppercase">Studio Kristian / Workspace</strong><form method="POST" action="/logout"><input type="hidden" name="_token" :value="csrfToken"><button class="font-mono text-xs font-bold uppercase">Log out</button></form></header>
        <main class="mx-auto max-w-[1600px] p-4 sm:p-8">
            <p v-if="error" class="border border-red-700 bg-red-50 p-3 text-red-700">{{ error }}</p>
            <p v-if="loading" class="py-20 text-center">Loading…</p>
            <template v-else-if="selected">
                <div class="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p class="font-mono text-xs font-bold uppercase">Assigned project</p><h1 class="mt-2 font-mono text-3xl font-bold">{{ selected.name }}</h1></div><select v-model="selectedId" class="min-w-64 border border-dark bg-white p-3"><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option></select></div>
                <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                    <section><div class="grid gap-4 lg:grid-cols-3"><article v-for="column in [{key:'new',label:'New'},{key:'in_progress',label:'In progress'},{key:'finished',label:'Finished'}]" :key="column.key" class="border border-dark bg-light"><h2 class="border-b border-dark p-4 font-mono text-xs font-bold uppercase">{{ column.label }} · {{ ticketsFor(column.key).length }}</h2><div class="space-y-3 p-3"><div v-for="ticket in ticketsFor(column.key)" :key="ticket.id" class="border border-neutral-400 p-4"><div class="flex justify-between gap-2"><strong>{{ ticket.title }}</strong><small class="uppercase">{{ ticket.priority }}</small></div><p class="mt-2 text-sm">{{ ticket.description }}</p><small class="mt-3 block text-neutral-500">{{ ticket.client_creator ? `Client: ${ticket.client_creator.first_name} ${ticket.client_creator.last_name}` : `Staff: ${ticket.creator?.name || 'Unknown'}` }}</small><select class="mt-3 w-full border p-2" :value="ticket.status" @change="move(ticket, $event.target.value)"><option value="new">New</option><option value="in_progress">In progress</option><option value="finished">Finished</option></select></div></div></article></div></section>
                    <aside class="space-y-5"><form class="border border-dark bg-light p-4" @submit.prevent="createTicket"><h2 class="font-mono text-sm font-bold uppercase">New ticket</h2><label class="mt-4 block text-xs font-bold uppercase">Title<input v-model="form.title" class="mt-1 w-full border p-2" required></label><label class="mt-3 block text-xs font-bold uppercase">Description<textarea v-model="form.description" class="mt-1 w-full border p-2" rows="4" required></textarea></label><label class="mt-3 block text-xs font-bold uppercase">Priority<select v-model="form.priority" class="mt-1 w-full border p-2"><option>low</option><option>normal</option><option>high</option><option>urgent</option></select></label><button class="mt-4 border border-dark bg-dark px-4 py-2 font-mono text-xs font-bold uppercase text-white">Create ticket</button></form><section class="border border-dark bg-light p-4"><h2 class="font-mono text-sm font-bold uppercase">Files</h2><button v-for="file in selected.files" :key="file.id" class="mt-3 block w-full truncate text-left underline" @click="openFile(file)">{{ file.display_name }}</button><p v-if="!selected.files.length" class="mt-3 text-sm text-neutral-500">No files uploaded.</p></section></aside>
                </div>
            </template>
            <p v-else class="py-20 text-center">No projects are assigned to you.</p>
        </main>
        <div v-if="preview" class="fixed inset-0 z-50 grid bg-black/75 p-4" @click.self="preview = null"><div class="m-auto flex h-[90vh] w-full max-w-6xl flex-col bg-white"><header class="flex justify-between border-b p-3"><strong>{{ preview.display_name }}</strong><div class="flex gap-4"><a :href="fileUrl(preview)" download>Download</a><button @click="preview = null">Close</button></div></header><img v-if="preview.mime_type.startsWith('image/')" :src="fileUrl(preview)" class="min-h-0 flex-1 object-contain p-4" :alt="preview.display_name"><iframe v-else :src="fileUrl(preview)" class="min-h-0 flex-1" :title="preview.display_name"></iframe></div></div>
    </div>
</template>