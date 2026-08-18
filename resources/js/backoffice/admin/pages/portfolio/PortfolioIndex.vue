<script setup>
import {
    computed,
    onMounted,
    ref
} from 'vue'


import {
    RouterLink,
    useRouter
} from 'vue-router'


import api, {
    errorMessage
} from '../../composables/useAdminApi'


import AdminDataTable from '@shared/components/DataTable.vue'
import Tag from '@shared/components/Tag.vue'
import { useAdminPageHeader } from '../../composables/useAdminPageHeader'


const router =
    useRouter()


const projects =
    ref([])


const loading =
    ref(true)


const error =
    ref('')


const columns = [
    {
        key: 'name',
        label: 'Project',
        sortable: true
    },


    {
        key: 'company',
        label: 'Client',
        sortable: true
    },


    {
        key: 'images_count',
        label: 'Images'
    },


    {
        key: 'features_count',
        label: 'Features'
    },


    {
        key: 'is_published',
        label: 'Status',
        sortable: true
    },


    {
        key: 'url',
        label: 'URL'
    }
]


const statusOptions =
    computed(() => [
        {
            label: 'All statuses',
            value: ''
        },


        {
            label: 'Published',
            value: true
        },


        {
            label: 'Hidden',
            value: false
        }
    ])


const search =
    ref('')


const status =
    ref('')


const filteredProjects =
    computed(() => {
        const query =
            search.value
                .trim()
                .toLowerCase()


        return projects.value.filter(
            project => {
                const matchesSearch =
                    !query ||
                    [
                        project.name,
                        project.company,
                        project.url
                    ]
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


                const matchesStatus =
                    status.value === '' ||
                    Boolean(
                        project.is_published
                    ) ===
                        (
                            status.value === true ||
                            status.value === 'true'
                        )


                return (
                    matchesSearch &&
                    matchesStatus
                )
            }
        )
    })


async function loadPortfolio() {
    loading.value =
        true


    error.value =
        ''


    try {
        const response =
            await api.get(
                '/portfolio'
            )


        projects.value =
            Array.isArray(
                response.data
            )
                ? response.data
                : response.data.data || []
    } catch (
        exception
    ) {
        error.value =
            errorMessage(
                exception
            )
    } finally {
        loading.value =
            false
    }
}


function openProject(
    project
) {
    if (
        !project?.id
    ) {
        return
    }


    router.push({
        name:
            'portfolio.edit',

        params: {
            id:
                project.id
        }
    })
}


function createPortfolioEntry() {
    /*
     * Portfolio entries are existing projects,
     * so there is no separate portfolio creation page.
     *
     * If you later want a project picker here,
     * this is the place to open it.
     */
}


onMounted(
    loadPortfolio
)
useAdminPageHeader({
    title: 'Portfolio',
    description: 'Choose which projects appear publicly and manage their website content.',
    breadcrumbs: [
        {
            label: 'Portfolio'
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
        <!-- Portfolio table -->
        <AdminDataTable
            v-model:search="search"
                title="Projects"
                search-placeholder="Search projects, clients or URLs"
                :columns="columns"
                :rows="filteredProjects"
                :loading="loading"
                :filters="[
                    {
                        key: 'status',
                        label: 'Status',
                        type: 'select',
                        options: statusOptions
                    }
                ]"
                :filter-values="{ status }"
                :sort="null"
                :direction="'asc'"
                empty-title="No portfolio projects found."
                empty-text="Create a project first, then add it to your portfolio."
                @row-click="openProject"
                @add="createPortfolioEntry"
        >
            <!-- Project -->
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


                    <p
                        v-if="
                            row.project_code
                        "
                        class="
                            p
                            mt-1
                            text-dark/40
                        "
                    >
                        {{
                            row.project_code
                        }}
                    </p>
                </div>
            </template>


            <!-- Client -->
            <template
                #cell-company="{
                    value
                }"
            >
                <span class="p">
                    {{
                        value ||
                        'No client'
                    }}
                </span>
            </template>


            <!-- Images -->
            <template
                #cell-images_count="{
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


            <!-- Features -->
            <template
                #cell-features_count="{
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
                #cell-is_published="{
                    value
                }"
            >
                <Tag
                    :text="
                        value
                            ? 'published'
                            : 'hidden'
                    "
                />
            </template>


            <!-- URL -->
            <template
                #cell-url="{
                    value
                }"
            >
                <span
                    class="
                        p
                        text-dark/50
                    "
                >
                    /
                    {{
                        value ||
                        '—'
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
                            'projects.index'
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
                    Create project
                </RouterLink>
            </template>
        </AdminDataTable>
    </div>
</template>