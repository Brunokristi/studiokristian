<script setup>
import {
    nextTick,
    ref
} from 'vue'


import axios from 'axios'


import Button from '@/components/Button.vue'
import FormField from '@/components/FormField.vue'
import Navbar from '@/components/Navbar.vue'
import Toast from '@/components/Toast.vue'


const root =
    document.querySelector(
        '#staff-login'
    )


const form =
    ref(null)


const email =
    ref('')


const status =
    ref(
        root?.dataset.status ||
        ''
    )


const error =
    ref(
        root?.dataset.error ||
        ''
    )


const sending =
    ref(false)


const showToast =
    ref(
        Boolean(
            status.value
        )
    )


function showSuccess(
    message
) {
    status.value =
        message


    showToast.value =
        false


    nextTick(() => {
        showToast.value =
            true
    })
}


function submitFromEnter(
    event
) {
    if (
        event.key !==
        'Enter'
    ) {
        return
    }


    event.preventDefault()


    if (
        sending.value
    ) {
        return
    }


    form.value?.requestSubmit()
}


async function submit() {
    if (
        sending.value
    ) {
        return
    }


    sending.value =
        true


    error.value =
        ''


    status.value =
        ''


    try {
        const response =
            await axios.post(
                '/login',
                {
                    email:
                        email.value
                },
                {
                    headers: {
                        Accept:
                            'application/json',


                        'X-CSRF-TOKEN':
                            document.querySelector(
                                'meta[name="csrf-token"]'
                            )?.content ||
                            '',


                        'X-Requested-With':
                            'XMLHttpRequest'
                    }
                }
            )


        email.value =
            ''


        showSuccess(
            response.data?.message ||
            'The sign-in link has been sent.'
        )
    } catch (
        exception
    ) {
        error.value =
            exception.response
                ?.data
                ?.errors
                ?.email
                ?.[0] ||
            exception.response
                ?.data
                ?.message ||
            'The sign-in link could not be sent.'
    } finally {
        sending.value =
            false
    }
}
</script>


<template>
    <div
        class="
            min-h-screen
            bg-light
            text-dark
        "
        data-theme="light"
    >
        <Navbar
            variant="light"
        />


        <Toast
            v-model="
                showToast
            "
            heading="
                Check your inbox
            "
            :text="
                status
            "
            :duration="
                5000
            "
        />


        <main
            class="
                flex
                min-h-screen
                items-center
                justify-center
                px-5
                py-24
                sm:px-8
                lg:px-12
            "
        >
            <section
                class="
                    w-full
                    max-w-2xl
                "
            >
                <form
                    ref="form"
                    class="
                        w-full
                        space-y-10
                    "
                    @submit.prevent="
                        submit
                    "
                >
                    <div
                        class="
                            space-y-3
                            text-center
                        "
                    >
                        <h1
                            class="h2"
                        >
                            Sign in to your workspace
                        </h1>


                        <p
                            class="p"
                        >
                            Enter your email and we’ll send you a link to sign in. No password needed.
                        </p>
                    </div>


                    <FormField
                        id="staff-email"
                        v-model="
                            email
                        "
                        type="email"
                        label="Email"
                        autocomplete="email"
                        :error="
                            error
                        "
                        required
                        autofocus
                        @keydown="
                            submitFromEnter
                        "
                    />


                    <div
                        class="
                            flex
                            justify-center
                        "
                    >
                        <Button
                            type="submit"
                            text="send link"
                            loading-text="sending"
                            :loading="
                                sending
                            "
                            :disabled="
                                sending
                            "
                            :lowercase="
                                false
                            "
                        />
                    </div>
                </form>
            </section>
        </main>
    </div>
</template>