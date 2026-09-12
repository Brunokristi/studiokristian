<script setup>
import {
    computed,
    ref
} from 'vue'

import Button from '@shared/components/Button.vue'
import Modal from '@shared/components/Modal.vue'
import Tag from '@shared/components/Tag.vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false
    },
    subscription: {
        type: Object,
        default: null
    },
    metrics: {
        type: Object,
        default: () => ({})
    },
    busy: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits([
    'close',
    'add-service',
    'edit-service',
    'end-service',
    'lifecycle',
    'debit-note',
    'invoice'
])

const showMore = ref(false)

const services = computed(() => props.subscription?.items || [])
const invoices = computed(() => (props.subscription?.invoices || []).slice(0, 5))
const history = computed(() => props.subscription?.history || [])

function money(amount, currency = props.metrics.currency || 'EUR') {
    return new Intl.NumberFormat('sk-SK', {
        style: 'currency',
        currency
    }).format(Number(amount || 0) / 100)
}

function formatDate(value) {
    if (!value) {
        return '-'
    }

    const parts = String(value).slice(0, 10).match(/^(\d{4})-(\d{2})-(\d{2})$/)

    return parts ? `${parts[3]}.${parts[2]}.${parts[1]}` : '-'
}

function intervalLabel(interval) {
    return {
        day: 'day',
        week: 'week',
        month: 'month',
        year: 'year',
        mixed: 'mixed intervals'
    }[interval] || interval || 'period'
}

function recurringTotalsLabel(totals = props.subscription?.recurring_totals || []) {
    if (!totals.length) {
        return money(0)
    }

    return totals
        .map((total) => `${money(total.amount, total.currency || props.metrics.currency)} / ${intervalLabel(total.interval)}`)
        .join(' + ')
}

function statusLabel(subscription) {
    if (!subscription) {
        return 'No subscription'
    }

    if (subscription.cancel_at_period_end) {
        return `Cancels ${formatDate(subscription.current_period_end)}`
    }

    if (subscription.ends_at) {
        return `Ends ${formatDate(subscription.ends_at)}`
    }

    return {
        active: 'Active',
        paused: 'Billing paused',
        draft: 'Scheduled',
        past_due: 'Awaiting payment',
        canceled: 'Ended'
    }[subscription.status] || subscription.status
}

function itemStatus(item) {
    const today = new Date().toISOString().slice(0, 10)

    if (item.starts_at && item.starts_at > today) {
        return 'Scheduled'
    }

    if (item.ends_at && item.ends_at < today) {
        return 'Ended'
    }

    return {
        active: 'Active',
        pending: 'Pending',
        canceled: 'Ended'
    }[item.status] || item.status
}
</script>

