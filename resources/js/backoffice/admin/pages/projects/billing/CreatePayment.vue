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
import InvoicePreview from './InvoicePreview.vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false
    },
    projectId: {
        type: [String, Number],
        required: true
    },
    items: {
        type: Array,
        default: () => []
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
    'created',
    'error',
    'update:busy'
])

const form = reactive({
    billing_item_ids: [],
    issue_date: '',
    delivery_date: '',
    due_date: '',
    notes: ''
})

const selectedItems = computed(() =>
    props.items.filter((item) => form.billing_item_ids.includes(item.id))
)

const selectedTotal = computed(() =>
    selectedItems.value.reduce(
        (total, item) => total + (item.unit_amount * item.quantity),
        0
    )
)

function money(amount, currency = props.currency) {
    return new Intl.NumberFormat('sk-SK', {
        style: 'currency',
        currency
    }).format(Number(amount || 0) / 100)
}

function reset() {
    form.billing_item_ids = props.items.map((item) => item.id)
    form.issue_date = new Date().toISOString().slice(0, 10)
    form.delivery_date = form.issue_date
    form.due_date = ''
    form.notes = ''
}

function toggleItem(itemId) {
    if (form.billing_item_ids.includes(itemId)) {
        form.billing_item_ids = form.billing_item_ids.filter((id) => id !== itemId)
    } else {
        form.billing_item_ids = [...form.billing_item_ids, itemId]
    }
}

async function createPayment() {
    if (!form.billing_item_ids.length) {
        emit('error', 'Select at least one item.')

        return
    }

    emit('update:busy', true)

    try {
        await api.post(
            `/projects/${props.projectId}/billing/invoices`,
            {
                billing_item_ids: form.billing_item_ids,
                issue_date: form.issue_date || null,
                delivery_date: form.delivery_date || null,
                due_date: form.due_date || null,
                notes: form.notes || null
            }
        )

        emit('created', 'Payment created.')
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
        title="Create Payment"
        subtitle="One-time charge with a live invoice PDF preview."
        max-width-class="max-w-7xl"
        body-class="p-0"
        @close="emit('close')"
    >
        <div class="grid max-h-[82vh] overflow-y-auto lg:grid-cols-[minmax(0,0.92fr)_minmax(420px,1.08fr)]">
            <section class="space-y-6 border-b border-accent p-6 lg:border-b-0 lg:border-r">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Items</div>
                    <div class="mt-3 space-y-3">
                        <button
                            v-for="item in items"
                            :key="item.id"
                            type="button"
                            class="w-full border p-4 text-left transition-colors"
                            :class="form.billing_item_ids.includes(item.id)
                                ? 'border-dark bg-accent/10'
                                : 'border-accent bg-light hover:bg-accent/5'"
                            @click="toggleItem(item.id)"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex gap-3">
                                    <span class="mt-1 font-mono text-xs font-bold">
                                        {{ form.billing_item_ids.includes(item.id) ? '[x]' : '[ ]' }}
                                    </span>
                                    <span>
                                        <span class="block font-bold text-dark">{{ item.name }}</span>
                                        <span class="mt-1 block text-sm text-dark/60">
                                            {{ item.quantity }} x {{ money(item.unit_amount, item.currency) }}
                                        </span>
                                    </span>
                                </div>
                                <span class="shrink-0 font-bold text-dark">
                                    {{ money(item.unit_amount * item.quantity, item.currency) }}
                                </span>
                            </div>
                        </button>

                        <div
                            v-if="!items.length"
                            class="border border-accent p-4 text-sm text-dark/60"
                        >
                            Add a one-time item before creating a payment.
                        </div>
                    </div>
                </div>

                <div class="border-t border-accent pt-6">
                    <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Invoice details</div>
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        <FormField v-model="form.issue_date" label="Issue date" type="date" />
                        <FormField v-model="form.delivery_date" label="Delivery date" type="date" />
                        <FormField v-model="form.due_date" label="Due date" type="date" />
                    </div>
                    <div class="mt-4">
                        <FormField v-model="form.notes" label="Notes" type="textarea" />
                    </div>
                </div>

                <div class="grid gap-3 border-t border-accent pt-6 sm:grid-cols-2">
                    <div class="border border-accent p-4">
                        <div class="text-xs uppercase tracking-wide text-dark/60">Selected</div>
                        <div class="mt-1 text-xl font-bold text-dark">{{ selectedItems.length }} items</div>
                    </div>
                    <div class="border border-accent p-4">
                        <div class="text-xs uppercase tracking-wide text-dark/60">Total</div>
                        <div class="mt-1 text-xl font-bold text-dark">{{ money(selectedTotal) }}</div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-accent pt-6">
                    <Button text="Cancel" variant="ghost" @click="emit('close')" />
                    <Button
                        text="Create Payment"
                        variant="primary"
                        :loading="busy"
                        :disabled="!form.billing_item_ids.length"
                        @click="createPayment"
                    />
                </div>
            </section>

            <InvoicePreview
                :project-id="projectId"
                :item-ids="form.billing_item_ids"
                :issue-date="form.issue_date"
                :delivery-date="form.delivery_date"
                :due-date="form.due_date"
                :notes="form.notes"
            />
        </div>
    </Modal>
</template>
