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
const isProjectPage = computed(() => props.page.page === 'project')
</script>

<template>
    <ProjectPage
        v-if="isProjectPage"
        :page="page"
        :data="page"
        :csrf-token="csrfToken"
        :locale="locale"
        @set-locale="setLocale"
    />

    <ClientLayout
        v-else
        :page="page"
        :csrf-token="csrfToken"
        :locale="locale"
        @set-locale="setLocale"
    >
        <component
            :is="currentPage"
            :page="page"
            :data="page"
            :csrf-token="csrfToken"
            :locale="locale"
        />
    </ClientLayout>
</template>
