<script setup>
import { onMounted, ref } from 'vue'
import api, { errorMessage } from '../../composables/useAdminApi'
import AdminPageHeader from '../../components/AdminPageHeader.vue'

const projects = ref([])
const selectedProjectId = ref('')
const loading = ref(true)
const error = ref('')

async function load(projectId = selectedProjectId.value) {
    try {
        const response = await api.get('/internal-storage', {
            params: projectId ? { project_id: projectId } : {},
        })
        projects.value = response.data
    } catch (exception) {
        error.value = errorMessage(exception)
    } finally {
        loading.value = false
    }
}

onMounted(load)

function size(bytes) {
    if (!bytes) return '0 B'
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1048576).toFixed(1)} MB`
}

function formatDate(value) {
    if (!value) return '—'
    return new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
</script>

<template>
    <div class="space-y-6">
        <AdminPageHeader
            title="Internal storage"
            eyebrow="Shared files"
            description="Internal-only project files and shared team documents."
        />

        <div v-if="error" class="border border-red-700 bg-red-50 p-4 text-sm text-red-800">{{ error }}</div>

        <div class="border border-dark bg-light p-4">
            <div class="admin-field max-w-md">
                <label>Filter by project</label>
                <select v-model="selectedProjectId" @change="load(selectedProjectId)">
                    <option value="">All projects</option>
                    <option v-for="project in projects.flatMap((item) => item.id ? [{ id: item.id, name: item.name }] : [])" :key="project.id" :value="project.id">
                        {{ project.name }}
                    </option>
                </select>
            </div>
        </div>

        <div v-if="loading" class="py-10 text-center text-sm">Loading…</div>

        <div v-else class="space-y-4">
            <article v-for="project in projects" :key="project.id" class="border border-dark bg-light p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-mono text-sm font-bold uppercase">{{ project.name }}</h2>
                    <span class="text-xs text-neutral-500">{{ project.files.length }} internal files</span>
                </div>

                <div v-if="project.files.length" class="space-y-2">
                    <div v-for="file in project.files" :key="file.id" class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-3 border-t border-dark pt-2 text-sm">
                        <strong class="truncate">{{ file.display_name }}</strong>
                        <span class="text-neutral-500">{{ file.mime_type || 'file' }}</span>
                        <span class="text-neutral-500">{{ size(file.size) }}</span>
                        <span class="text-neutral-500">{{ formatDate(file.created_at) }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-neutral-500">No internal files for this project yet.</p>
            </article>
        </div>
    </div>
</template>
