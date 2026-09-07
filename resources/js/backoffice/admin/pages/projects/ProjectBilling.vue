<script setup>

import {
    computed,
    onMounted,
    reactive,
    ref
} from 'vue'

import {
    useRouter
} from 'vue-router'

import api, {
    errorMessage
} from '../../composables/useAdminApi'

import AdminDataTable from '@shared/components/DataTable.vue'
import AdminConfirmDialog from '../../../../shared/components/ConfirmDialog.vue'
import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Modal from '@shared/components/Modal.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'

import { useAdminPageHeader } from '../../composables/useAdminPageHeader'


const props = defineProps({
    id: {
        type: String,
        default: ''
    }
})


const router = useRouter()

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
const products = ref([])
const recipients = ref([])

const showItemModal = ref(false)
const showInvoiceModal = ref(false)
const showSendModal = ref(false)
const sendingInvoice = ref(null)
const selectedRecipients = ref([])
const confirmState = reactive({
    open: false,
    title: '',
    message: '',
    confirmText: 'Confirm',
    action: null
})

const itemForm = reactive({
    billing_product_id: '',
    name: '',
    description: '',
    unit_amount: '',
    quantity: 1,
    billing_type: 'one_time',
    interval: 'month',
    interval_count: 1,
    starts_at: '',
    ends_at: ''
})

const invoiceForm = reactive({
    billing_item_ids: [],
    payment_method: 'stripe_hosted',
    issue_date: '',
    delivery_date: '',
    due_date: '',
    notes: ''
})


useAdminPageHeader(computed(() => ({
    title: project.value?.name ? `Billing — ${project.value.name}` : 'Billing',
    subtitle: project.value?.company?.name || ''
})))


function money(amount, currency = 'EUR') {
    const value = Number(amount || 0) / 100

    return new Intl.NumberFormat('sk-SK', {
        style: 'currency',
        currency
    }).format(value)
}


function formatDate(value) {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleDateString('sk-SK')
}


const oneTimeItems = computed(() =>
    billingItems.value.filter((item) => item.billing_type === 'one_time'))

const recurringItems = computed(() =>
    billingItems.value.filter((item) => item.billing_type === 'recurring'))

const invoiceableItems = computed(() =>
    billingItems.value.filter((item) =>
        item.billing_type === 'one_time' && item.status !== 'invoiced'))

const productOptions = computed(() => [
    { value: '', label: 'Custom item (no product)' },
    ...products.value.map((product) => ({
        value: String(product.id),
        label: `${product.name} — ${money(product.unit_amount, product.currency)}`
    }))
])

const recipientOptions = computed(() =>
    recipients.value.map((recipient) => ({
        value: recipient.email,
        label: recipient.name
            ? `${recipient.name} — ${recipient.email}`
            : recipient.email
    })))

const canStartSubscription = computed(() =>
    recurringItems.value.some((item) => !item.project_subscription_id)
    && !['active', 'past_due'].includes(subscription.value?.status))


const invoiceColumns = [
    { key: 'invoice_number', label: 'Number' },
    { key: 'issue_date', label: 'Issued' },
    { key: 'due_date', label: 'Due' },
    { key: 'total', label: 'Amount' },
    { key: 'status', label: 'Status' },
    { key: 'payment_status', label: 'Payment' },
    { key: 'actions', label: '' }
]

const recurringColumns = [
    { key: 'name', label: 'Service' },
    { key: 'unit_amount', label: 'Price' },
    { key: 'quantity', label: 'Qty' },
    { key: 'status', label: 'Status' }
]

const oneTimeColumns = [
    { key: 'name', label: 'Item' },
    { key: 'unit_amount', label: 'Price' },
    { key: 'quantity', label: 'Qty' },
    { key: 'status', label: 'Status' }
]


