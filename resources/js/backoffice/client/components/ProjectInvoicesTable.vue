<script setup>
import { computed } from 'vue'

import AdminDataTable from '@shared/components/DataTable.vue'
import Tag from '@shared/components/Tag.vue'

const props = defineProps({
    invoices: {
        type: Array,
        default: () => []
    },
    locale: {
        type: String,
        required: true
    }
})

const copy = computed(() => props.locale === 'sk'
    ? {
        heading: 'Faktúry projektu',
        number: 'Faktúra',
        issued: 'Vystavená',
        due: 'Splatnosť',
        amount: 'Suma',
        status: 'Stav',
        empty: 'Tento projekt zatiaľ nemá žiadne faktúry.',
        open: 'Otvoriť PDF',
        pay: 'Zaplatiť',
        paid: 'Zaplatená',
        unpaid: 'Neuhradená',
        overdue: 'Po splatnosti',
        void: 'Stornovaná',
        uncollectible: 'Nevymožiteľná'
    }
    : {
        heading: 'Project invoices',
        number: 'Invoice',
        issued: 'Issued',
        due: 'Due',
        amount: 'Amount',
        status: 'Status',
        empty: 'This project has no invoices yet.',
        open: 'Open PDF',
        pay: 'Pay',
        paid: 'Paid',
        unpaid: 'Unpaid',
        overdue: 'Overdue',
        void: 'Void',
        uncollectible: 'Uncollectible'
    })

const columns = computed(() => [
    { key: 'number', label: copy.value.number, sortable: true },
    { key: 'issue_date', label: copy.value.issued, sortable: true },
    { key: 'due_date', label: copy.value.due },
    { key: 'total', label: copy.value.amount, sortable: true },
    { key: 'state', label: copy.value.status },
    { key: 'actions', label: '' }
])

function money(amount, currency) {
    return new Intl.NumberFormat(props.locale === 'sk' ? 'sk-SK' : 'en-IE', {
        style: 'currency',
        currency: currency || 'EUR'
    }).format(Number(amount || 0) / 100)
}

function formatDate(value) {
    return value
        ? new Date(value).toLocaleDateString(props.locale === 'sk' ? 'sk-SK' : 'en-IE')
        : '—'
}

function stateLabel(invoice) {
    if (invoice.status === 'paid') return copy.value.paid
    if (invoice.status === 'void') return copy.value.void
    if (invoice.status === 'uncollectible') return copy.value.uncollectible

    return invoice.is_overdue ? copy.value.overdue : copy.value.unpaid
}
</script>

<template>
    <section>
        <AdminDataTable
            :title="copy.heading"
            :columns="columns"
            :rows="invoices"
            :empty-title="copy.empty"
        >
            <template #cell-number="{ value }">
                <span class="p font-medium uppercase">{{ value }}</span>
            </template>

            <template #cell-issue_date="{ value }">
                <span class="p">{{ formatDate(value) }}</span>
            </template>

            <template #cell-due_date="{ value }">
                <span class="p">{{ formatDate(value) }}</span>
            </template>

            <template #cell-total="{ row }">
                <span class="p font-medium">{{ money(row.total, row.currency) }}</span>
            </template>

            <template #cell-state="{ row }">
                <Tag :text="stateLabel(row)" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex flex-wrap justify-end gap-2">
                    <a
                        :href="row.pdf_url"
                        class="border border-accent px-3 py-2 font-mono text-xs font-bold uppercase text-dark transition-colors hover:bg-accent hover:text-light"
                    >
                        {{ copy.open }}
                    </a>
                    <a
                        v-if="row.is_payable"
                        :href="row.pay_url"
                        class="border border-dark bg-dark px-3 py-2 font-mono text-xs font-bold uppercase text-light transition-colors hover:border-accent hover:bg-accent"
                    >
                        {{ copy.pay }}
                    </a>
                </div>
            </template>
        </AdminDataTable>
    </section>
</template>