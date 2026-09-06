<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api, { errorMessage } from '../../composables/useAdminApi'
import Button from '@shared/components/Button.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'

const props = defineProps({
    id: { type: String, required: true },
    companyId: { type: String, required: true }
})

const router = useRouter()
const loading = ref(true)
const error = ref('')
const showErrorToast = ref(false)
const billing = ref(null)
const syncing = ref(false)

function money(amount, currency = 'EUR') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: String(currency || 'EUR').toUpperCase()
    }).format(Number(amount || 0) / 100)
}

function date(value) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' }).format(new Date(value))
}

function showError(message) {
    error.value = message || 'The request could not be completed.'
    showErrorToast.value = true
}

async function load() {
    try {
        const response = await api.get(`/saas/projects/${props.id}/customers/${props.companyId}`)
        billing.value = response.data

        try {
            const syncResponse = await api.post(`/saas/projects/${props.id}/customers/${props.companyId}/sync-stripe-history`)
            billing.value = syncResponse.data
        } catch {
        }
    } catch (exception) {
        showError(errorMessage(exception))
    } finally {
        loading.value = false
    }
}

async function syncStripeHistory() {
    syncing.value = true

    try {
        const response = await api.post(`/saas/projects/${props.id}/customers/${props.companyId}/sync-stripe-history`)
        billing.value = response.data
    } catch (exception) {
        showError(errorMessage(exception))
    } finally {
        syncing.value = false
    }
}

onMounted(load)
</script>

<template>
    <div class="w-full space-y-10">
        <Toast v-model="showErrorToast" heading="Something went wrong" :text="error" />

        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="p uppercase text-dark/40">Customer billing</p>
                <h1 class="h1 text-accent">
                    {{ billing?.company?.name || 'Loading...' }}
                </h1>
            </div>
            <Button type="button" text="Back to customers" align="right" @click="router.back()" />
            <Button type="button" text="Sync Stripe history" align="right" :loading="syncing" loading-text="Syncing..." @click="syncStripeHistory" />
        </div>

        <div v-if="loading" class="p uppercase text-dark/50">Loading billing history...</div>

        <template v-else-if="billing">
            <section class="space-y-5">
                <h2 class="h2 text-accent">Company</h2>
                <div class="grid gap-4 border border-accent p-5 sm:grid-cols-2">
                    <p class="p"><strong>Name:</strong> {{ billing.company.name }}</p>
                    <p class="p"><strong>Email:</strong> {{ billing.company.email || '—' }}</p>
                    <p class="p"><strong>Phone:</strong> {{ billing.company.phone || '—' }}</p>
                    <p class="p"><strong>Stripe customer:</strong> {{ billing.company.stripe_customer_id || '—' }}</p>
                </div>
            </section>

            <section class="space-y-5">
                <h2 class="h2 text-accent">Current subscription</h2>
                <div v-if="billing.subscriptions?.length" class="space-y-3">
                    <div v-for="subscription in billing.subscriptions" :key="subscription.id" class="border border-accent p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <span class="h3">{{ subscription.plan?.name || '—' }}</span>
                            <Tag size="sm" tone="neutral" :label="subscription.status" />
                        </div>
                        <p class="p mt-3">
                            {{ money(subscription.price?.amount, subscription.price?.currency) }} /
                            {{ subscription.price?.interval || '—' }}
                        </p>
                        <p class="p mt-2 text-dark/50">
                            {{ date(subscription.current_period_start) }} – {{ date(subscription.current_period_end) }}
                        </p>
                    </div>
                </div>
                <p v-else class="p text-dark/50">No subscription.</p>
            </section>

            <section class="space-y-5">
                <h2 class="h2 text-accent">Invoices</h2>
                <div class="overflow-x-auto border border-accent">
                    <table class="w-full min-w-[700px] border-collapse text-left">
                        <thead><tr class="border-b border-accent"><th class="p-4 h3">Invoice</th><th class="p-4 h3">Date</th><th class="p-4 h3">Period</th><th class="p-4 h3">Amount</th><th class="p-4 h3">Status</th><th class="p-4 h3"></th></tr></thead>
                        <tbody>
                            <tr v-for="invoice in billing.invoices" :key="invoice.id" class="border-b border-accent/20">
                                <td class="p-4">{{ invoice.invoice_number || '—' }}</td>
                                <td class="p-4">{{ date(invoice.invoice_date) }}</td>
                                <td class="p-4">{{ date(invoice.period_start) }} – {{ date(invoice.period_end) }}</td>
                                <td class="p-4">{{ money(invoice.amount_paid, invoice.currency) }}</td>
                                <td class="p-4"><Tag size="sm" tone="neutral" :label="invoice.status || 'unknown'" /></td>
                                <td class="p-4"><a v-if="invoice.hosted_invoice_url" :href="invoice.hosted_invoice_url" target="_blank" rel="noopener noreferrer" class="p text-accent">view</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-5">
                <h2 class="h2 text-accent">Transactions</h2>
                <div class="overflow-x-auto border border-accent">
                    <table class="w-full min-w-[650px] border-collapse text-left">
                        <thead><tr class="border-b border-accent"><th class="p-4 h3">Date</th><th class="p-4 h3">Amount</th><th class="p-4 h3">Status</th><th class="p-4 h3">Method</th><th class="p-4 h3">Invoice</th></tr></thead>
                        <tbody>
                            <tr v-for="payment in billing.payments" :key="payment.id" class="border-b border-accent/20">
                                <td class="p-4">{{ date(payment.paid_at) }}</td>
                                <td class="p-4">{{ money(payment.amount, payment.currency) }}</td>
                                <td class="p-4"><Tag size="sm" tone="neutral" :label="payment.status || 'unknown'" /></td>
                                <td class="p-4">{{ payment.payment_method_brand ? `${payment.payment_method_brand} •••• ${payment.payment_method_last4 || ''}` : '—' }}</td>
                                <td class="p-4">{{ payment.saas_invoice_id || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>
