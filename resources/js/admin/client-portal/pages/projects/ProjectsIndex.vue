<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '../../composables/useAdminApi'
import { useServerTable } from '../../composables/useServerTable'
import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminDataTable from '../../components/AdminDataTable.vue'
import AdminPagination from '../../components/AdminPagination.vue'
import AdminStatusBadge from '../../components/AdminStatusBadge.vue'

const router = useRouter()
const lookups = ref({ companies: [], service_products: [] })
const columns = [{key:'name',label:'Project',sortable:true},{key:'company',label:'Client'},{key:'service_product',label:'Product'},{key:'contacts_count',label:'Contacts'},{key:'status',sortKey:'portal_status',label:'Status',sortable:true},{key:'updated_at',label:'Updated',sortable:true}]
const { rows, meta, loading, error, state, sortBy } = useServerTable('/projects', { company_id: '', service_product_id: '' })
onMounted(async () => { lookups.value = (await api.get('/lookups')).data })
</script>

<template>
  <div class="space-y-6">
    <AdminPageHeader title="Projects" eyebrow="Client work" description="Operational project dossiers linked to a client and service product."><RouterLink class="admin-button bg-dark text-light" :to="{name:'projects.create'}">New project</RouterLink></AdminPageHeader>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_180px_220px_220px]">
      <div class="admin-field"><label for="project-search">Search</label><input id="project-search" v-model="state.search" type="search" placeholder="Project, code or client" @input="state.page=1"></div>
      <div class="admin-field"><label for="project-status">Status</label><select id="project-status" v-model="state.status" @change="state.page=1"><option value="">All statuses</option><option value="draft">Draft</option><option value="active">Active</option><option value="on_hold">On hold</option><option value="completed">Completed</option><option value="archived">Archived</option></select></div>
      <div class="admin-field"><label for="project-client">Client</label><select id="project-client" v-model="state.company_id" @change="state.page=1"><option value="">All clients</option><option v-for="company in lookups.companies" :key="company.id" :value="company.id">{{company.display_name||company.name}}</option></select></div>
      <div class="admin-field"><label for="project-product">Service product</label><select id="project-product" v-model="state.service_product_id" @change="state.page=1"><option value="">All products</option><option v-for="product in lookups.service_products" :key="product.id" :value="product.id">{{product.name}}</option></select></div>
    </div>
    <p v-if="error" class="text-sm text-red-700">{{error}}</p>
    <AdminDataTable :columns="columns" :rows="rows" :loading="loading" :sort="state.sort" :direction="state.direction" empty-title="No projects yet." empty-text="Create a project and assign it to a client." @sort="sortBy" @row-click="row=>router.push({name:'projects.show',params:{id:row.id}})">
      <template #cell-name="{row}"><strong>{{row.name}}</strong><small v-if="row.project_code" class="mt-1 block font-mono text-neutral-500">{{row.project_code}}</small></template>
      <template #cell-company="{value}">{{value?.name||'—'}}</template><template #cell-service_product="{value}">{{value?.name||'—'}}</template>
      <template #cell-status="{value}"><AdminStatusBadge :status="value" /></template><template #cell-updated_at="{value}">{{new Date(value).toLocaleDateString()}}</template>
      <template #empty-action><RouterLink class="admin-button bg-dark text-light" :to="{name:'projects.create'}">Create project</RouterLink></template>
    </AdminDataTable>
    <AdminPagination :meta="meta" @change="page=>state.page=page" />
  </div>
</template>