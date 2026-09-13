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


import Button from '@shared/components/Button.vue'
import Loading from '@shared/components/Loading.vue'
import Section from '../../../components/Section.vue'
import Toast from '@shared/components/Toast.vue'


import AdminConfirmDialog
    from '../../../../shared/components/ConfirmDialog.vue'


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


const clientName =
    ref('Client')


const loading =
    ref(true)


const deleting =
    ref(false)


const showDeleteConfirm =
    ref(false)


const requestError =
    ref('')


const showErrorToast =
    ref(false)


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


function deleteClient() {

    if (
        !clientId.value ||
        deleting.value
    ) {

        return

    }


    showDeleteConfirm.value =
        true

}


async function confirmDeleteClient() {

    if (
        !clientId.value ||
        deleting.value
    ) {

        return

    }


    deleting.value =
        true


    requestError.value =
        ''


    try {

        await api.delete(
            `/clients/${clientId.value}`
        )


        showDeleteConfirm.value =
            false


        router.push({

            name:
                'clients.index'

        })

    } catch (
        exception
    ) {

        showDeleteConfirm.value =
            false


        showError(
            errorMessage(
                exception
            )
        )

    } finally {

        deleting.value =
            false

    }

}


function closeDeleteConfirm() {

    if (
        deleting.value
    ) {

        return

    }


    showDeleteConfirm.value =
        false

}


useAdminPageHeader({

    title:
        clientName,

    description:
        'Destructive actions for this client.',

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
                label: 'Danger zone'
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
            title="Danger zone"
        >

            <Loading
                v-if="loading"
            />


            <div
                v-else
                class="
                    space-y-6
                "
            >

                <p class="p">

                    Deleting this client will permanently
                    remove {{ clientName }} and its
                    associated data.

                </p>


                <Button
                    type="button"
                    text="Delete client"
                    loading-text="Deleting..."
                    :loading="deleting"
                    :disabled="deleting"
                    :lowercase="true"
                    align="left"
                    @click="deleteClient"
                />

            </div>

        </Section>


        <AdminConfirmDialog
            :open="
                showDeleteConfirm
            "
            title="Delete client?"
            :text="
                `This will permanently delete ${clientName}. This action cannot be undone.`
            "
            confirm-label="Delete client"
            :busy="
                deleting
            "
            @close="
                closeDeleteConfirm
            "
            @confirm="
                confirmDeleteClient
            "
        />

    </div>

</template>