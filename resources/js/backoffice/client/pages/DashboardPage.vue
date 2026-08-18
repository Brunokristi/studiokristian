<script setup>
import {
    computed,
    ref
} from 'vue'


import AdminDataTable from '@shared/components/DataTable.vue'
import { useClientPageHeader } from '../composables/useClientPageHeader'


const props = defineProps({
    data: {
        type: Object,
        required: true
    },

    locale: {
        type: String,
        required: true
    }
})


/*
|--------------------------------------------------------------------------
| Copy
|--------------------------------------------------------------------------
*/

const copy =
    computed(() => {
        if (
            props.locale === 'sk'
        ) {
            return {
                heading:
                    'Projekty',

                tableHeading:
                    'Prehľad vašich projektov',

                project:
                    'Projekt',

                service:
                    'Služba',

                requiredActions:
                    'Vyžaduje akciu',

                searchPlaceholder:
                    'Hľadať projekty',

                noProjects:
                    'Zatiaľ tu nie sú žiadne aktívne projekty.',

                open:
                    'Otvoriť',

                actionRequired:
                    'Vyžaduje vašu pozornosť',

                noAction:
                    'Žiadna akcia'
            }
        }


        return {
            heading:
                'Projects',

            tableHeading:
                'Overview of your projects',

            project:
                'Project',

            service:
                'Service',

            requiredActions:
                'Required actions',

            searchPlaceholder:
                'Search projects',

            noProjects:
                'There are no active projects here yet.',

            open:
                'Open',

            actionRequired:
                'Action required',

            noAction:
                'No action'
        }
    })


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

const search =
    ref('')


/*
|--------------------------------------------------------------------------
| Columns
|--------------------------------------------------------------------------
*/

const columns =
    computed(() => [
        {
            key:
                'name',

            label:
                copy.value.project,

            sortable:
                true
        },

        {
            key:
                'service_name',

            label:
                copy.value.service,

            sortable:
                true
        },

        {
            key:
                'action_count',

            label:
                copy.value.requiredActions,

            sortable:
                true
        }
    ])


/*
|--------------------------------------------------------------------------
| Rows
|--------------------------------------------------------------------------
*/

const rows =
    computed(() => {
        const query =
            search.value
                .trim()
                .toLowerCase()


        return (
            props.data.projects ||
            []
        )
            .filter(
                project => {
                    if (
                        !query
                    ) {
                        return true
                    }


                    return [
                        String(
                            project.name ||
                            ''
                        ),

                        String(
                            project.service_name ||
                            ''
                        )
                    ].some(
                        value =>
                            value
                                .toLowerCase()
                                .includes(
                                    query
                                )
                    )
                }
            )
            .map(
                project => ({
                    ...project,

                    action_count:
                        Number(
                            project.action_count ||
                            0
                        )
                })
            )
    })


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

function openProject(
    project
) {
    const url =
        String(
            project?.url ||
            ''
        ).trim()


    if (
        !url
    ) {
        return
    }


    window.location.assign(
        url
    )
}
useClientPageHeader({
    title: computed(() => copy.value.heading),
    eyebrow: computed(() => props.data.company_name),
    homeUrl: computed(() => props.data.urls.dashboard)
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
        <!--
        |--------------------------------------------------------------------------
        | Projects table
        |--------------------------------------------------------------------------
        -->

        <AdminDataTable
            v-model:search="
                search
            "
            :title="
                copy.tableHeading
            "
            :columns="
                columns
            "
            :rows="
                rows
            "
            :search-placeholder="
                copy.searchPlaceholder
            "
            :empty-title="
                copy.noProjects
            "
            @row-click="
                openProject
            "
        >
            <!-- Project -->

            <template
                #cell-name="{
                    row
                }"
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
            </template>


            <!-- Service -->

            <template
                #cell-service_name="{
                    value
                }"
            >
                <span
                    class="
                        p
                        uppercase
                    "
                >
                    {{
                        value ||
                        '—'
                    }}
                </span>
            </template>


            <!-- Required actions -->

            <template
                #cell-action_count="{
                    value
                }"
            >
                <span
                    class="
                        p
                        uppercase
                    "
                    :class="
                        value > 0
                            ? 'text-accent font-medium'
                            : 'text-dark/40'
                    "
                >
                    {{
                        value > 0
                            ? `${value} ${copy.actionRequired}`
                            : copy.noAction
                    }}
                </span>
            </template>


            <!-- Empty state -->

            <template
                #empty-action
            >
                <span
                    class="
                        font-mono
                        text-xs
                        font-bold
                        uppercase
                        text-dark/40
                    "
                >
                    {{
                        copy.noProjects
                    }}
                </span>
            </template>
        </AdminDataTable>
    </div>
</template>