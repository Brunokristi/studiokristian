<script setup>
import {
    computed,
    reactive,
    watch
} from 'vue'

import api, { errorMessage } from '../../../composables/useAdminApi'
import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Modal from '@shared/components/Modal.vue'
import Tag from '@shared/components/Tag.vue'
import SubscriptionChangePreview from './SubscriptionChangePreview.vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false
    },
    projectId: {
        type: [String, Number],
        required: true
    },
    services: {
        type: Array,
        default: () => []
    },
    currency: {
        type: String,
        default: 'EUR'
    },
    hasSavedPaymentMethod: {
        type: Boolean,
        default: false
    },
    busy: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits([
    'close',
    'created',
    'error',
    'update:busy'
])

const form = reactive({
    service_ids: [],
    starts_at: '',
    ends_at: '',
    collection_method: 'send_invoice',
    step: 'compose'
})

const today = new Date().toISOString().slice(0, 10)

const selectableServices = computed(() =>
    props.services.filter(
        (service) => ['pending', 'active'].includes(service.status) && !service.project_subscription_id
    )
)

const selectedServices = computed(() =>
    selectableServices.value.filter((service) => form.service_ids.includes(service.id))
)

const selectedTotals = computed(() => {
    const totals = new Map()

    selectedServices.value.forEach((service) => {
        const interval = service.interval || 'month'
        const existing = totals.get(interval) || {
            interval,
            amount: 0,
            currency: service.currency || props.currency
        }

        existing.amount += service.unit_amount * service.quantity
        totals.set(interval, existing)
    })

    return [...totals.values()]
})

const recurringTotal = computed(() => {
    if (!selectedTotals.value.length) {
        return money(0)
    }

    return selectedTotals.value
        .map((total) => `${money(total.amount, total.currency)} / ${intervalLabel(total.interval)}`)
        .join(' + ')
})

const startsInPast = computed(() => form.starts_at && form.starts_at < today)
const startsInFuture = computed(() => form.starts_at && form.starts_at > today)

const reviewItems = computed(() => [
    {
        label: 'Services',
        value: `${selectedServices.value.length}`
    },
    {
        label: 'Recurring total',
        value: recurringTotal.value
    },
    {
        label: 'Starts',
        value: formatDate(form.starts_at)
    },
    {
        label: 'Ends',
        value: form.ends_at ? formatDate(form.ends_at) : 'No end date'
    },
    {
        label: 'First payment',
        value: form.collection_method === 'send_invoice' ? 'Invoice sent to customer' : 'Saved payment method'
    }
])

function money(amount, currency = props.currency) {
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
        year: 'year'
    }[interval] || interval || 'period'
}

function toggleService(serviceId) {
    if (form.service_ids.includes(serviceId)) {
        form.service_ids = form.service_ids.filter((id) => id !== serviceId)
    } else {
        form.service_ids = [...form.service_ids, serviceId]
    }
}

function reset() {
    form.service_ids = selectableServices.value.map((service) => service.id)
    form.starts_at = selectableServices.value
        .map((service) => service.starts_at)
        .filter(Boolean)
        .sort()[0] || today
    form.ends_at = ''
    form.collection_method = 'send_invoice'
    form.step = 'compose'
}

function review() {
    if (!form.service_ids.length) {
        emit('error', 'Select at least one recurring service.')

        return
    }

    form.step = 'review'
}

async function createSubscription() {
    emit('update:busy', true)

    try {
        await api.post(
            `/projects/${props.projectId}/billing/subscription`,
            {
                billing_item_ids: form.service_ids,
                collection_method: form.collection_method,
                starts_at: form.starts_at || null,
                ends_at: form.ends_at || null
            }
        )

        emit('created', 'Subscription created.')
    } catch (exception) {
        emit('error', errorMessage(exception))
    } finally {
        emit('update:busy', false)
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset()
        }
    },
    { immediate: true }
)
</script>

