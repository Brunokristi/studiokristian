<script setup>
import {
    computed
} from 'vue'


import {
    RouterLink,
    useRouter
} from 'vue-router'


import {
    useServerTable
} from '../../composables/useServerTable'


import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminDataTable from '../../components/AdminDataTable.vue'
import AdminPagination from '../../components/AdminPagination.vue'
import Tag from '@shared/components/Tag.vue'


const router =
    useRouter()


const columns = [
    {
        key: 'display_label',
        sortKey: 'name',
        label: 'Client',
        sortable: true
    },


    {
        key: 'registration_number',
        label: 'IČO',
        sortable: true
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
        key: 'portal_contacts_count',
        label: 'Portal access'
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
        '/clients'
    )


const statusOptions =
    computed(() => [
        {
            label: 'All statuses',
            value: ''
        },


        {
            label: 'Active',
            value: 'active'
        },


        {
            label: 'Inactive',
            value: 'inactive'
        },


        {
            label: 'Archived',
            value: 'archived'
        }
    ])


function createClient() {
    router.push({
        name:
            'clients.create'
    })
}


function openClient(
    client
) {
    if (
        !client?.id
    ) {
        return
    }


    router.push({
        name:
            'clients.show',


        params: {
            id:
                client.id
        }
    })
}


function formatDate(
    value
) {
    if (
        !value
    ) {
        return '—'
    }


    const date =
        new Date(
            value
        )


    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
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
</script>


<template>
    <div
        class="
            w-full
            space-y-12
            lg:space-y-14
        "
    >
        <!-- Page header -->
        <AdminPageHeader
            title="Clients"
            description="Companies and the people representing them."
            :breadcrumbs="[
                {
                    label: 'Clients'
                }
            ]"
        />


        <!-- Clients table -->
        <AdminDataTable
            v-model:search="
                state.search
            "
            v-model:filters="
                state.filters
            "
            title="All clients"
            search-placeholder="Search clients"
            :columns="
                columns
            "
            :rows="
                rows
            "
            :loading="
                loading
            "
            :filters="[
                {
                    key: 'status',
                    label: 'Status',
                    type: 'select',
                    options: statusOptions
                }
            ]"
            :sort="
                state.sort
            "
            :direction="
                state.direction
            "
            empty-title="No clients yet."
            empty-text="Create the first client company to begin."
            add-label=" "
            @sort="
                sortBy
            "
            @row-click="
                openClient
            "
            @add="
                createClient
            "
        >
            <!-- Client -->
            <template
                #cell-display_label="{
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
                            uppercase
                        "
                    >
                        {{
                            row.display_label
                        }}
                    </p>


                </div>
            </template>


            <!-- IČO -->
            <template
                #cell-registration_number="{
                    value
                }"
            >
                <span class="p">
                    {{
                        value ||
                        '—'
                    }}
                </span>
            </template>


            <!-- Contacts -->
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


            <!-- Projects -->
            <template
                #cell-projects_count="{
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


            <!-- Portal access -->
            <template
                #cell-portal_contacts_count="{
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


            <!-- Status -->
            <template
                #cell-status="{
                    value
                }"
            >
                <Tag
                    :text="
                        value
                    "
                />
            </template>


            <!-- Updated -->
            <template
                #cell-updated_at="{
                    value
                }"
            >
                <span class="p">
                    {{
                        formatDate(
                            value
                        )
                    }}
                </span>
            </template>


            <!-- Empty state -->
            <template
                #empty-action
            >
                <RouterLink
                    :to="{
                        name:
                            'clients.create'
                    }"
                    class="
                        inline-flex
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
                >
                    Create client
                </RouterLink>
            </template>
        </AdminDataTable>


        <!-- Pagination -->
        <AdminPagination
            :meta="
                meta
            "
            @change="
                page =>
                    state.page =
                        page
            "
        />
    </div>
</template>