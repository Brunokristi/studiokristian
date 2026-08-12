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


function formatDate(
    value
) {
    if (
        !value
    ) {
        return '—'
    }


    return new Date(
        value
    ).toLocaleDateString()
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
        <AdminPageHeader
            title="Clients"
            description="Companies and the people representing them."
            :breadcrumbs="[
                {
                    label: 'Clients'
                }
            ]"
        >
            <RouterLink
                :to="{
                    name: 'clients.create'
                }"
                class="
                    inline-flex
                    items-center
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
                New client
            </RouterLink>
        </AdminPageHeader>


        <AdminDataTable
            v-model:search="state.search"
            title="All clients"
            search-placeholder="Search clients"
            :columns="columns"
            :rows="rows"
            :loading="loading"
            :filters="[
                {
                    key: 'status',
                    label: 'Status',
                    type: 'select',
                    options: statusOptions
                }
            ]"
            v-model:filters="state.filters"
            :sort="state.sort"
            :direction="state.direction"
            empty-title="No clients yet."
            empty-text="Create the first client company to begin."
            @sort="sortBy"
        >
            <template
                #cell-display_label="{
                    row
                }"
            >
                <div>
                    <p
                        class="
                            p
                            font-medium
                            uppercase
                        "
                    >
                        {{ row.display_label }}
                    </p>

                    <p
                        v-if="
                            row.display_name
                        "
                        class="
                            mt-1
                            text-xs
                            uppercase
                            text-dark/40
                        "
                    >
                        {{ row.name }}
                    </p>
                </div>
            </template>


            <template
                #cell-registration_number="{
                    value
                }"
            >
                <span class="p">
                    {{ value || '—' }}
                </span>
            </template>


            <template
                #cell-contacts_count="{
                    value
                }"
            >
                <span class="p">
                    {{ value ?? 0 }}
                </span>
            </template>


            <template
                #cell-projects_count="{
                    value
                }"
            >
                <span class="p">
                    {{ value ?? 0 }}
                </span>
            </template>


            <template
                #cell-portal_contacts_count="{
                    value
                }"
            >
                <span class="p">
                    {{ value ?? 0 }}
                </span>
            </template>


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


            <template
                #cell-updated_at="{
                    value
                }"
            >
                <span class="p">
                    {{ formatDate(value) }}
                </span>
            </template>


            <template
                #empty-action
            >
                <RouterLink
                    :to="{
                        name: 'clients.create'
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


        <AdminPagination
            :meta="meta"
            @change="
                page =>
                    state.page = page
            "
        />
    </div>
</template>