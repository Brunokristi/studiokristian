<script setup>

import {
    computed,
    onMounted,
    ref
} from 'vue'


import {
    useRoute
} from 'vue-router'


import api, {
    errorMessage
} from '../../composables/useAdminApi'


import DataTable from '@shared/components/DataTable.vue'
import Loading from '@shared/components/Loading.vue'
import Section from '../../../components/Section.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'


import ContactModal from './ContactModal.vue'


import {
    useAdminPageHeader
} from '../../composables/useAdminPageHeader'


const route =
    useRoute()


const clientId =
    computed(() =>
        String(
            route.params.id ||
            ''
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


const showContactModal =
    ref(false)


const selectedContact =
    ref(null)


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


function openCreateContact() {

    selectedContact.value =
        null


    showContactModal.value =
        true

}


function openEditContact(
    contact
) {

    if (
        !contact?.id
    ) {

        return

    }


    selectedContact.value =
        contact


    showContactModal.value =
        true

}


function closeContactModal() {

    showContactModal.value =
        false

    selectedContact.value =
        null

}


async function handleContactSaved() {

    closeContactModal()

    await loadClient()

}


function handleContactError(
    message
) {

    showError(
        message
    )

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

        <!-- ============================================================ -->
        <!-- ERROR -->
        <!-- ============================================================ -->

        <Toast
            v-model="
                showErrorToast
            "
            heading="Something went wrong"
            :text="
                requestError
            "
            :duration="5000"
        />


        <!-- ============================================================ -->
        <!-- CONTACTS -->
        <!-- ============================================================ -->

        <Section
            title="Contacts"
        >

            <Loading
                v-if="
                    loading
                "
            />


            <DataTable
                v-else
                search-placeholder="Search contacts"
                :columns="
                    columns
                "
                :rows="
                    contacts
                "
                :loading="
                    loading
                "
                empty-title="No contacts yet."
                empty-text="Add a contact to give this client a person to work with."
                add-label=" "
                @row-click="
                    openEditContact
                "
                @add="
                    openCreateContact
                "
            >

                <!-- Contact -->

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


                <!-- Email -->

                <template
                    #cell-email="{
                        value
                    }"
                >

                    <span
                        class="p"
                    >
                        {{
                            value ||
                            '—'
                        }}
                    </span>

                </template>


                <!-- Position -->

                <template
                    #cell-position="{
                        value
                    }"
                >

                    <span
                        class="p"
                    >
                        {{
                            value ||
                            '—'
                        }}
                    </span>

                </template>


                <!-- Status -->

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


                <!-- Portal -->

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


        <!-- ============================================================ -->
        <!-- CONTACT MODAL -->
        <!-- ============================================================ -->

        <ContactModal
            :open="
                showContactModal
            "
            :company-id="
                clientId
            "
            :contact="
                selectedContact
            "
            @close="
                closeContactModal
            "
            @saved="
                handleContactSaved
            "
            @error="
                handleContactError
            "
        />

    </div>

</template>