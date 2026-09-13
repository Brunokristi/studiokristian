<script setup>
import {
    computed,
    onMounted,
    reactive,
    ref
} from 'vue'

import api, {
    errorMessage
} from '../../composables/useAdminApi'

import AdminDataTable from '@shared/components/DataTable.vue'
import AdminConfirmDialog from '../../../../shared/components/ConfirmDialog.vue'
import Button from '@shared/components/Button.vue'
import CreatePayment from './billing/CreatePayment.vue'
import CreateSubscription from './billing/CreateSubscription.vue'
import FormField from '@shared/components/FormField.vue'
import Modal from '@shared/components/Modal.vue'
import ProjectSubscriptionManagement from './billing/ProjectSubscriptionManagement.vue'
import SubscriptionLifecycleDialog from './billing/SubscriptionLifecycleDialog.vue'
import SubscriptionServiceEditor from './billing/SubscriptionServiceEditor.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'

import { useAdminPageHeader } from '../../composables/useAdminPageHeader'

const props = defineProps({
    id: {
        type: String,
        default: ''
    }
})

const loading = ref(true)
const busy = ref(false)
const error = ref('')
const showErrorToast = ref(false)
const showSuccessToast = ref(false)
const successMessage = ref('')

const project = ref(null)
const billingItems = ref([])
const subscription = ref(null)
const invoices = ref([])
const metrics = ref({})
const recipients = ref([])

const showItemModal = ref(false)
const showPaymentWorkflow = ref(false)
const showSubscriptionCreateWorkflow = ref(false)
const showSubscriptionManagement = ref(false)
const showServiceEditor = ref(false)
const showInvoiceDetailModal = ref(false)
const showSendModal = ref(false)
const showPaymentModal = ref(false)
const showRefundModal = ref(false)
const showDebitNoteModal = ref(false)
const showLifecycleDialog = ref(false)

const editingItem = ref(null)
const editingSubscriptionService = ref(null)
const serviceEditorMode = ref('edit')
const openedInvoice = ref(null)
const payingInvoice = ref(null)
const sendingInvoice = ref(null)
const selectedRecipients = ref([])
const lifecycleAction = ref('cancel')

const paymentForm = reactive({
    paid_at: '',
    payment_method: 'bank_transfer'
})

const refundForm = reactive({
    amount: '',
    reason: ''
})

const debitNoteForm = reactive({
    description: '',
    amount: ''
})

const confirmState = reactive({
    open: false,
    title: '',
    text: '',
    confirmLabel: 'Confirm',
    action: null
})

const itemForm = reactive({
    name: '',
    unit_amount: '',
    quantity: 1,
    billing_type: 'one_time',
    interval: 'month',
    interval_count: 1,
    starts_at: '',
    ends_at: ''
})

useAdminPageHeader(computed(() => ({
    title: project.value?.name
        ? `Billing - ${project.value.name}`
        : 'Billing',
    subtitle: project.value?.company?.name || ''
})))

const oneTimeItems = computed(() =>
    billingItems.value.filter((item) => item.billing_type === 'one_time')
)

const recurringItems = computed(() =>
    billingItems.value.filter((item) => item.billing_type === 'recurring')
)

const recurringItemsAvailableForSubscription = computed(() =>
    recurringItems.value.some(
        (item) =>
            ['pending', 'active'].includes(item.status) &&
            !item.project_subscription_id
    )
)

const recipientOptions = computed(() =>
    recipients.value.map((recipient) => ({
        value: recipient.email,
        label: recipient.name
            ? `${recipient.name} - ${recipient.email}`
            : recipient.email
    }))
)

const invoiceColumns = [
    { key: 'invoice_number', label: 'Number' },
    { key: 'issue_date', label: 'Issued' },
    { key: 'due_date', label: 'Due' },
    { key: 'total', label: 'Amount' },
    { key: 'status', label: 'Status' },
    { key: 'payment_status', label: 'Payment' },
    { key: 'billing_source', label: 'Type' },
]

