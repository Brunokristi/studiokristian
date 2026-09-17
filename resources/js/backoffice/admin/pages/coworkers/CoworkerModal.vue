<script setup>

import {
    computed,
    reactive,
    ref,
    watch
} from 'vue'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'


import Button
    from '@shared/components/Button.vue'

import FormField
    from '@shared/components/FormField.vue'

import Modal
    from '@shared/components/Modal.vue'


const props =
    defineProps({

        open: {
            type: Boolean,
            default: false
        },

        projectId: {
            type: [
                String,
                Number
            ],
            default: ''
        },

        initialName: {
            type: String,
            default: ''
        }

    })


const emit =
    defineEmits([
        'close',
        'created',
        'error'
    ])


const saving =
    ref(false)


const errors =
    ref({})


const form =
    reactive({

        name: '',

        email: ''

    })


const title =
    computed(() =>
        'Create coworker'
    )


const subtitle =
    computed(() =>
        'Create a coworker and assign them to this project.'
    )


/*
|--------------------------------------------------------------------------
| Initialize
|--------------------------------------------------------------------------
*/

function initialize() {

    errors.value =
        {}

    form.name =
        String(
            props.initialName ||
            ''
        )

    form.email =
        ''

}


watch(
    () => props.open,
    value => {

        if (
            value
        ) {

            initialize()

        }

    }
)


watch(
    () => props.initialName,
    value => {

        if (
            props.open &&
            !form.name
        ) {

            form.name =
                String(
                    value ||
                    ''
                )

        }

    }
)


/*
|--------------------------------------------------------------------------
| Close
|--------------------------------------------------------------------------
*/

function close() {

    if (
        saving.value
    ) {
        return
    }


    emit(
        'close'
    )

}


/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

async function submit() {

    if (
        saving.value
    ) {
        return
    }


    errors.value =
        {}


    const name =
        String(
            form.name ||
            ''
        ).trim()


    const email =
        String(
            form.email ||
            ''
        ).trim()


    if (
        !name
    ) {

        errors.value = {

            name: [
                'Name is required.'
            ]

        }

        return

    }


    if (
        !email
    ) {

        errors.value = {

            email: [
                'Email is required.'
            ]

        }

        return

    }


    saving.value =
        true


    try {

        const response =
            await api.post(
                '/coworkers',
                {

                    name,

                    email,

                    project_ids: [
                        Number(
                            props.projectId
                        )
                    ]

                }
            )


        const created =
            response.data?.data ||
            response.data


        emit(
            'created',
            created
        )

    } catch (
        exception
    ) {

        errors.value =
            validationErrors(
                exception
            )


        emit(
            'error',
            errorMessage(
                exception
            )
        )

    } finally {

        saving.value =
            false

    }

}

</script>


<template>

    <Modal
        :open="open"
        :title="title"
        :subtitle="subtitle"
        @close="close"
    >

        <form
            class="
                space-y-8
            "
            @submit.prevent="
                submit
            "
        >

            <FormField
                id="coworker-modal-name"
                v-model="
                    form.name
                "
                name="name"
                type="text"
                label="Name"
                placeholder="Full name"
                autocomplete="name"
                required
                :disabled="
                    saving
                "
                :error="
                    errors.name?.[0] ||
                    ''
                "
            />


            <FormField
                id="coworker-modal-email"
                v-model="
                    form.email
                "
                name="email"
                type="email"
                label="Email"
                placeholder="name@company.com"
                autocomplete="email"
                required
                :disabled="
                    saving
                "
                :error="
                    errors.email?.[0] ||
                    ''
                "
            />

        </form>


        <template #footer>

            <Button
                type="button"
                text="Cancel"
                variant="ghost"
                :disabled="
                    saving
                "
                @click="
                    close
                "
            />


            <Button
                type="button"
                text="Create coworker"
                variant="primary"
                :loading="
                    saving
                "
                @click="
                    submit
                "
            />

        </template>

    </Modal>

</template>