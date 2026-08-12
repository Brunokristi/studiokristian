<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api, { errorMessage } from '../../composables/useAdminApi'
import AdminPageHeader from '../../components/AdminPageHeader.vue'

const projects = ref([])
const error = ref('')
const loading = ref(true)

onMounted(async () => {
    try { projects.value = (await api.get('/portfolio')).data }
    catch (exception) { error.value = errorMessage(exception) }
    finally { loading.value = false }
})
</script>

<template>
    <div class="space-y-6">
        <AdminPageHeader title="Portfolio" eyebrow="Website publishing" description="Every portfolio entry is an existing project. Choose what appears publicly and edit its website content." />
        <p v-if="error" class="admin-error">{{ error }}</p>
        <p v-if="loading">Loading…</p>
        <div v-else class="border border-dark bg-light">
            <article v-for="project in projects" :key="project.id" class="grid gap-3 border-b border-dark p-4 last:border-0 md:grid-cols-[minmax(0,1fr)_120px_120px_140px_auto] md:items-center">
                <div><strong>{{ project.name }}</strong><small class="mt-1 block text-neutral-500">{{ project.company || 'No client' }} · /{{ project.url }}</small></div>
                <span class="font-mono text-xs font-bold uppercase" :class="project.is_published ? 'text-green-700' : 'text-neutral-500'">{{ project.is_published ? 'Published' : 'Hidden' }}</span>
                <small>{{ project.images_count }} images</small><small>{{ project.features_count }} features</small>
                <RouterLink class="admin-button" :to="{ name: 'portfolio.edit', params: { id: project.id } }">Edit content</RouterLink>
            </article>
            <p v-if="!projects.length" class="p-12 text-center">No projects yet. Create a project first.</p>
        </div>
    </div>
</template>