function statusVariant(status) {
    return {
        paid: 'success',
        active: 'success',
        open: 'warning',
        pending: 'warning',
        past_due: 'error',
        failed: 'error',
        void: 'muted',
        canceled: 'muted',
        uncollectible: 'error'
    }[status] || 'muted'
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
        const [billing, catalog] = await Promise.all([
            api.get(`/projects/${props.id}/billing`),
            api.get('/billing-products', { params: { active_only: 1 } })
        ])

        project.value = billing.data.project
        billingItems.value = billing.data.billing_items || []
        subscription.value = billing.data.subscription
        invoices.value = billing.data.invoices || []
        metrics.value = billing.data.metrics || {}
        recipients.value = billing.data.recipients || []
        products.value = catalog.data.data || []
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        loading.value = false
    }
}


function applyProduct() {
    const product = products.value.find(
        (candidate) => String(candidate.id) === String(itemForm.billing_product_id))

    if (!product) {
        return
    }

    itemForm.name = product.name
    itemForm.description = product.description || ''
    itemForm.unit_amount = (Number(product.unit_amount) / 100).toFixed(2)
    itemForm.billing_type = product.billing_type
    itemForm.interval = product.interval || 'month'
    itemForm.interval_count = product.interval_count || 1
}


function openItemModal() {
    Object.assign(itemForm, {
        billing_product_id: '',
        name: '',
        description: '',
        unit_amount: '',
        quantity: 1,
        billing_type: 'one_time',
        interval: 'month',
        interval_count: 1,
        starts_at: '',
        ends_at: ''
    })

    showItemModal.value = true
}


