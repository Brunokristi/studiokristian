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


const contacts =
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
        label: 'Contact'
    },

    {
        key: 'email',
        label: 'Email'
    },

    {
        key: 'position',
        label: 'Position'
    },

    {
        key: 'active',
        label: 'Status'
    },

    {
        key: 'can_access_portal',
        label: 'Portal'
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


        contacts.value =
            client.contacts ||
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


function contactName(
    contact
) {

    if (
        contact.name
    ) {

        return contact.name

    }


    return [

        contact.first_name,

        contact.last_name

    ]
        .filter(Boolean)
        .join(' ')

}


function editContact(
    contact
) {

    if (
        !contact?.id ||
        !clientId.value
    ) {

        return

    }


    router.push({

        name:
            'contacts.edit',

        params: {

            companyId:
                clientId.value,

            id:
                contact.id

        }

    })

}


function createContact() {

    if (
        !clientId.value
    ) {

        return

    }


    router.push({

        name:
            'contacts.create',

        params: {

            companyId:
                clientId.value

        }

    })

}


useAdminPageHeader({

    title:
        clientName,

    description:
        'People connected to this client.',

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
                label: 'Contacts'
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
            title="Contacts"
        >

            <Loading
                v-if="loading"
            />


            <DataTable
                v-else
                search-placeholder="Search contacts"
                :columns="columns"
                :rows="contacts"
                :loading="loading"
                empty-title="No contacts yet."
                empty-text="Add a contact to give this client a person to work with."
                add-label=" "
                @row-click="editContact"
                @add="createContact"
            >

                <template
                    #cell-name="{
                        row
                    }"
                >

                    <span
                        class="
                            p
                            font-medium
                        "
                    >
                        {{
                            contactName(row)
                        }}
                    </span>

                </template>


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


                <template
                    #cell-position="{
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


                <template
                    #cell-active="{
                        value
                    }"
                >

                    <Tag
                        :text="
                            value === false
                                ? 'inactive'
                                : 'active'
                        "
                    />

                </template>


                <template
                    #cell-can_access_portal="{
                        value
                    }"
                >

                    <Tag
                        :text="
                            value
                                ? 'enabled'
                                : 'disabled'
                        "
                    />

                </template>

            </DataTable>

        </Section>

    </div>

</template>