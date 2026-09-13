<script setup>
import { computed } from 'vue'

import ClientLayout from './layouts/ClientLayout.vue'
import DashboardPage from './pages/DashboardPage.vue'
import ProjectPage from './pages/ProjectPage.vue'
import { useClientLocale } from './composables/useClientLocale'

const props = defineProps({
    page: { type: Object, required: true },
})

const {
    locale,
    setLocale,
} = useClientLocale()

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''
const pages = {
    dashboard: DashboardPage,
    project: ProjectPage,
}
const currentPage = computed(() => pages[props.page.page] || DashboardPage)
</script>

<template>
    <ClientLayout
        :page="page"
        :csrf-token="csrfToken"
        :locale="locale"
        @set-locale="setLocale"
    >
        <component
            :is="currentPage"
            :data="page"
            :csrf-token="csrfToken"
            :locale="locale"
        />
    </ClientLayout>
</template>
