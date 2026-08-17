<script setup>
import {
    nextTick,
    ref
} from 'vue'


import axios from 'axios'


import AuthLayout from '@auth/layouts/AuthLayout.vue'
import {
    useAuthLocale
} from '@auth/composables/useAuthLocale'

import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Toast from '@shared/components/Toast.vue'


const messages = {
    en: {
        title: 'Everything about your project, in one place',
        description: 'Enter your email and we’ll send you a link to sign in. No password needed.',
        email: 'Email',
        placeholder: 'you@example.com',
        submit: 'continue',
        sending: 'sending',
        confirmationTitle: 'Check your inbox',
        confirmation: 'If this email is linked to your client account, you’ll receive a sign-in link shortly.',
        error: 'We couldn’t send the sign-in link. Please try again.'
    },

    sk: {
        title: 'Všetko o vašom projekte na jednom mieste',
        description: 'Zadajte svoj email a pošleme vám odkaz na prihlásenie. Bez hesla.',
        email: 'Email',
        placeholder: 'vas@email.sk',
        submit: 'pokračovať',
        sending: 'odosielam',
        confirmationTitle: 'Skontrolujte si email',
        confirmation: 'Ak je tento email prepojený s vaším klientskym účtom, čoskoro vám príde odkaz na prihlásenie.',
        error: 'Odkaz na prihlásenie sa nepodarilo odoslať. Skúste to znova.'
    }
};


const root =
    document.querySelector(
        '#client-login'
    )


const {
    copy,
    locale,
    setLocale
} = useAuthLocale(messages)


const email =
    ref(
        root?.dataset.email ||
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
            root?.dataset.status
        )
    )


function showSuccessToast() {
    showToast.value =
        false


    nextTick(() => {
        showToast.value =
            true
    })
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


    try {
        await axios.post(
            '/client/login',
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


        showSuccessToast()
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
            copy.value.error
    } finally {
        sending.value =
            false
    }
}
</script>


<template>
    <AuthLayout
        :locale="locale"
        @set-locale="setLocale"
    >
        <Toast
            v-model="showToast"
            :heading="copy.confirmationTitle"
            :text="copy.confirmation"
            :duration="5000"
        />


        <form
            class="
                w-full
                space-y-8
            "
            @submit.prevent="submit"
        >
            <header
                class="
                    space-y-3
                    text-center
                "
            >
                <h1
                    class="
                        h2
                        text-accent
                    "
                >
                    {{ copy.title }}
                </h1>


                <p
                    class="
                        p
                        uppercase
                    "
                >
                    {{ copy.description }}
                </p>
            </header>


            <FormField
                id="client-email"
                v-model="email"
                type="email"
                :label="copy.email"
                :placeholder="copy.placeholder"
                autocomplete="email"
                :error="error"
                required
                autofocus
            />


            <Button
                type="submit"
                :text="copy.submit"
                :loading-text="copy.sending"
                :loading="sending"
                :disabled="sending"
                :lowercase="true"
            />
        </form>
    </AuthLayout>
</template>