<script setup>
import {
    computed
} from 'vue'


import {
    useRouter
} from 'vue-router'


import {
    useServerTable
} from '../../composables/useServerTable'


import AdminDataTable from '@shared/components/DataTable.vue'
import Tag from '@shared/components/Tag.vue'
import { useAdminPageHeader } from '../../composables/useAdminPageHeader'


const router =
    useRouter()


const columns = [
    {
        key: 'name',
        label: 'Project',
        sortable: true
    },


    {
        key: 'company',
        label: 'Client'
    },


    {
        key: 'service_product',
        label: 'Service'
    },


    {
        key: 'contacts_count',
        label: 'Contacts'
    },


    {
        key: 'status',
        label: 'Status',
        sortable: true
    },


    {
        key: 'updated_at',
        label: 'Updated',
        sortable: true
    }
]


const {
    rows,
    meta,
    loading,
    error,
    state,
    sortBy
} =
    useServerTable(
        '/projects'
    )


const canCreateProject =
    computed(() => {
        if (
            !rows.value.length
        ) {
            return true
        }

        return Boolean(
            rows.value[0]
                ?.current_user
                ?.is_admin
        )
    })


const statusOptions =
    computed(() => [
        {
            label: 'All statuses',
            value: ''
        },


        {
            label: 'Draft',
            value: 'draft'
        },


        {
            label: 'Active',
            value: 'active'
        },


        {
            label: 'On hold',
            value: 'on_hold'
        },


        {
            label: 'Completed',
            value: 'completed'
        },


        {
            label: 'Archived',
            value: 'archived'
        }
    ])


function createProject() {
    if (!canCreateProject.value) {
        return
    }

    router.push({
        name: 'projects.create'
    })
}


function openProject(project) {
    if (!project?.id) {
        return
    }


    router.push({
        name: 'projects.show',
        params: {
            id: project.id
        }
    })
}


function statusTone(status) {
    switch (status) {
    case 'active':
        return 'success'
    case 'completed':
        return 'success'
    case 'on_hold':
        return 'warning'
    case 'archived':
        return 'muted'
    default:
        return 'neutral'
    }
}


function statusLabel(status) {
    if (!status) {
        return 'Draft'
    }


    return String(status)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, letter => letter.toUpperCase())
}


function formatDate(value) {
    if (!value) {
        return '—'
    }


    const date =
        new Date(value)


    if (Number.isNaN(date.getTime())) {
        return '—'
    }


    return date.toLocaleDateString(
        'en-GB',
        {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }
    )
}
useAdminPageHeader({
    title: 'Projects',
    description: 'Manage active and archived client projects.',
    breadcrumbs: [
        {
            label: 'Projects'
        }
    ]
})
</script>


<template>
    <div
        class="
            w-full
            space-y-12
            lg:space-y-14
        "
    >
        <AdminDataTable
            v-model:search="state.search"
                v-model:filters="state.filters"
                title="All projects"
                search-placeholder="Search by project, code or client"
                :columns="columns"
                :rows="rows"
                :loading="loading"
                :meta="meta"
                :filters="[
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        options: statusOptions
                    }
                ]"
                :sort="state.sort"
                :direction="state.direction"
                empty-title="No projects yet."
                empty-text="Create your first project to start collaboration."
                :add-label="canCreateProject ? ' ' : ''"
                @sort="sortBy"
                @row-click="openProject"
                @add="createProject"
                @page-change="page => state.page = page"
        >
            <template
                #cell-name="{
                    row
                }"
            >
                <div
                    class="
                        min-w-0
                    "
                >
                    <p
                        class="
                            p
                            font-medium
                        "
                    >
                        {{
                            row.name ||
                            'Untitled project'
                        }}
                    </p>


                    <p
                        v-if="
                            row.project_code
                        "
                        class="
                            p
                            text-dark/50
                        "
                    >
                        {{
                            row.project_code
                        }}
                    </p>
                </div>
            </template>


            <template
                #cell-company="{
                    row
                }"
            >
                <span class="p">
                    {{
                        row.company?.name ||
                        '—'
                    }}
                </span>
            </template>


            <template
                #cell-service_product="{
                    row
                }"
            >
                <span class="p">
                    {{
                        row.service_product?.name ||
                        '—'
                    }}
                </span>
            </template>


            <template
                #cell-contacts_count="{
                    value
                }"
            >
                <span class="p">
                    {{
                        value ??
                        0
                    }}
                </span>
            </template>


            <template
                #cell-status="{
                    value
                }"
            >
                <Tag
                    size="sm"
                    :tone="
                        statusTone(value)
                    "
                    :label="
                        statusLabel(value)
                    "
                />
            </template>


            <template
                #cell-updated_at="{
                    value
                }"
            >
                <span class="p">
                    {{
                        formatDate(value)
                    }}
                </span>
            </template>


            <template
                v-if="
                    canCreateProject
                "
                #empty-action
            >
                <button
                    type="button"
                    class="
                        border-b
                        border-dark
                        pb-1
                        font-mono
                        text-xs
                        font-bold
                        uppercase
                        transition-colors
                        hover:border-accent
                        hover:text-accent
                    "
                    @click="
                        createProject
                    "
                >
                    Create project
                </button>
            </template>
        </AdminDataTable>


        <p
            v-if="
                error
            "
            class="
                p
                text-red-700
            "
        >
            {{
                error
            }}
        </p>
    </div>
</template>
