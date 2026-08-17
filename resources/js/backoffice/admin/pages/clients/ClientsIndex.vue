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


import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminDataTable from '../../components/AdminDataTable.vue'
import AdminPagination from '../../components/AdminPagination.vue'
import Tag from '@shared/components/Tag.vue'


const router =
    useRouter()


const columns = [
    {
        key: 'name',
        label: 'Client',
        sortable: true
    },


    {
        key: 'registration_number',
        label: 'Registration',
        sortable: true
    },


    {
        key: 'contacts_count',
        label: 'Contacts'
    },


    {
        key: 'portal_contacts_count',
        label: 'Portal contacts'
    },


    {
        key: 'projects_count',
        label: 'Projects'
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
        '/clients',
        {
            sort: 'updated_at',
            direction: 'desc',
            status: ''
        }
    )


const filterValues =
    computed({
        get() {
            return {
                status:
                    state.status ||
                    ''
            }
        },


        set(value) {
            state.status =
                value?.status ||
                ''

            state.page =
                1
        }
    })


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
            label: 'Archived',
            value: 'archived'
        }
    ])


function createClient() {
    router.push({
        name: 'clients.create'
    })
}


function openClient(
    client
) {
    if (!client?.id) {
        return
    }


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


function statusTone(
    status
) {
    return status === 'archived'
        ? 'muted'
        : 'success'
}


function statusLabel(
    status
) {
    if (!status) {
        return 'Active'
    }


    return String(status)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, letter => letter.toUpperCase())
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
            description="All companies in the client portal."
            :breadcrumbs="[
                {
                    label: 'Clients'
                }
            ]"
        />


        <p
            v-if="
                error
            "
            class="
                p
                text-red-600
            "
        >
            {{ error }}
        </p>


        <AdminDataTable
            v-model:search="
                state.search
            "
            :filter-values="
                filterValues
            "
            @update:filterValues="
                values => {
                    filterValues = values
                }
            "
            title="All clients"
            search-placeholder="Search by company, registration, tax, VAT or contact email"
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
            empty-title="No clients found."
            empty-text="Create your first client to start assigning projects."
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
            <template
                #cell-name="{
                    row
                }"
            >
                <div class="min-w-0">
                    <p
                        class="
                            p
                            font-medium
                            uppercase
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
                #cell-portal_contacts_count="{
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
                #cell-status="{
                    value
                }"
            >
                <Tag
                    :tone="
                        statusTone(value)
                    "
                    :text="
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
                    {{ formatDate(value) }}
                </span>
            </template>
        </AdminDataTable>


        <AdminPagination
            :meta="
                meta
            "
            @change="
                value => {
                    state.page = value
                }
            "
        />
    </div>
</template>
