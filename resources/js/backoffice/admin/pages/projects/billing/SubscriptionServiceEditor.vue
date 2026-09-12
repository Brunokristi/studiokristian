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
    item: {
        type: Object,
        default: null
    },
    mode: {
        type: String,
        default: 'edit'
    },
    currency: {
        type: String,
        default: 'EUR'
    },
    busy: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits([
    'close',
    'saved',
    'error',
    'update:busy'
])

const form = reactive({
    name: '',
    unit_amount: '',
    quantity: 1,
    interval: 'month',
    interval_count: 1,
    starts_at: '',
    ends_at: ''
})

const isEditing = computed(() => Boolean(props.item))
const isEnding = computed(() => props.mode === 'end')
const today = new Date().toISOString().slice(0, 10)

function money(amount, currency = props.currency) {
    return new Intl.NumberFormat('sk-SK', {
        style: 'currency',
        currency
    }).format(Number(amount || 0) / 100)
}

function formatDate(value) {
    if (!value) {
        return 'No end date'
    }

    const isoDate = String(value).slice(0, 10)
    const parts = isoDate.match(/^(\d{4})-(\d{2})-(\d{2})$/)

    return parts ? `${parts[3]}.${parts[2]}.${parts[1]}` : 'No end date'
}

function intervalLabel(interval) {
    return {
        day: 'day',
        week: 'week',
        month: 'month',
        year: 'year'
    }[interval] || interval || 'period'
}

const amountInCents = computed(() => Math.round(Number(form.unit_amount || 0) * 100))

const previewItems = computed(() => [
    {
        label: isEditing.value ? 'Current' : 'This service',
        value: `${money(amountInCents.value)} / ${intervalLabel(form.interval)}`
    },
    {
        label: 'Quantity',
        value: String(form.quantity || 1)
    },
    {
        label: 'Effective from',
        value: formatDate(form.starts_at || today)
    },
    {
        label: 'Ends',
        value: form.ends_at ? formatDate(form.ends_at) : 'No end date'
    }
])

const endPreviewItems = computed(() => [
    {
        label: 'Service',
        value: props.item?.name || '-'
    },
    {
        label: 'Currently active',
        value: `${formatDate(props.item?.starts_at)} -> ${props.item?.ends_at ? formatDate(props.item.ends_at) : 'No end date'}`
    },
    {
        label: 'Ends',
        value: form.ends_at ? formatDate(form.ends_at) : '-'
    }
])

function reset() {
    form.name = props.item?.name || ''
    form.unit_amount = (Number(props.item?.unit_amount || 0) / 100).toFixed(2)
    form.quantity = props.item?.quantity || 1
    form.interval = props.item?.interval || 'month'
    form.interval_count = props.item?.interval_count || 1
    form.starts_at = props.item?.starts_at ? String(props.item.starts_at).slice(0, 10) : today
    form.ends_at = props.item?.ends_at ? String(props.item.ends_at).slice(0, 10) : ''
}

