<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api, { errorMessage } from '../composables/useAdminApi'
import AdminPageHeader from '../components/AdminPageHeader.vue'
import AdminStatusBadge from '../components/AdminStatusBadge.vue'

const data = ref({ counts: {}, recent_projects: [], recent_clients: [] })
const loading = ref(true)
const error = ref('')
onMounted(async () => { try { data.value = (await api.get('/dashboard')).data } catch (e) { error.value = errorMessage(e) } finally { loading.value = false } })
</script>

<template>
  <div class="space-y-8">
    <AdminPageHeader title="Client Portal" eyebrow="Admin dashboard" description="Companies, contacts, projects and the services you sell.">
      <RouterLink class="admin-button admin-button--quiet" :to="{ name: 'service-products.index', query: { create: 1 } }">New service product</RouterLink>
      <RouterLink class="admin-button admin-button--quiet" :to="{ name: 'projects.create' }">New project</RouterLink>
      <RouterLink class="admin-button bg-dark text-light" :to="{ name: 'clients.create' }">New client</RouterLink>
    </AdminPageHeader>
    <p v-if="error" class="border border-red-700 bg-red-50 p-4 text-sm text-red-800">{{ error }}</p>
    <section class="grid grid-cols-2 gap-px border border-dark bg-dark lg:grid-cols-4" aria-label="Summary">
      <article v-for="item in [{k:'active_clients',l:'Active clients'},{k:'active_projects',l:'Active projects'},{k:'active_service_products',l:'Active products'},{k:'portal_contacts',l:'Portal contacts'}]" :key="item.k" class="min-h-36 bg-light p-5 sm:min-h-44 sm:p-6"><p class="font-mono text-xs font-bold uppercase">{{ item.l }}</p><p class="mt-8 font-mono text-4xl font-bold">{{ loading ? '—' : data.counts[item.k] }}</p></article>
    </section>
    <div class="grid gap-8 xl:grid-cols-2">
      <section><div class="mb-3 flex items-end justify-between"><h2 class="font-mono text-lg font-bold uppercase">Recent projects</h2><RouterLink :to="{name:'projects.index'}" class="text-xs font-bold uppercase">View all →</RouterLink></div><div class="border border-dark bg-light"><RouterLink v-for="project in data.recent_projects" :key="project.id" :to="{name:'projects.show',params:{id:project.id}}" class="grid grid-cols-[1fr_auto] gap-4 border-b border-neutral-300 p-4 last:border-0"><div><strong>{{ project.name }}</strong><p class="mt-1 text-xs text-neutral-600">{{ project.company?.name }} · {{ project.service_product?.name }}</p></div><AdminStatusBadge :status="project.status" /></RouterLink><p v-if="!loading && !data.recent_projects.length" class="p-8 text-center text-sm text-neutral-600">No projects yet.</p></div></section>
      <section><div class="mb-3 flex items-end justify-between"><h2 class="font-mono text-lg font-bold uppercase">Recent clients</h2><RouterLink :to="{name:'clients.index'}" class="text-xs font-bold uppercase">View all →</RouterLink></div><div class="border border-dark bg-light"><RouterLink v-for="client in data.recent_clients" :key="client.id" :to="{name:'clients.show',params:{id:client.id}}" class="grid grid-cols-[1fr_auto] gap-4 border-b border-neutral-300 p-4 last:border-0"><div><strong>{{ client.display_label }}</strong><p class="mt-1 text-xs text-neutral-600">{{ client.contacts_count }} contacts · {{ client.projects_count }} projects</p></div><AdminStatusBadge :status="client.status" /></RouterLink><p v-if="!loading && !data.recent_clients.length" class="p-8 text-center text-sm text-neutral-600">No clients yet.</p></div></section>
    </div>
  </div>
</template>