const recurringColumns = [
    { key: 'name', label: 'Service' },
    { key: 'unit_amount', label: 'Price' },
    { key: 'starts_at', label: 'Starts' },
    { key: 'ends_at', label: 'Ends' },
    { key: 'status', label: 'Status' },
]

const oneTimeColumns = [
    { key: 'name', label: 'Item' },
    { key: 'unit_amount', label: 'Price' },
    { key: 'quantity', label: 'Qty' },
    { key: 'status', label: 'Status' },
]

function money(amount, currency = metrics.value.currency || 'EUR') {
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

function dateInputValue(value) {
    return value ? String(value).slice(0, 10) : ''
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

function recurringTotalsLabel(totals = metrics.value.recurring_totals || []) {
    if (!totals.length) {
        return money(0)
    }

    return totals
        .map((total) => `${money(total.amount, total.currency || metrics.value.currency)} / ${intervalLabel(total.interval)}`)
        .join(' + ')
}

function invoiceType(invoice) {
    return invoice.billing_source === 'subscription' || invoice.project_subscription_id
        ? 'Subscription'
        : 'Payment'
}

function humanStatus(status) {
    return {
        active: 'Active',
        paused: 'Billing paused',
        draft: 'Scheduled',
        pending: 'Pending',
        past_due: 'Awaiting payment',
        canceled: 'Ended',
        invoiced: 'Invoiced',
        open: 'Open',
        paid: 'Paid',
        unpaid: 'Unpaid',
        failed: 'Failed',
        void: 'Void',
        uncollectible: 'Uncollectible'
    }[status] || status || '-'
}

function subscriptionStatusLabel() {
    if (!subscription.value) {
        return 'No subscription'
    }

    if (subscription.value.cancel_at_period_end) {
        return `Cancels ${formatDate(subscription.value.current_period_end)}`
    }

    if (subscription.value.ends_at) {
        return `Ends ${formatDate(subscription.value.ends_at)}`
    }

    return humanStatus(subscription.value.status)
}

function itemStatus(item) {
    const today = new Date().toISOString().slice(0, 10)

    if (item.starts_at && item.starts_at > today) {
        return 'Scheduled'
    }

    if (item.ends_at && item.ends_at < today) {
        return 'Ended'
    }

    return humanStatus(item.status)
}

function notifyError(message) {
    error.value = message
    showErrorToast.value = true
}

function notifySuccess(message) {
    successMessage.value = message
    showSuccessToast.value = true
}

async function load() {
    loading.value = true

    try {
        const billing = await api.get(`/projects/${props.id}/billing`)

        project.value = billing.data.project
        billingItems.value = billing.data.billing_items || []
        subscription.value = billing.data.subscription
        invoices.value = billing.data.invoices || []
        metrics.value = billing.data.metrics || {}
        recipients.value = billing.data.recipients || []
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        loading.value = false
    }
}

function finishWorkflow(message) {
    showPaymentWorkflow.value = false
    showSubscriptionCreateWorkflow.value = false
    showServiceEditor.value = false
    notifySuccess(message)
    load()
}

function openItemModal(type) {
    editingItem.value = null

    Object.assign(itemForm, {
        name: '',
        unit_amount: '',
        quantity: 1,
        billing_type: type,
        interval: 'month',
        interval_count: 1,
        starts_at: '',
        ends_at: ''
    })

    showItemModal.value = true
}

function openItemEditor(item) {
    editingItem.value = item

    Object.assign(itemForm, {
        name: item.name || '',
        unit_amount: (Number(item.unit_amount || 0) / 100).toFixed(2),
        quantity: item.quantity || 1,
        billing_type: item.billing_type,
        interval: item.interval || 'month',
        interval_count: item.interval_count || 1,
        starts_at: dateInputValue(item.starts_at),
        ends_at: dateInputValue(item.ends_at)
    })

    showItemModal.value = true
}

function openSubscriptionServiceEditor(item = null, mode = item ? 'edit' : 'add') {
    editingSubscriptionService.value = item
    serviceEditorMode.value = mode
    showServiceEditor.value = true
}

async function saveItem() {
    if (!itemForm.name.trim()) {
        notifyError('Enter a name for the billing item.')

        return
    }

    busy.value = true

    const payload = {
        name: itemForm.name.trim(),
        unit_amount: Math.round(Number(itemForm.unit_amount) * 100),
        quantity: Number(itemForm.quantity),
        starts_at: itemForm.starts_at || null,
        ends_at: itemForm.ends_at || null
    }

    try {
        if (editingItem.value) {
            await api.put(
                `/projects/${props.id}/billing/items/${editingItem.value.id}`,
                payload
            )
        } else {
            await api.post(
                `/projects/${props.id}/billing/items`,
                {
                    ...payload,
                    billing_type: itemForm.billing_type,
                    interval: itemForm.billing_type === 'recurring' ? itemForm.interval : null,
                    interval_count: itemForm.billing_type === 'recurring' ? Number(itemForm.interval_count) : 1
                }
            )
        }

        showItemModal.value = false
        notifySuccess(editingItem.value ? 'Billing item updated.' : 'Billing item added.')
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}

function confirmRemoveItem(item) {
    confirmState.open = true
    confirmState.title = 'Remove billing item?'
    confirmState.text = `"${item.name}" will be removed from this project. Invoices already issued keep their line items.`
    confirmState.confirmLabel = 'Remove'
    confirmState.action = () => removeItem(item)
}

async function removeItem(item) {
    confirmState.open = false
    busy.value = true

    try {
        await api.delete(`/projects/${props.id}/billing/items/${item.id}`)
        showItemModal.value = false
        notifySuccess('Billing item removed.')
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}

function openInvoiceDetail(invoice) {
    openedInvoice.value = invoice
    showInvoiceDetailModal.value = true
}

function openSendModal(invoice) {
    if (!recipients.value.length) {
        notifyError('This client has no contact with an email address. Add one on the client first.')

        return
    }

    sendingInvoice.value = invoice
    showInvoiceDetailModal.value = false

    selectedRecipients.value = [
        invoice.customer_email && recipients.value.some((recipient) => recipient.email === invoice.customer_email)
            ? invoice.customer_email
            : recipients.value[0].email
    ]

    showSendModal.value = true
}

async function sendInvoice() {
    if (!selectedRecipients.value.length) {
        notifyError('Select at least one recipient.')

        return
    }

    busy.value = true

    try {
        const response = await api.post(
            `/projects/${props.id}/billing/invoices/${sendingInvoice.value.id}/send`,
            { recipients: selectedRecipients.value }
        )

        const sentTo = response.data.recipients || selectedRecipients.value
        showSendModal.value = false
        notifySuccess(`Invoice ${sendingInvoice.value.invoice_number} sent to ${sentTo.join(', ')}.`)
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}

function openPaymentModal(invoice) {
    payingInvoice.value = invoice
    showInvoiceDetailModal.value = false
    paymentForm.paid_at = new Date().toISOString().slice(0, 10)
    paymentForm.payment_method = 'bank_transfer'
    showPaymentModal.value = true
}

async function recordPayment() {
    busy.value = true

    try {
        await api.post(
            `/projects/${props.id}/billing/invoices/${payingInvoice.value.id}/record-payment`,
            {
                paid_at: paymentForm.paid_at || null,
                payment_method: paymentForm.payment_method
            }
        )

        showPaymentModal.value = false
        notifySuccess(`Invoice ${payingInvoice.value.invoice_number} marked as paid.`)
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}

function openRefundModal(invoice) {
    payingInvoice.value = invoice
    refundForm.amount = ((invoice.amount_paid || invoice.total || 0) / 100).toFixed(2)
    refundForm.reason = ''
    showInvoiceDetailModal.value = false
    showRefundModal.value = true
}

async function refundInvoice() {
    busy.value = true

    try {
        await api.post(
            `/projects/${props.id}/billing/invoices/${payingInvoice.value.id}/refund`,
            {
                amount: Math.round(Number(refundForm.amount || 0) * 100),
                reason: refundForm.reason || null
            }
        )

        showRefundModal.value = false
        notifySuccess('Stripe refund created.')
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}

function openDebitNoteModal() {
    debitNoteForm.description = ''
    debitNoteForm.amount = ''
    showSubscriptionManagement.value = false
    showDebitNoteModal.value = true
}

async function createDebitNote() {
    busy.value = true

    try {
        await api.post(
            `/projects/${props.id}/billing/debit-notes`,
            {
                description: debitNoteForm.description,
                amount: Math.round(Number(debitNoteForm.amount || 0) * 100)
            }
        )

        showDebitNoteModal.value = false
        notifySuccess('Debit note created and sent to the client.')
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}

function downloadPdf(invoice) {
    window.open(
        `/admin/client-portal/api/projects/${props.id}/billing/invoices/${invoice.id}/pdf`,
        '_blank'
    )
}

function openStripe(invoice) {
    if (invoice.hosted_invoice_url) {
        window.open(invoice.hosted_invoice_url, '_blank', 'noopener')
    }
}

async function syncInvoiceToStripe(invoice) {
    busy.value = true

    try {
        const response = await api.post(
            `/projects/${props.id}/billing/invoices/${invoice.id}/sync-stripe`
        )

        openedInvoice.value = response.data.data || openedInvoice.value
        notifySuccess(`Invoice ${invoice.invoice_number} is now in Stripe.`)
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}

function openLifecycle(action) {
    lifecycleAction.value = action
    showLifecycleDialog.value = true
}

async function confirmLifecycle(payload) {
    if (!subscription.value) {
        return
    }

    busy.value = true

    try {
        const endpoint = lifecycleAction.value === 'cancel'
            ? 'cancel'
            : 'pause'

        await api.post(
            `/projects/${props.id}/billing/subscription/${subscription.value.id}/${endpoint}`,
            payload
        )

        showLifecycleDialog.value = false
        notifySuccess(lifecycleAction.value === 'cancel' ? 'Subscription cancellation updated.' : 'Billing collection updated.')
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}

onMounted(load)
</script>

<template>
    <div class="space-y-8">
        <section class="border border-accent bg-light p-5">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Billing overview</div>
                    <h1 class="mt-1 text-2xl font-bold text-dark">{{ project?.name || 'Project billing' }}</h1>
                    <p class="mt-1 text-sm text-dark/60">{{ project?.company?.name || '' }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="border border-accent p-4">
                    <div class="text-xs uppercase tracking-wide text-dark/60">Outstanding</div>
                    <div class="mt-2 text-xl font-bold text-dark">{{ money(metrics.outstanding, metrics.currency) }}</div>
                </div>
                <div class="border border-accent p-4">
                    <div class="text-xs uppercase tracking-wide text-dark/60">Revenue</div>
                    <div class="mt-2 text-xl font-bold text-dark">{{ money(metrics.total_revenue, metrics.currency) }}</div>
                </div>
                <div class="border border-accent p-4">
                    <div class="text-xs uppercase tracking-wide text-dark/60">Recurring</div>
                    <div class="mt-2 text-xl font-bold text-dark">{{ recurringTotalsLabel() }}</div>
                </div>
                <div class="border border-accent p-4">
                    <div class="text-xs uppercase tracking-wide text-dark/60">Next billing</div>
                    <div class="mt-2 text-xl font-bold text-dark">{{ formatDate(metrics.next_billing_date) }}</div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="border border-accent bg-light p-5">
                <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Payments</div>
                <h2 class="mt-2 text-xl font-bold text-dark">One-time charge</h2>
                <p class="mt-2 text-sm text-dark/60">Create a payment invoice from one-time project items, preview the final PDF, then send or collect payment.</p>
                <div class="mt-5">
                    <Button text="+ Create Payment" variant="primary" :disabled="busy" @click="showPaymentWorkflow = true" />
                </div>
            </div>

            <div class="border border-accent bg-light p-5">
                <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Subscription</div>
                <h2 class="mt-2 text-xl font-bold text-dark">Recurring relationship</h2>
                <p class="mt-2 text-sm text-dark/60">Manage recurring services by start date, end date, current period, and next billing date.</p>
                <div class="mt-5">
                    <Button
                        text="+ Create Subscription"
                        variant="secondary"
                        :disabled="busy || !recurringItemsAvailableForSubscription"
                        @click="showSubscriptionCreateWorkflow = true"
                    />
                </div>
            </div>
        </section>

        <section
            v-if="subscription"
            class="border border-accent bg-light p-5"
        >
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <div class="mb-3 flex flex-wrap items-center gap-3">
                        <h2 class="text-xl font-bold text-dark">Subscription</h2>
                        <Tag :text="subscriptionStatusLabel()" />
                    </div>

                    <div class="text-2xl font-bold text-dark">
                        {{ recurringTotalsLabel(subscription.recurring_totals) }}
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <div
                                v-for="item in (subscription.items || []).slice(0, 4)"
                                :key="item.id"
                                class="flex items-center justify-between gap-4 border-b border-accent/40 pb-3 last:border-b-0"
                            >
                                <span class="font-bold text-dark">{{ item.name }}</span>
                                <span class="text-sm text-dark/70">{{ money(item.unit_amount, item.currency) }} / {{ intervalLabel(item.interval) }}</span>
                            </div>
                        </div>

                        <div class="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <span class="block text-xs uppercase tracking-wide text-dark/50">Current period</span>
                                <span class="font-bold text-dark">{{ formatDate(subscription.current_period_start) }} -> {{ formatDate(subscription.current_period_end) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs uppercase tracking-wide text-dark/50">Next billing</span>
                                <span class="font-bold text-dark">{{ formatDate(subscription.next_billing_at || subscription.current_period_end) }}</span>
                            </div>
                            <div>
                                <span class="block text-xs uppercase tracking-wide text-dark/50">Payment method</span>
                                <span class="font-bold text-dark">{{ subscription.payment_method_label || 'Not saved yet' }}</span>
                            </div>
                            <div>
                                <span class="block text-xs uppercase tracking-wide text-dark/50">Billing email</span>
                                <span class="font-bold text-dark">{{ subscription.billing_email || '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-start lg:justify-end">
                    <Button text="Manage subscription" variant="primary" :disabled="busy" @click="showSubscriptionManagement = true" />
                </div>
            </div>
        </section>

        <AdminDataTable
            title="Recurring services"
            :columns="recurringColumns"
            :rows="recurringItems"
            :loading="loading"
            add-label=" "
            empty-title="No recurring services yet."
            empty-text="Add recurring services, then create a subscription."
            @add="openItemModal('recurring')"
            @row-click="openItemEditor"
        >
            <template #cell-unit_amount="{ row }">
                {{ money(row.unit_amount, row.currency) }} / {{ intervalLabel(row.interval) }}
            </template>

            <template #cell-starts_at="{ row }">
                {{ formatDate(row.starts_at) }}
            </template>

            <template #cell-ends_at="{ row }">
                {{ row.ends_at ? formatDate(row.ends_at) : 'No end date' }}
            </template>

            <template #cell-status="{ row }">
                <Tag :text="itemStatus(row)" />
            </template>
        </AdminDataTable>

        <AdminDataTable
            title="One-time items"
            :columns="oneTimeColumns"
            :rows="oneTimeItems"
            :loading="loading"
            add-label=" "
            empty-title="No one-time items yet."
            empty-text="Add one-time items before creating a payment."
            @add="openItemModal('one_time')"
            @row-click="openItemEditor"
        >
            <template #cell-unit_amount="{ row }">
                {{ money(row.unit_amount, row.currency) }}
            </template>

            <template #cell-status="{ row }">
                <Tag :text="humanStatus(row.status)" />
            </template>
        </AdminDataTable>

        <AdminDataTable
            title="Invoices"
            :columns="invoiceColumns"
            :rows="invoices"
            :loading="loading"
            add-label=" "
            empty-title="No invoices yet."
            empty-text="Payments and subscriptions both create invoices here."
            @row-click="openInvoiceDetail"
            @add="showPaymentWorkflow = true"
        >
            <template #cell-issue_date="{ row }">
                {{ formatDate(row.issue_date) }}
            </template>

            <template #cell-due_date="{ row }">
                {{ formatDate(row.due_date) }}
            </template>

            <template #cell-total="{ row }">
                {{ money(row.total, row.currency) }}
            </template>

            <template #cell-status="{ row }">
                <Tag :text="humanStatus(row.status)" />
            </template>

            <template #cell-payment_status="{ row }">
                <Tag :text="humanStatus(row.payment_status)" />
            </template>

            <template #cell-billing_source="{ row }">
                {{ invoiceType(row) }}
            </template>
        </AdminDataTable>

        <CreatePayment
            :open="showPaymentWorkflow"
            :project-id="id"
            :items="oneTimeItems"
            :currency="metrics.currency"
            :busy="busy"
            @update:busy="busy = $event"
            @close="showPaymentWorkflow = false"
            @error="notifyError"
            @created="finishWorkflow"
        />

        <CreateSubscription
            :open="showSubscriptionCreateWorkflow"
            :project-id="id"
            :services="recurringItems"
            :currency="metrics.currency"
            :busy="busy"
            @update:busy="busy = $event"
            @close="showSubscriptionCreateWorkflow = false"
            @error="notifyError"
            @created="finishWorkflow"
        />

        <ProjectSubscriptionManagement
            :open="showSubscriptionManagement"
            :subscription="subscription"
            :metrics="metrics"
            :busy="busy"
            @close="showSubscriptionManagement = false"
            @add-service="openSubscriptionServiceEditor(null)"
            @edit-service="openSubscriptionServiceEditor"
            @end-service="(item) => openSubscriptionServiceEditor(item, 'end')"
            @lifecycle="openLifecycle"
            @debit-note="openDebitNoteModal"
            @invoice="(invoice) => { openInvoiceDetail(invoice); showSubscriptionManagement = false }"
        />

        <SubscriptionServiceEditor
            :open="showServiceEditor"
            :project-id="id"
            :item="editingSubscriptionService"
            :mode="serviceEditorMode"
            :currency="metrics.currency"
            :busy="busy"
            @update:busy="busy = $event"
            @close="showServiceEditor = false"
            @error="notifyError"
            @saved="finishWorkflow"
        />

        <SubscriptionLifecycleDialog
            :open="showLifecycleDialog"
            :action="lifecycleAction"
            :subscription="subscription"
            :busy="busy"
            @close="showLifecycleDialog = false"
            @confirm="confirmLifecycle"
        />

        <Modal
            :open="showItemModal"
            :title="editingItem ? 'Edit billing item' : itemForm.billing_type === 'recurring' ? 'Add recurring service' : 'Add one-time item'"
            :subtitle="itemForm.billing_type === 'recurring' ? 'Set service dates and billing interval.' : 'Add one-time money for a future Payment.'"
            @close="showItemModal = false"
        >
            <div class="space-y-5">
                <FormField v-model="itemForm.name" label="Name" autocomplete="off" />

                <div class="grid grid-cols-2 gap-4">
                    <FormField v-model="itemForm.unit_amount" label="Price" type="number" step="0.01" min="0" />
                    <FormField v-model="itemForm.quantity" label="Quantity" type="number" min="1" step="1" />
                </div>

                <div
                    v-if="itemForm.billing_type === 'recurring'"
                    class="grid grid-cols-2 gap-4"
                >
                    <FormField
                        v-model="itemForm.interval"
                        label="Interval"
                        type="select"
                        :disabled="Boolean(editingItem)"
                        :options="[
                            { value: 'month', label: 'Monthly' },
                            { value: 'year', label: 'Yearly' },
                            { value: 'week', label: 'Weekly' }
                        ]"
                    />
                    <FormField v-model="itemForm.interval_count" label="Every" type="number" min="1" step="1" :disabled="Boolean(editingItem)" />
                    <FormField v-model="itemForm.starts_at" label="Starts" type="date" />
                    <FormField v-model="itemForm.ends_at" label="Ends" type="date" />
                </div>

                <div class="flex justify-between gap-4 border-t border-accent pt-5">
                    <Button
                        v-if="editingItem"
                        text="Remove item"
                        variant="danger"
                        :disabled="busy"
                        @click="confirmRemoveItem(editingItem)"
                    />
                    <Button
                        :text="editingItem ? 'Save' : 'Add'"
                        variant="primary"
                        :loading="busy"
                        align="right"
                        @click="saveItem"
                    />
                </div>
            </div>
        </Modal>

        <Modal
            :open="showInvoiceDetailModal"
            :title="openedInvoice ? `Invoice ${openedInvoice.invoice_number}` : 'Invoice'"
            :subtitle="openedInvoice ? `${money(openedInvoice.total, openedInvoice.currency)} - ${humanStatus(openedInvoice.status)}` : ''"
            @close="showInvoiceDetailModal = false"
        >
            <div v-if="openedInvoice" class="space-y-5">
                <div class="border border-accent">
                    <div
                        v-for="detail in [
                            { label: 'Type', value: invoiceType(openedInvoice) },
                            { label: 'Issued', value: formatDate(openedInvoice.issue_date) },
                            { label: 'Due', value: formatDate(openedInvoice.due_date) },
                            { label: 'Amount due', value: money(openedInvoice.amount_due, openedInvoice.currency) },
                            { label: 'Payment', value: humanStatus(openedInvoice.payment_status) },
                            { label: 'Sent to', value: openedInvoice.customer_email || '-' },
                            { label: 'In Stripe', value: openedInvoice.stripe_invoice_id ? 'Yes' : 'Not synced' }
                        ]"
                        :key="detail.label"
                        class="flex items-center justify-between gap-4 border-b border-accent/40 p-3 last:border-b-0"
                    >
                        <span class="text-xs uppercase tracking-wide text-dark/60">{{ detail.label }}</span>
                        <span class="text-sm font-bold text-dark">{{ detail.value }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button text="Download PDF" variant="secondary" @click="downloadPdf(openedInvoice)" />
                    <Button text="Send to client" variant="secondary" :disabled="busy" @click="openSendModal(openedInvoice)" />
                    <Button
                        v-if="!['paid', 'void', 'uncollectible'].includes(openedInvoice.status)"
                        text="Mark as paid"
                        variant="secondary"
                        :disabled="busy"
                        @click="openPaymentModal(openedInvoice)"
                    />
                    <Button v-if="openedInvoice.hosted_invoice_url" text="Open payment page" variant="ghost" @click="openStripe(openedInvoice)" />
                    <Button
                        v-if="openedInvoice.status === 'paid' && openedInvoice.stripe_payment_intent_id"
                        text="Refund"
                        variant="ghost"
                        :disabled="busy"
                        @click="openRefundModal(openedInvoice)"
                    />
                    <Button
                        v-if="!openedInvoice.stripe_invoice_id && openedInvoice.status !== 'paid'"
                        text="Push to Stripe"
                        variant="ghost"
                        :disabled="busy"
                        @click="syncInvoiceToStripe(openedInvoice)"
                    />
                </div>
            </div>
        </Modal>

        <Modal
            :open="showSendModal"
            title="Send invoice"
            :subtitle="sendingInvoice ? `Invoice ${sendingInvoice.invoice_number} with the StudioKristian PDF attached.` : ''"
            @close="showSendModal = false"
        >
            <div class="space-y-4">
                <FormField v-model="selectedRecipients" label="Recipients" type="select" multiple :options="recipientOptions" />
            </div>

            <template #footer>
                <div class="flex justify-end gap-3 border-t border-accent p-6">
                    <Button text="Cancel" variant="ghost" @click="showSendModal = false" />
                    <Button text="Send invoice" variant="primary" :loading="busy" :disabled="!selectedRecipients.length" @click="sendInvoice" />
                </div>
            </template>
        </Modal>

        <Modal
            :open="showPaymentModal"
            title="Mark invoice as paid"
            :subtitle="payingInvoice ? `Invoice ${payingInvoice.invoice_number} - ${money(payingInvoice.total, payingInvoice.currency)}` : ''"
            @close="showPaymentModal = false"
        >
            <div class="space-y-4">
                <FormField v-model="paymentForm.paid_at" label="Payment date" type="date" />
                <FormField
                    v-model="paymentForm.payment_method"
                    label="Payment method"
                    type="select"
                    :options="[
                        { value: 'bank_transfer', label: 'Bank transfer' },
                        { value: 'stripe_card', label: 'Card' },
                        { value: 'stripe_hosted', label: 'Stripe payment page' }
                    ]"
                />
            </div>

            <template #footer>
                <div class="flex justify-end gap-3 border-t border-accent p-6">
                    <Button text="Cancel" variant="ghost" @click="showPaymentModal = false" />
                    <Button text="Mark as paid" variant="primary" :loading="busy" @click="recordPayment" />
                </div>
            </template>
        </Modal>

        <Modal
            :open="showRefundModal"
            title="Refund payment"
            subtitle="This creates a real Stripe refund. The original invoice remains unchanged."
            @close="showRefundModal = false"
        >
            <div class="space-y-4">
                <FormField v-model="refundForm.amount" label="Refund amount" type="number" min="0.01" step="0.01" />
                <FormField v-model="refundForm.reason" label="Reason" type="textarea" />
            </div>

            <template #footer>
                <div class="flex justify-end gap-3 border-t border-accent p-6">
                    <Button text="Cancel" variant="ghost" @click="showRefundModal = false" />
                    <Button text="Create refund" variant="primary" :loading="busy" @click="refundInvoice" />
                </div>
            </template>
        </Modal>

        <Modal
            :open="showDebitNoteModal"
            title="Create debit note"
            subtitle="A debit note is issued as a new invoice; existing invoices are never rewritten."
            @close="showDebitNoteModal = false"
        >
            <div class="space-y-4">
                <FormField v-model="debitNoteForm.description" label="Description" />
                <FormField v-model="debitNoteForm.amount" label="Amount" type="number" min="0.01" step="0.01" />
            </div>

            <template #footer>
                <div class="flex justify-end gap-3 border-t border-accent p-6">
                    <Button text="Cancel" variant="ghost" @click="showDebitNoteModal = false" />
                    <Button text="Create debit note" variant="primary" :loading="busy" @click="createDebitNote" />
                </div>
            </template>
        </Modal>

        <AdminConfirmDialog
            :open="confirmState.open"
            :title="confirmState.title"
            :text="confirmState.text"
            :confirm-label="confirmState.confirmLabel"
            :busy="busy"
            @confirm="confirmState.action && confirmState.action()"
            @close="confirmState.open = false"
        />

        <Toast v-model="showErrorToast" heading="Something went wrong" :text="error" :duration="5000" />
        <Toast v-model="showSuccessToast" heading="Done" :text="successMessage" :duration="4000" />
    </div>
</template>
