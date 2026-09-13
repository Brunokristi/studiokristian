<script setup>

import {
    computed,
    onMounted,
    ref
} from 'vue'


import {
    useRoute,
    useRouter
} from 'vue-router'


import api, {
    errorMessage
} from '../../composables/useAdminApi'


import DataTable from '@shared/components/DataTable.vue'
import Loading from '@shared/components/Loading.vue'
import Section from '../../../components/Section.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'


import {
    useAdminPageHeader
} from '../../composables/useAdminPageHeader'


const route =
    useRoute()


const router =
    useRouter()


const clientId =
    computed(() =>
        String(
            route.params.id || ''
        )
    )


const loading =
    ref(true)


const projects =
    ref([])


const clientName =
    ref('Client')


const requestError =
    ref('')


const showErrorToast =
    ref(false)


const columns = [

    {
        key: 'name',
        label: 'Project'
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


function showError(
    message
) {

    requestError.value =
        message


    showErrorToast.value =
        false


    requestAnimationFrame(() => {

        showErrorToast.value =
            true

    })

}


async function loadClient() {

    if (
        !clientId.value
    ) {

        loading.value =
            false

        return

    }


    loading.value =
        true


    try {

        const response =
            await api.get(
                `/clients/${clientId.value}`
            )


        const client =
            response.data.data


        clientName.value =
            client.name ||
            'Client'


        projects.value =
            client.projects ||
            []

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
            'projects.show',

        params: {

            id:
                project.id

        }

    })

}


function createProject() {

    if (
        !clientId.value
    ) {

        return

    }


    router.push({

        name:
            'projects.create',

        query: {

            client_id:
                clientId.value

        }

    })

}


useAdminPageHeader({

    title:
        clientName,

    description:
        'Projects associated with this client.',

    breadcrumbs:
        computed(() => [

            {
                label: 'Clients',

                to: {
                    name: 'clients.index'
                }
            },

            {
                label:
                    clientName.value
            },

            {
                label: 'Projects'
            }

        ])

})


onMounted(
    loadClient
)

</script>


<template>

    <div
        class="
            w-full
        "
    >

        <Toast
            v-model="showErrorToast"
            heading="Something went wrong"
            :text="requestError"
            :duration="5000"
        />


        <Section
            title="Projects"
        >

            <Loading
                v-if="loading"
            />


            <DataTable
                v-else
                search-placeholder="Search projects"
                :columns="columns"
                :rows="projects"
                :loading="loading"
                empty-title="No projects yet."
                empty-text="Create a project to start tracking work for this client."
                add-label=" "
                @row-click="openProject"
                @add="createProject"
            >

                <template
                    #cell-name="{
                        row,
                        value
                    }"
                >

                    <div>

                        <span
                            class="
                                p
                                font-medium
                            "
                        >
                            {{
                                value
                            }}
                        </span>


                        <span
                            v-if="
                                row.project_code
                            "
                            class="
                                mt-1
                                block
                                text-xs
                                uppercase
                                text-dark/40
                            "
                        >
                            {{
                                row.project_code
                            }}
                        </span>

                    </div>

                </template>


                <template
                    #cell-service_product="{
                        value,
                        row
                    }"
                >

                    <span class="p">

                        {{
                            value?.name ||
                            row.service_product_name ||
                            value ||
                            '—'
                        }}

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

            </DataTable>

        </Section>

    </div>

</template>