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


const router =
    useRouter()


const columns = [
    {
        key: 'name',
        label: 'Name',
        sortable: true
    },


    {
        key: 'email',
        label: 'Email',
        sortable: true
    },


    {
        key: 'projects',
        label: 'Projects'
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
        '/coworkers'
    )


const search =
    computed({
        get() {
            return state.search
        },


        set(value) {
            state.search =
                value

            state.page =
                1
        }
    })


function createCoworker() {
    router.push({
        name:
            'coworkers.create'
    })
}


function openCoworker(
    coworker
) {
    if (
        !coworker?.id
    ) {
        return
    }


    router.push({
        name:
            'coworkers.edit',

        params: {
            id:
                coworker.id
        }
    })
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
            title="Coworkers"
            description="Manage staff members and the client projects they can access."
            :breadcrumbs="[
                {
                    label: 'Coworkers'
                }
            ]"
        />


        <!-- Coworkers table -->
        <AdminDataTable
            v-model:search="
                search
            "
            title="All coworkers"
            search-placeholder="Search by name, email or project"
            :columns="
                columns
            "
            :rows="
                rows
            "
            :loading="
                loading
            "
            :sort="
                state.sort
            "
            :direction="
                state.direction
            "
            empty-title="No coworkers yet."
            empty-text="Create your first coworker to give your team access."
            add-label=" "
            @sort="
                sortBy
            "
            @row-click="
                openCoworker
            "
            @add="
                createCoworker
            "
        >
            <!-- Name -->
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
                            uppercase
                        "
                    >
                        {{
                            row.name
                        }}
                    </p>
                </div>
            </template>


            <!-- Email -->
            <template
                #cell-email="{
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


            <!-- Projects -->
            <template
                #cell-projects="{
                    row
                }"
            >
                <div
                    v-if="
                        row.projects?.length
                    "
                    class="
                        flex
                        flex-wrap
                        gap-2
                    "
                >
                    <span
                        v-for="
                            project in row.projects
                        "
                        :key="
                            project.id
                        "
                        class="
                            inline-flex
                        "
                    >
                        <span
                            class="
                                bg-accent
                                px-2
                                py-1
                                font-mono
                                text-[10px]
                                font-bold
                                uppercase
                                text-light
                            "
                        >
                            {{
                                project.name
                            }}
                        </span>
                    </span>
                </div>


                <span
                    v-else
                    class="
                        p
                        text-dark/40
                    "
                >
                    No projects
                </span>
            </template>


            <!-- Empty state -->
            <template
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
                        createCoworker
                    "
                >
                    Create coworker
                </button>
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