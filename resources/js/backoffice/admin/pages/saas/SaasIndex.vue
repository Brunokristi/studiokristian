<script setup>
import {
    useRouter
} from 'vue-router'

import {
    useServerTable
} from '../../composables/useServerTable'

import AdminDataTable from '@shared/components/DataTable.vue'
import Tag from '@shared/components/Tag.vue'
import { useAdminPageHeader } from '../../composables/useAdminPageHeader'

const router = useRouter()

const columns = [
    {
        key: 'name',
        label: 'SaaS project',
        sortable: true
    },
    {
        key: 'company',
        label: 'Client'
    },
    {
        key: 'saas_plans_count',
        label: 'Plans'
    },
    {
        key: 'saas_subscriptions_count',
        label: 'Subscriptions'
    }
]

const {
    rows,
    meta,
    loading,
    state,
    sortBy
} = useServerTable(
    '/saas/projects',
    {
        sort: 'name',
        direction: 'asc'
    }
)

function openProject(project) {
    if (!project?.id) {
        return
    }

    router.push({
        name: 'saas.projects.show',
        params: {
            id: project.id
        }
    })
}

useAdminPageHeader({
    title: 'SaaS',
    description: 'Manage SaaS projects, plans, customers, subscriptions and revenue.',
    breadcrumbs: [
        {
            label: 'SaaS'
        }
    ]
})
</script>

<template>
    <div class="w-full space-y-12">
        <AdminDataTable
            v-model:search="state.search"
            title="SaaS projects"
            search-placeholder="Search SaaS projects"
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :meta="meta"
            :sort="state.sort"
            :direction="state.direction"
            empty-title="No SaaS projects yet."
            empty-text="Enable SaaS project on a Project to manage plans and subscriptions here."
            @sort="sortBy"
            @row-click="openProject"
            @page-change="page => state.page = page"
        >
            <template #cell-name="{ row }">
                <div class="min-w-0">
                    <p class="p font-medium">
                        {{ row.name || 'Untitled project' }}
                    </p>

                    <Tag
                        v-if="row.is_saas"
                        size="sm"
                        tone="success"
                        label="SaaS"
                    />
                </div>
            </template>

            <template #cell-company="{ row }">
                <span class="p">
                    {{ row.company?.name || '—' }}
                </span>
            </template>

            <template #cell-saas_plans_count="{ value }">
                <span class="p">
                    {{ value ?? 0 }}
                </span>
            </template>

            <template #cell-saas_subscriptions_count="{ value }">
                <span class="p">
                    {{ value ?? 0 }}
                </span>
            </template>
        </AdminDataTable>
    </div>
</template>