<template>
    <Modal
        :open="open"
        title="Create Subscription"
        subtitle="Ongoing recurring services. Dates define when billing starts and ends."
        max-width-class="max-w-6xl"
        body-class="p-0"
        @close="emit('close')"
    >
        <div class="max-h-[82vh] overflow-y-auto p-6">
            <template v-if="form.step === 'compose'">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <section class="space-y-6">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Services</div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <button
                                    v-for="service in selectableServices"
                                    :key="service.id"
                                    type="button"
                                    class="border p-4 text-left transition-colors"
                                    :class="form.service_ids.includes(service.id)
                                        ? 'border-dark bg-accent/10'
                                        : 'border-accent bg-light hover:bg-accent/5'"
                                    @click="toggleService(service.id)"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-bold text-dark">{{ service.name }}</div>
                                            <div class="mt-1 text-sm text-dark/60">
                                                {{ money(service.unit_amount, service.currency) }} / {{ intervalLabel(service.interval) }}
                                            </div>
                                        </div>
                                        <span class="font-mono text-xs font-bold">
                                            {{ form.service_ids.includes(service.id) ? '[x]' : '[ ]' }}
                                        </span>
                                    </div>
                                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs text-dark/60">
                                        <div>
                                            <span class="block uppercase tracking-wide">Starts</span>
                                            <span class="font-bold text-dark">{{ formatDate(service.starts_at || form.starts_at) }}</span>
                                        </div>
                                        <div>
                                            <span class="block uppercase tracking-wide">Ends</span>
                                            <span class="font-bold text-dark">{{ service.ends_at ? formatDate(service.ends_at) : 'No end date' }}</span>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-accent pt-6">
                            <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Subscription dates</div>
                            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                <FormField v-model="form.starts_at" label="Starts" type="date" />
                                <FormField v-model="form.ends_at" label="Ends" type="date" />
                            </div>
                            <p class="mt-3 text-sm text-dark/60">
                                The subscription billing schedule is based on the start date, not the date the customer pays the first invoice.
                            </p>

                            <div
                                v-if="startsInPast"
                                class="mt-3 border border-accent bg-accent/5 p-3 text-sm text-dark/70"
                            >
                                Starts in the past. Stripe will calculate the amount for the elapsed billing period.
                            </div>

                            <div
                                v-if="startsInFuture"
                                class="mt-3 border border-accent bg-accent/5 p-3 text-sm text-dark/70"
                            >
                                Scheduled. No recurring service will be billed before {{ formatDate(form.starts_at) }}.
                            </div>
                        </div>

                        <div class="border-t border-accent pt-6">
                            <div class="text-xs font-bold uppercase tracking-wide text-dark/60">First payment</div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    class="border p-4 text-left"
                                    :class="form.collection_method === 'send_invoice' ? 'border-dark bg-accent/10' : 'border-accent'"
                                    @click="form.collection_method = 'send_invoice'"
                                >
                                    <div class="font-bold text-dark">Send invoice to customer</div>
                                    <div class="mt-1 text-sm text-dark/60">Customer receives the first invoice and pays it.</div>
                                </button>

                                <button
                                    type="button"
                                    class="border p-4 text-left disabled:cursor-not-allowed disabled:opacity-40"
                                    :class="form.collection_method === 'charge_automatically' ? 'border-dark bg-accent/10' : 'border-accent'"
                                    :disabled="!hasSavedPaymentMethod"
                                    @click="form.collection_method = 'charge_automatically'"
                                >
                                    <div class="font-bold text-dark">Charge saved payment method</div>
                                    <div class="mt-1 text-sm text-dark/60">
                                        {{ hasSavedPaymentMethod ? 'Use the saved payment method.' : 'No saved payment method is available yet.' }}
                                    </div>
                                </button>
                            </div>
                        </div>
                    </section>

                    <aside class="space-y-4">
                        <section class="border border-accent bg-light p-5">
                            <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Summary</div>
                            <div class="mt-3 text-2xl font-bold text-dark">{{ recurringTotal }}</div>
                            <div class="mt-4 space-y-3 text-sm">
                                <div class="flex justify-between gap-4">
                                    <span class="text-dark/60">Services</span>
                                    <span class="font-bold text-dark">{{ selectedServices.length }}</span>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <span class="text-dark/60">Starts</span>
                                    <span class="font-bold text-dark">{{ formatDate(form.starts_at) }}</span>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <span class="text-dark/60">Ends</span>
                                    <span class="font-bold text-dark">{{ form.ends_at ? formatDate(form.ends_at) : 'No end date' }}</span>
                                </div>
                            </div>
                        </section>

                        <SubscriptionChangePreview
                            title="First invoice"
                            summary="Stripe will create and calculate the first subscription invoice from these services and dates."
                        />
                    </aside>
                </div>
            </template>

            <template v-else>
                <div class="mx-auto max-w-3xl space-y-5">
                    <SubscriptionChangePreview
                        title="Review subscription"
                        :items="reviewItems"
                    />

                    <div class="border border-accent">
                        <div
                            v-for="service in selectedServices"
                            :key="service.id"
                            class="flex items-center justify-between gap-4 border-b border-accent/40 p-4 last:border-b-0"
                        >
                            <div>
                                <div class="font-bold text-dark">{{ service.name }}</div>
                                <div class="mt-1 text-sm text-dark/60">
                                    {{ money(service.unit_amount, service.currency) }} / {{ intervalLabel(service.interval) }}
                                </div>
                            </div>
                            <Tag :text="service.starts_at && service.starts_at > today ? 'Scheduled' : 'Included'" />
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3 border-t border-accent p-6">
                <Button
                    :text="form.step === 'review' ? 'Back' : 'Cancel'"
                    variant="ghost"
                    @click="form.step === 'review' ? form.step = 'compose' : emit('close')"
                />
                <Button
                    v-if="form.step === 'compose'"
                    text="Review subscription"
                    variant="primary"
                    :disabled="!form.service_ids.length"
                    @click="review"
                />
                <Button
                    v-else
                    text="Create Subscription"
                    variant="primary"
                    :loading="busy"
                    @click="createSubscription"
                />
            </div>
        </template>
    </Modal>
</template>