<template>
    <Modal
        :open="open"
        title="Subscription"
        subtitle="Date-driven recurring billing for this client."
        max-width-class="max-w-7xl"
        body-class="p-0"
        @close="emit('close')"
    >
        <div
            v-if="subscription"
            class="max-h-[84vh] overflow-y-auto p-6"
        >
            <header class="grid gap-6 border-b border-accent pb-6 lg:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-bold text-dark">{{ recurringTotalsLabel() }}</h2>
                        <Tag :text="statusLabel(subscription)" />
                    </div>
                    <div class="mt-3 grid gap-3 text-sm text-dark/70 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <span class="block text-xs uppercase tracking-wide text-dark/50">Started</span>
                            <span class="font-bold text-dark">{{ formatDate(subscription.starts_at) }}</span>
                        </div>
                        <div>
                            <span class="block text-xs uppercase tracking-wide text-dark/50">Ends</span>
                            <span class="font-bold text-dark">{{ subscription.ends_at ? formatDate(subscription.ends_at) : 'No end date' }}</span>
                        </div>
                        <div>
                            <span class="block text-xs uppercase tracking-wide text-dark/50">Next billing</span>
                            <span class="font-bold text-dark">{{ formatDate(subscription.next_billing_at || subscription.current_period_end) }}</span>
                        </div>
                        <div>
                            <span class="block text-xs uppercase tracking-wide text-dark/50">Payment method</span>
                            <span class="font-bold text-dark">{{ subscription.payment_method_label || 'Not saved yet' }}</span>
                        </div>
                    </div>
                </div>

                <div class="relative flex items-start justify-end gap-3">
                    <Button text="Add service" variant="secondary" :disabled="busy" @click="emit('add-service')" />
                    <Button text="More actions" variant="ghost" @click="showMore = !showMore" />

                    <div
                        v-if="showMore"
                        class="absolute right-0 top-10 z-10 w-56 border border-accent bg-light p-3 shadow-xl"
                    >
                        <button
                            type="button"
                            class="block w-full px-2 py-2 text-left text-sm font-bold text-dark hover:bg-accent/10"
                            @click="emit('lifecycle', subscription.status === 'paused' ? 'resume' : 'pause'); showMore = false"
                        >
                            {{ subscription.status === 'paused' ? 'Resume billing' : 'Pause billing' }}
                        </button>
                        <button
                            type="button"
                            class="block w-full px-2 py-2 text-left text-sm font-bold text-red-600 hover:bg-red-50"
                            @click="emit('lifecycle', 'cancel'); showMore = false"
                        >
                            Cancel subscription
                        </button>
                        <button
                            type="button"
                            class="block w-full px-2 py-2 text-left text-sm font-bold text-dark hover:bg-accent/10"
                            @click="emit('debit-note'); showMore = false"
                        >
                            Create debit note
                        </button>
                    </div>
                </div>
            </header>

            <section class="grid gap-6 border-b border-accent py-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Subscription timeline</div>
                    <div class="mt-5 border border-accent p-5">
                        <div class="flex items-center gap-3">
                            <div class="h-3 w-3 border border-dark bg-dark" />
                            <div class="h-px flex-1 bg-dark/40" />
                            <div class="h-3 w-3 border border-dark bg-light" />
                        </div>
                        <div class="mt-3 flex justify-between gap-6 text-sm">
                            <div>
                                <div class="font-bold text-dark">{{ formatDate(subscription.current_period_start || subscription.starts_at) }}</div>
                                <div class="text-dark/60">Start</div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-dark">{{ formatDate(subscription.cancel_at_period_end ? subscription.current_period_end : (subscription.ends_at || subscription.current_period_end)) }}</div>
                                <div class="text-dark/60">{{ subscription.cancel_at_period_end || subscription.ends_at ? 'End' : 'Next billing' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="border border-accent p-5">
                    <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Billing</div>
                    <div class="mt-4 space-y-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <span class="text-dark/60">Current period</span>
                            <span class="font-bold text-dark">{{ formatDate(subscription.current_period_start) }} -> {{ formatDate(subscription.current_period_end) }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="text-dark/60">Next invoice</span>
                            <span class="font-bold text-dark">{{ subscription.next_billing_amount ? money(subscription.next_billing_amount) : recurringTotalsLabel() }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="text-dark/60">Billing email</span>
                            <span class="font-bold text-dark">{{ subscription.billing_email || '-' }}</span>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="border-b border-accent py-6">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Services</div>
                        <div class="mt-1 text-sm text-dark/60">Each service has its own start and end dates.</div>
                    </div>
                    <Button text="Add service" variant="secondary" :disabled="busy" @click="emit('add-service')" />
                </div>

                <div class="grid gap-3 lg:grid-cols-2">
                    <article
                        v-for="item in services"
                        :key="item.id"
                        class="border border-accent p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-bold text-dark">{{ item.name }}</div>
                                <div class="mt-1 text-sm text-dark/60">
                                    {{ money(item.unit_amount, item.currency) }} / {{ intervalLabel(item.interval) }}
                                </div>
                            </div>
                            <Tag :text="itemStatus(item)" />
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="block text-xs uppercase tracking-wide text-dark/50">Starts</span>
                                <span class="font-bold text-dark">{{ formatDate(item.starts_at) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs uppercase tracking-wide text-dark/50">Ends</span>
                                <span class="font-bold text-dark">{{ item.ends_at ? formatDate(item.ends_at) : 'No end date' }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap justify-end gap-3 border-t border-accent pt-4">
                            <Button text="History" variant="ghost" disabled />
                            <Button text="Edit" variant="ghost" :disabled="busy" @click="emit('edit-service', item)" />
                            <Button
                                v-if="item.status !== 'canceled'"
                                text="End"
                                variant="ghost"
                                :disabled="busy"
                                @click="emit('end-service', item)"
                            />
                        </div>
                    </article>
                </div>
            </section>

            <section class="grid gap-6 py-6 lg:grid-cols-2">
                <div>
                    <div class="mb-3 text-xs font-bold uppercase tracking-wide text-dark/60">Recent invoices</div>
                    <div class="border border-accent">
                        <button
                            v-for="invoice in invoices"
                            :key="invoice.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-4 border-b border-accent/40 p-4 text-left last:border-b-0 hover:bg-accent/10"
                            @click="emit('invoice', invoice)"
                        >
                            <span>
                                <span class="block font-bold text-dark">{{ invoice.invoice_number }}</span>
                                <span class="text-sm text-dark/60">{{ formatDate(invoice.issue_date) }}</span>
                            </span>
                            <span class="text-right">
                                <span class="block font-bold text-dark">{{ money(invoice.total, invoice.currency) }}</span>
                                <span class="text-sm text-dark/60">{{ invoice.status }}</span>
                            </span>
                        </button>

                        <div
                            v-if="!invoices.length"
                            class="p-4 text-sm text-dark/60"
                        >
                            No subscription invoices yet.
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-3 text-xs font-bold uppercase tracking-wide text-dark/60">History</div>
                    <div class="border border-accent p-4">
                        <div
                            v-for="entry in history"
                            :key="entry.id || `${entry.date}-${entry.label}`"
                            class="border-b border-accent/40 py-3 first:pt-0 last:border-b-0 last:pb-0"
                        >
                            <div class="font-bold text-dark">{{ formatDate(entry.date) }}</div>
                            <div class="text-sm text-dark/60">{{ entry.label || entry.description }}</div>
                        </div>

                        <div
                            v-if="!history.length"
                            class="text-sm text-dark/60"
                        >
                            History will appear here as subscription activity is recorded.
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </Modal>
</template>
