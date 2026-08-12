<script setup>
import { computed } from 'vue'

import ClientLayout from './layouts/ClientLayout.vue'
import DashboardPage from './pages/DashboardPage.vue'
import ProjectPage from './pages/ProjectPage.vue'
import ContractPage from './pages/ContractPage.vue'
import OfferPage from './pages/OfferPage.vue'

const props = defineProps({
    page: { type: Object, required: true },
})

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''
const pages = {
    dashboard: DashboardPage,
    project: ProjectPage,
    contract: ContractPage,
    offer: OfferPage,
}
const currentPage = computed(() => pages[props.page.page] || DashboardPage)
</script>

<template>
    <ClientLayout :page="page" :csrf-token="csrfToken">
        <component :is="currentPage" :data="page" :csrf-token="csrfToken" />
    </ClientLayout>
</template>
