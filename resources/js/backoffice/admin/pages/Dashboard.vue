<script setup>
import {
    computed,
    nextTick,
    onMounted,
    ref
} from 'vue'


import {
    useRouter
} from 'vue-router'


import api, {
    errorMessage
} from '../composables/useAdminApi'


import AdminTable from '@shared/components/DataTable.vue'
import { useAdminPageHeader } from '../composables/useAdminPageHeader'

import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'


const router =
    useRouter()


const data =
    ref({
        counts: {},
        recent_projects: [],
        recent_clients: []
    })


const loading =
    ref(true)


const error =
    ref('')


const showErrorToast =
    ref(false)


const projectSearch =
    ref('')


const clientSearch =
    ref('')


const summary =
    computed(() => [
        {
            key: 'active_clients',
            label: 'Active clients'
        },

        {
            key: 'active_projects',
            label: 'Active projects'
        },

        {
            key: 'active_service_products',
            label: 'Active products'
        },

        {
            key: 'portal_contacts',
            label: 'Portal contacts'
        }
    ])


const projectColumns = [
    {
        key: 'name',
        label: 'Project'
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
        key: 'status',
        label: 'Status'
    }
]


const clientColumns = [
    {
        key: 'display_label',
        label: 'Client'
    },

    {
        key: 'contacts_count',
        label: 'Contacts'
    },

    {
        key: 'projects_count',
        label: 'Projects'
    },

    {
        key: 'status',
        label: 'Status'
    }
]


const filteredProjects =
    computed(() => {
        const query =
            projectSearch.value
                .trim()
                .toLowerCase()


        if (!query) {
            return data.value
                .recent_projects
        }


        return data.value
            .recent_projects
            .filter(
                project => {
                    const values = [
                        project.name,
                        project.company?.name,
                        project.service_product?.name,
                        project.status
                    ]


                    return values
                        .filter(Boolean)
                        .some(
                            value =>
                                String(
                                    value
                                )
                                    .toLowerCase()
                                    .includes(
                                        query
                                    )
                        )
                }
            )
    })


const filteredClients =
    computed(() => {
        const query =
            clientSearch.value
                .trim()
                .toLowerCase()


        if (!query) {
            return data.value
                .recent_clients
        }


        return data.value
            .recent_clients
            .filter(
                client => {
                    const values = [
                        client.display_label,
                        client.status,
                        client.contacts_count,
                        client.projects_count
                    ]


                    return values
                        .filter(
                            value =>
                                value !== null &&
                                value !== undefined
                        )
                        .some(
                            value =>
                                String(
                                    value
                                )
                                    .toLowerCase()
                                    .includes(
                                        query
                                    )
                        )
                }
            )
    })


function showError(
    message
) {
    error.value =
        message


    showErrorToast.value =
        false


    nextTick(() => {
        showErrorToast.value =
            true
    })
}


function openProject(
    project
) {
    router.push({
        name: 'projects.show',

        params: {
            id: project.id
        }
    })
}


function openClient(
    client
) {
    router.push({
        name: 'clients.show',

        params: {
            id: client.id
        }
    })
}


onMounted(
    async () => {
        try {
            const response =
                await api.get(
                    '/dashboard'
                )


            data.value =
                response.data
        } catch (
            exception
        ) {
            showError(
                errorMessage(
                    exception
                )
            )
        } finally {
            loading.value =
                false
        }
    }
)
useAdminPageHeader({
    title: 'Overview',
    breadcrumbs: [
        {
            label: 'Dashboard'
        }
    ]
})
</script>


<template>
    <div
            class="w-full space-y-12 lg:space-y-14"
    >
        <Toast
            v-model="showErrorToast"
            heading="Something went wrong"
            :text="error"
            :duration="5000"
        />


        <!-- Summary -->
        <section
            aria-label="Summary"
        >
            <div
                class="
                    grid
                    grid-cols-2
                    gap-px
                    border
                    border-accent
                    bg-accent
                    lg:grid-cols-4
                "
            >
                <article
                    v-for="
                        item
                        in summary
                    "
                    :key="
                        item.key
                    "
                    class="
                        flex
                        min-h-32
                        flex-col
                        justify-between
                        bg-light
                        p-4
                        sm:min-h-40
                        sm:p-5
                        lg:min-h-44
                        lg:p-6
                    "
                >
                    <p class="h3">
                        {{ item.label }}
                    </p>


                    <p
                        class="
                            font-mono
                            text-4xl
                            font-bold
                            leading-none
                            text-accent
                            sm:text-5xl
                        "
                    >
                        {{
                            loading
                                ? '—'
                                : data.counts[
                                    item.key
                                ] ?? 0
                        }}
                    </p>
                </article>
            </div>
        </section>


        <!-- Recent projects -->
        <AdminTable
            v-model:search="
                projectSearch
            "
            title="Recent projects"
            search-placeholder="Search projects"
            :columns="
                projectColumns
            "
            :rows="
                filteredProjects
            "
            :loading="
                loading
            "
            empty-title="No projects found."
            empty-text="Projects you create will appear here."
            @row-click="
                openProject
            "
        >
            <template
                #cell-name="{
                    row
                }"
            >
                <span class="h3">
                    {{ row.name }}
                </span>
            </template>


            <template
                #cell-company="{
                    row
                }"
            >
                <span
                    class="
                        p
                        uppercase
                    "
                >
                    {{
                        row.company
                            ?.name ||
                        '—'
                    }}
                </span>
            </template>


            <template
                #cell-service_product="{
                    row
                }"
            >
                <span
                    class="
                        p
                        uppercase
                    "
                >
                    {{
                        row.service_product
                            ?.name ||
                        '—'
                    }}
                </span>
            </template>


            <template
                #cell-status="{
                    row
                }"
            >
                <Tag
                    :text="
                        row.status
                    "
                />
            </template>
        </AdminTable>


        <!-- Recent clients -->
        <AdminTable
            v-model:search="
                clientSearch
            "
            title="Recent clients"
            search-placeholder="Search clients"
            :columns="
                clientColumns
            "
            :rows="
                filteredClients
            "
            :loading="
                loading
            "
            empty-title="No clients found."
            empty-text="Clients you create will appear here."
            @row-click="
                openClient
            "
        >
            <template
                #cell-display_label="{
                    row
                }"
            >
                <span class="h3">
                    {{
                        row.display_label
                    }}
                </span>
            </template>


            <template
                #cell-contacts_count="{
                    row
                }"
            >
                <span class="p">
                    {{
                        row.contacts_count ??
                        0
                    }}
                </span>
            </template>


            <template
                #cell-projects_count="{
                    row
                }"
            >
                <span class="p">
                    {{
                        row.projects_count ??
                        0
                    }}
                </span>
            </template>


            <template
                #cell-status="{
                    row
                }"
            >
                <Tag
                    :text="
                        row.status
                    "
                />
            </template>
        </AdminTable>
    </div>
</template>