async function saveItem() {
    busy.value = true

    try {
        await api.post(`/projects/${props.id}/billing/items`, {
            billing_product_id: itemForm.billing_product_id || null,
            name: itemForm.name,
            description: itemForm.description || null,
            unit_amount: Math.round(Number(itemForm.unit_amount || 0) * 100),
            quantity: Number(itemForm.quantity || 1),
            billing_type: itemForm.billing_type,
            interval: itemForm.billing_type === 'recurring' ? itemForm.interval : null,
            interval_count: itemForm.billing_type === 'recurring' ? Number(itemForm.interval_count || 1) : 1,
            starts_at: itemForm.starts_at || null,
            ends_at: itemForm.ends_at || null
        })

        showItemModal.value = false
        notifySuccess('Billing item added.')
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}


function openInvoiceModal() {
    invoiceForm.billing_item_ids = invoiceableItems.value.map((item) => item.id)
    invoiceForm.payment_method = 'stripe_hosted'
    invoiceForm.issue_date = ''
    invoiceForm.delivery_date = ''
    invoiceForm.due_date = ''
    invoiceForm.notes = ''

    showInvoiceModal.value = true
}


function toggleInvoiceItem(itemId) {
    const index = invoiceForm.billing_item_ids.indexOf(itemId)

    if (index === -1) {
        invoiceForm.billing_item_ids.push(itemId)
    } else {
        invoiceForm.billing_item_ids.splice(index, 1)
    }
}


const invoiceTotal = computed(() =>
    billingItems.value
        .filter((item) => invoiceForm.billing_item_ids.includes(item.id))
        .reduce((total, item) => total + (item.unit_amount * item.quantity), 0))


async function createInvoice() {
    if (!invoiceForm.billing_item_ids.length) {
        notifyError('Select at least one item.')

        return
    }

    busy.value = true

    try {
        await api.post(`/projects/${props.id}/billing/invoices`, {
            billing_item_ids: invoiceForm.billing_item_ids,
            payment_method: invoiceForm.payment_method,
            issue_date: invoiceForm.issue_date || null,
            delivery_date: invoiceForm.delivery_date || null,
            due_date: invoiceForm.due_date || null,
            notes: invoiceForm.notes || null
        })

        showInvoiceModal.value = false
        notifySuccess('Invoice created.')
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}


function openSendModal(invoice) {
    if (!recipients.value.length) {
        notifyError('This client has no contact with an email address. Add one on the client first.')

        return
    }

    sendingInvoice.value = invoice

    const preselected = invoice.customer_email
        && recipients.value.some((recipient) => recipient.email === invoice.customer_email)
        ? invoice.customer_email
        : recipients.value[0].email

    selectedRecipients.value = [preselected]
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
        notifySuccess(
            `Invoice ${sendingInvoice.value.invoice_number} sent to ${sentTo.join(', ')}.`)
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


async function startSubscription() {
    busy.value = true

    try {
        await api.post(`/projects/${props.id}/billing/subscription`)

        notifySuccess('Recurring billing started. The first invoice will be emailed for payment.')
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}


function confirmCancelSubscription() {
    confirmState.open = true
    confirmState.title = 'Cancel recurring billing?'
    confirmState.message =
        'Recurring billing stays active until the end of the current period, then stops in Stripe.'
    confirmState.confirmText = 'Cancel billing'
    confirmState.action = cancelSubscription
}


async function cancelSubscription() {
    confirmState.open = false
    busy.value = true

    try {
        await api.post(
            `/projects/${props.id}/billing/subscription/${subscription.value.id}/cancel`,
            { at_period_end: true }
        )

        notifySuccess('Recurring billing will end at the period end.')
        await load()
    } catch (exception) {
        notifyError(errorMessage(exception))
    } finally {
        busy.value = false
    }
}


async function togglePause() {
    busy.value = true

    try {
        await api.post(
            `/projects/${props.id}/billing/subscription/${subscription.value.id}/pause`,
            { paused: subscription.value.status !== 'paused' }
        )

        notifySuccess('Subscription updated.')
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

        <!-- Overview -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="card in [
                    { label: 'Total revenue', value: money(metrics.total_revenue, metrics.currency) },
                    { label: 'Outstanding', value: money(metrics.outstanding, metrics.currency) },
                    { label: 'Recurring / month', value: money(metrics.recurring_monthly_total, metrics.currency) },
                    { label: 'Next billing', value: formatDate(metrics.next_billing_date) }
                ]"
                :key="card.label"
                class="border border-accent bg-light p-4"
            >
                <div class="text-xs uppercase tracking-wide text-dark/60">
                    {{ card.label }}
                </div>

                <div class="mt-2 text-xl font-bold text-dark">
                    {{ card.value }}
                </div>
            </div>
        </section>


        <!-- Actions -->
        <section class="flex flex-wrap items-center gap-3">
            <Button
                text="Add billing item"
                variant="primary"
                :disabled="busy"
                @click="openItemModal"
            />

            <Button
                text="Create invoice"
                variant="secondary"
                :disabled="busy || !invoiceableItems.length"
                @click="openInvoiceModal"
            />

            <Button
                v-if="canStartSubscription"
                text="Start recurring billing"
                variant="secondary"
                :disabled="busy"
                @click="startSubscription"
            />

            <Button
                text="Back to project"
                variant="ghost"
                @click="router.push({ name: 'projects.show', params: { id: props.id } })"
            />
        </section>


        <!-- Recurring subscription -->
        <section
            v-if="subscription"
            class="border border-accent bg-light p-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-bold uppercase tracking-wide text-dark">
                        Stripe subscription
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-dark/70">
                        <Tag
                            :text="subscription.status"
                            :variant="statusVariant(subscription.status)"
                        />

                        <span>
                            Next billing: {{ formatDate(subscription.current_period_end) }}
                        </span>

                        <span v-if="subscription.cancel_at_period_end">
                            Cancels at period end
                        </span>

                        <span>
                            {{ subscription.stripe_default_payment_method_id
                                ? 'Card saved — future invoices charge automatically'
                                : 'Awaiting first payment — card is saved when the customer pays' }}
                        </span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button
                        :text="subscription.status === 'paused' ? 'Resume' : 'Pause'"
                        variant="secondary"
                        :disabled="busy || subscription.status === 'canceled'"
                        @click="togglePause"
                    />

                    <Button
                        text="Cancel"
                        variant="danger"
                        :disabled="busy || subscription.status === 'canceled' || subscription.cancel_at_period_end"
                        @click="confirmCancelSubscription"
                    />
                </div>
            </div>
        </section>


        <!-- Recurring services -->
        <AdminDataTable
            title="Recurring services"
            :columns="recurringColumns"
            :rows="recurringItems"
            :loading="loading"
            empty-title="No recurring services yet."
            empty-text="Add a recurring billing item to start monthly billing."
        >
            <template #cell-unit_amount="{ row }">
                {{ money(row.unit_amount, row.currency) }} / {{ row.interval }}
            </template>

            <template #cell-status="{ row }">
                <Tag
                    :text="row.status"
                    :variant="statusVariant(row.status)"
                />
            </template>
        </AdminDataTable>


        <!-- One-time items -->
        <AdminDataTable
            title="One-time items"
            :columns="oneTimeColumns"
            :rows="oneTimeItems"
            :loading="loading"
            empty-title="No one-time items yet."
            empty-text="Add a one-time item such as development or branding."
        >
            <template #cell-unit_amount="{ row }">
                {{ money(row.unit_amount, row.currency) }}
            </template>

            <template #cell-status="{ row }">
                <Tag
                    :text="row.status"
                    :variant="statusVariant(row.status)"
                />
            </template>
        </AdminDataTable>


        <!-- Invoices -->
        <AdminDataTable
            title="Invoices"
            :columns="invoiceColumns"
            :rows="invoices"
            :loading="loading"
            empty-title="No invoices yet."
            empty-text="Create an invoice from the project's billing items."
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
                <Tag
                    :text="row.status"
                    :variant="statusVariant(row.status)"
                />
            </template>

            <template #cell-payment_status="{ row }">
                <Tag
                    :text="row.payment_status"
                    :variant="statusVariant(row.payment_status)"
                />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex flex-wrap justify-end gap-2">
                    <Button
                        text="PDF"
                        variant="ghost"
                        @click.stop="downloadPdf(row)"
                    />

                    <Button
                        text="Send"
                        variant="ghost"
                        :disabled="busy"
                        @click.stop="openSendModal(row)"
                    />

                    <Button
                        v-if="row.hosted_invoice_url"
                        text="Stripe"
                        variant="ghost"
                        @click.stop="openStripe(row)"
                    />
                </div>
            </template>
        </AdminDataTable>


        <!-- Add billing item -->
        <Modal
            :open="showItemModal"
            title="Add billing item"
            subtitle="Reusable products can be adjusted to a project-specific price."
            @close="showItemModal = false"
        >
            <div class="space-y-4">
                <FormField
                    v-model="itemForm.billing_product_id"
                    label="Product"
                    type="select"
                    :options="productOptions"
                    @update:model-value="applyProduct"
                />

                <FormField
                    v-model="itemForm.name"
                    label="Name"
                />

                <FormField
                    v-model="itemForm.description"
                    label="Description"
                    type="textarea"
                />

                <div class="grid grid-cols-2 gap-4">
                    <FormField
                        v-model="itemForm.unit_amount"
                        label="Unit price"
                        type="number"
                        step="0.01"
                    />

                    <FormField
                        v-model="itemForm.quantity"
                        label="Quantity"
                        type="number"
                    />
                </div>

                <FormField
                    v-model="itemForm.billing_type"
                    label="Billing type"
                    type="select"
                    :options="[
                        { value: 'one_time', label: 'One-time' },
                        { value: 'recurring', label: 'Recurring' }
                    ]"
                />

                <div
                    v-if="itemForm.billing_type === 'recurring'"
                    class="grid grid-cols-2 gap-4"
                >
                    <FormField
                        v-model="itemForm.interval"
                        label="Interval"
                        type="select"
                        :options="[
                            { value: 'month', label: 'Monthly' },
                            { value: 'year', label: 'Yearly' },
                            { value: 'week', label: 'Weekly' }
                        ]"
                    />

                    <FormField
                        v-model="itemForm.interval_count"
                        label="Interval count"
                        type="number"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button
                        text="Cancel"
                        variant="ghost"
                        @click="showItemModal = false"
                    />

                    <Button
                        text="Add item"
                        variant="primary"
                        :loading="busy"
                        @click="saveItem"
                    />
                </div>
            </template>
        </Modal>


        <!-- Create invoice -->
        <Modal
            :open="showInvoiceModal"
            title="Create invoice"
            subtitle="A StudioKristian invoice number and PDF are generated, then mirrored into Stripe."
            @close="showInvoiceModal = false"
        >
            <div class="space-y-4">
                <div class="border border-accent">
                    <label
                        v-for="item in invoiceableItems"
                        :key="item.id"
                        class="flex cursor-pointer items-center justify-between gap-4 border-b border-accent/40 p-3 last:border-b-0"
                    >
                        <span class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                :checked="invoiceForm.billing_item_ids.includes(item.id)"
                                @change="toggleInvoiceItem(item.id)"
                            >

                            <span>
                                <span class="font-bold">{{ item.name }}</span>
                                <span class="block text-xs text-dark/60">
                                    {{ item.quantity }} × {{ money(item.unit_amount, item.currency) }}
                                </span>
                            </span>
                        </span>

                        <span class="font-bold">
                            {{ money(item.unit_amount * item.quantity, item.currency) }}
                        </span>
                    </label>
                </div>

                <div class="flex items-center justify-between border border-accent p-3">
                    <span class="text-sm uppercase tracking-wide text-dark/60">Total</span>
                    <span class="text-lg font-bold">{{ money(invoiceTotal, metrics.currency) }}</span>
                </div>

                <FormField
                    v-model="invoiceForm.payment_method"
                    label="Payment method"
                    type="select"
                    :options="[
                        { value: 'stripe_hosted', label: 'Stripe hosted payment page' },
                        { value: 'stripe_card', label: 'Charge saved card automatically' },
                        { value: 'bank_transfer', label: 'Bank transfer' }
                    ]"
                />

                <div class="grid grid-cols-3 gap-4">
                    <FormField
                        v-model="invoiceForm.issue_date"
                        label="Issue date"
                        type="date"
                    />

                    <FormField
                        v-model="invoiceForm.delivery_date"
                        label="Delivery date"
                        type="date"
                    />

                    <FormField
                        v-model="invoiceForm.due_date"
                        label="Due date"
                        type="date"
                    />
                </div>

                <FormField
                    v-model="invoiceForm.notes"
                    label="Notes"
                    type="textarea"
                />
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button
                        text="Cancel"
                        variant="ghost"
                        @click="showInvoiceModal = false"
                    />

                    <Button
                        text="Create invoice"
                        variant="primary"
                        :loading="busy"
                        @click="createInvoice"
                    />
                </div>
            </template>
        </Modal>


        <!-- Send invoice -->
        <Modal
            :open="showSendModal"
            title="Send invoice"
            :subtitle="sendingInvoice
                ? `Invoice ${sendingInvoice.invoice_number} with the StudioKristian PDF attached.`
                : ''"
            @close="showSendModal = false"
        >
            <div class="space-y-4">
                <FormField
                    v-model="selectedRecipients"
                    label="Recipients"
                    type="select"
                    multiple
                    :options="recipientOptions"
                    placeholder="Choose contacts"
                />

                <p
                    v-if="sendingInvoice?.sent_at"
                    class="text-xs text-dark/60"
                >
                    Last sent {{ formatDate(sendingInvoice.sent_at) }}.
                </p>
            </div>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button
                        text="Cancel"
                        variant="ghost"
                        @click="showSendModal = false"
                    />

                    <Button
                        :text="selectedRecipients.length > 1
                            ? `Send to ${selectedRecipients.length} recipients`
                            : 'Send invoice'"
                        variant="primary"
                        :loading="busy"
                        :disabled="!selectedRecipients.length"
                        @click="sendInvoice"
                    />
                </div>
            </template>
        </Modal>


        <AdminConfirmDialog
            :open="confirmState.open"
            :title="confirmState.title"
            :message="confirmState.message"
            :confirm-text="confirmState.confirmText"
            @confirm="confirmState.action && confirmState.action()"
            @cancel="confirmState.open = false"
        />


        <Toast
            :open="showErrorToast"
            :message="error"
            variant="error"
            @close="showErrorToast = false"
        />

        <Toast
            :open="showSuccessToast"
            :message="successMessage"
            variant="success"
            @close="showSuccessToast = false"
        />

    </div>
</template>
