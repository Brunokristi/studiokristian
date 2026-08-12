<script setup>
import axios from 'axios'
import { ref } from 'vue'

const root = document.querySelector('#client-login')
const email = ref(root?.dataset.email || '')
const status = ref(root?.dataset.status || '')
const error = ref(root?.dataset.error || '')
const sending = ref(false)

async function submit() {
    sending.value = true
    status.value = ''
    error.value = ''

    try {
        const response = await axios.post('/client/login', { email: email.value }, {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        })
        status.value = response.data.message
        email.value = ''
    } catch (exception) {
        error.value = exception.response?.data?.errors?.email?.[0] || 'Prihlasovací odkaz sa nepodarilo odoslať.'
    } finally {
        sending.value = false
    }
}
</script>

<template>
    <section class="client-login">
        <p class="portal-muted client-login__eyebrow">Bezpečný prístup</p>
        <h1 class="client-login__title">Váš projekt,<br>na jednom mieste.</h1>
        <p class="portal-muted client-login__intro">Zadajte pracovný email. Pošleme vám jednorazový prihlasovací odkaz platný 10 minút.</p>

        <div v-if="status" class="portal-panel client-login__message client-login__message--success" role="status">{{ status }}</div>
        <div v-if="error" class="portal-panel client-login__message client-login__message--error" role="alert">{{ error }}</div>

        <form @submit.prevent="submit">
            <label class="portal-label" for="client-email">Email</label>
            <input id="client-email" v-model="email" class="portal-input" type="email" autocomplete="email" required autofocus>
            <button class="portal-button client-login__submit" type="submit" :disabled="sending">
                {{ sending ? 'Odosielam…' : 'Poslať prihlasovací odkaz' }}
                <i v-if="!sending" class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </form>
    </section>
</template>

<style scoped>
.client-login { max-width: 520px; margin: 6vh auto 0; }
.client-login__eyebrow { margin: 0; font-size: 12px; text-transform: uppercase; }
.client-login__title { margin: 14px 0 18px; font-size: clamp(32px, 7vw, 56px); line-height: 1; letter-spacing: 0; }
.client-login__intro { margin-bottom: 32px; font-family: sans-serif; line-height: 1.55; }
.client-login__message { margin-bottom: 20px; padding: 16px; }
.client-login__message--success { border-color: #718315; }
.client-login__message--error { border-color: #9f2d20; }
.client-login__submit { width: 100%; margin-top: 12px; background: var(--portal-accent); color: var(--portal-ink); }
.client-login__submit:disabled { cursor: wait; opacity: .65; }
</style>