async function save() {
    if (!form.name.trim()) {
        emit('error', 'Enter a service name.')

        return
    }

    emit('update:busy', true)

    const payload = isEnding.value
        ? {
            name: props.item.name,
            unit_amount: props.item.unit_amount,
            quantity: props.item.quantity,
            starts_at: props.item.starts_at ? String(props.item.starts_at).slice(0, 10) : null,
            ends_at: form.ends_at || null
        }
        : {
        name: form.name.trim(),
        unit_amount: amountInCents.value,
        quantity: Number(form.quantity || 1),
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null
    }

    try {
        if (props.item) {
            await api.put(
                `/projects/${props.projectId}/billing/items/${props.item.id}`,
                payload
            )
        } else {
            const response = await api.post(
                `/projects/${props.projectId}/billing/items`,
                {
                    ...payload,
                    billing_type: 'recurring',
                    interval: form.interval,
                    interval_count: Number(form.interval_count || 1)
                }
            )

            await api.post(
                `/projects/${props.projectId}/billing/subscription`,
                {
                    billing_item_ids: [response.data.data.id]
                }
            )
        }

        emit('saved', isEnding.value ? 'Service end date updated.' : isEditing.value ? 'Service updated.' : 'Service added to the subscription.')
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
        :title="isEnding ? `End ${item?.name}` : isEditing ? `Edit ${item?.name}` : 'Add service'"
        :subtitle="isEnding ? 'Choose when this service stops existing in the subscription.' : 'Dates decide when this service exists in the subscription. Stripe calculates the billing effect.'"
        max-width-class="max-w-5xl"
        @close="emit('close')"
    >
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section v-if="isEnding" class="space-y-5">
                <div class="border border-accent p-4">
                    <div class="font-bold text-dark">{{ item?.name }}</div>
                    <div class="mt-1 text-sm text-dark/60">
                        {{ money(item?.unit_amount, item?.currency) }} / {{ intervalLabel(item?.interval) }}
                    </div>
                    <div class="mt-4 text-sm text-dark/60">
                        Currently active {{ formatDate(item?.starts_at) }} -> {{ item?.ends_at ? formatDate(item.ends_at) : 'No end date' }}.
                    </div>
                </div>

                <div>
                    <div class="text-xs font-bold uppercase tracking-wide text-dark/60">When should this service end?</div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        <button
                            type="button"
                            class="border p-4 text-left"
                            :class="form.ends_at === today ? 'border-dark bg-accent/10' : 'border-accent'"
                            @click="form.ends_at = today"
                        >
                            <div class="font-bold text-dark">End today</div>
                            <div class="mt-1 text-sm text-dark/60">Effective {{ formatDate(today) }}.</div>
                        </button>

                        <button
                            type="button"
                            class="border p-4 text-left"
                            :class="form.ends_at === '' ? 'border-dark bg-accent/10' : 'border-accent'"
                            @click="form.ends_at = ''"
                        >
                            <div class="font-bold text-dark">No end date</div>
                            <div class="mt-1 text-sm text-dark/60">Keep the service active.</div>
                        </button>

                        <div class="border border-accent p-4">
                            <FormField v-model="form.ends_at" label="Specific date" type="date" />
                        </div>
                    </div>
                </div>

                <div class="border border-accent bg-accent/5 p-4 text-sm text-dark/70">
                    After the end date, this service will no longer be billed. Stripe will calculate any applicable credit or proration.
                </div>
            </section>

            <section v-else class="space-y-5">
                <div
                    v-if="isEditing"
                    class="border border-accent p-4"
                >
                    <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Current</div>
                    <div class="mt-2 text-lg font-bold text-dark">
                        {{ money(item.unit_amount, item.currency) }} / {{ intervalLabel(item.interval) }}
                    </div>
                    <div class="mt-1 text-sm text-dark/60">
                        Quantity {{ item.quantity || 1 }} · {{ formatDate(item.starts_at) }} -> {{ item.ends_at ? formatDate(item.ends_at) : 'No end date' }}
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField v-model="form.name" label="Service" />
                    <FormField v-model="form.unit_amount" label="Price" type="number" min="0" step="0.01" />
                    <FormField v-model="form.quantity" label="Quantity" type="number" min="1" step="1" />
                    <FormField
                        v-model="form.interval"
                        label="Billing interval"
                        type="select"
                        :disabled="isEditing"
                        :options="[
                            { value: 'month', label: 'Monthly' },
                            { value: 'year', label: 'Yearly' },
                            { value: 'week', label: 'Weekly' }
                        ]"
                    />
                    <FormField v-model="form.starts_at" label="Effective from" type="date" />
                    <FormField v-model="form.ends_at" label="Ends" type="date" />
                </div>
            </section>

            <aside class="space-y-4">
                <SubscriptionChangePreview
                    :title="isEnding ? 'End summary' : 'Change summary'"
                    :items="isEnding ? endPreviewItems : previewItems"
                />

                <div class="border border-accent p-4 text-sm text-dark/60">
                    Stripe will calculate any applicable proration for the selected effective date. No existing invoice is rewritten.
                </div>
            </aside>
        </div>

        <template #footer>
            <div class="flex justify-end gap-3 border-t border-accent p-6">
                <Button text="Cancel" variant="ghost" @click="emit('close')" />
                <Button
                    :text="isEnding ? 'End service' : isEditing ? 'Save service' : 'Add service'"
                    variant="primary"
                    :loading="busy"
                    @click="save"
                />
            </div>
        </template>
    </Modal>
</template>
