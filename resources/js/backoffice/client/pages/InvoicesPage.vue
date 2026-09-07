<script setup>
import {
    computed,
    ref
} from 'vue'


import AdminDataTable from '@shared/components/DataTable.vue'
import Tag from '@shared/components/Tag.vue'
import { useClientPageHeader } from '../composables/useClientPageHeader'


const props = defineProps({
    data: {
        type: Object,
        required: true
    },

    locale: {
        type: String,
        required: true
    }
})


/*
|--------------------------------------------------------------------------
| Copy
|--------------------------------------------------------------------------
*/

const copy =
    computed(() => {
        if (
            props.locale === 'sk'
        ) {
            return {
                heading: 'Faktúry',
                tableHeading: 'Prehľad vašich faktúr',
                number: 'Faktúra',
                project: 'Projekt',
                issued: 'Vystavená',
                due: 'Splatnosť',
                amount: 'Suma',
                state: 'Stav',
                searchPlaceholder: 'Hľadať faktúry',
                noInvoices: 'Zatiaľ tu nie sú žiadne faktúry.',
                pay: 'Zaplatiť',
                pdf: 'PDF',
                paid: 'Zaplatená',
                unpaid: 'Neuhradená',
                overdue: 'Po splatnosti',
                void: 'Stornovaná',
                uncollectible: 'Nevymožiteľná',
                paidOn: 'Zaplatená'
            }
        }


        return {
            heading: 'Invoices',
            tableHeading: 'Overview of your invoices',
            number: 'Invoice',
            project: 'Project',
            issued: 'Issued',
            due: 'Due',
            amount: 'Amount',
            state: 'Status',
            searchPlaceholder: 'Search invoices',
            noInvoices: 'There are no invoices here yet.',
            pay: 'Pay invoice',
            pdf: 'PDF',
            paid: 'Paid',
            unpaid: 'Unpaid',
            overdue: 'Overdue',
            void: 'Void',
            uncollectible: 'Uncollectible',
            paidOn: 'Paid'
        }
    })


useClientPageHeader({
    title: computed(() => copy.value.heading)
})


const search =
    ref('')


const columns =
    computed(() => [
        { key: 'number', label: copy.value.number, sortable: true },
        { key: 'project_name', label: copy.value.project },
        { key: 'issue_date', label: copy.value.issued, sortable: true },
        { key: 'due_date', label: copy.value.due },
        { key: 'total', label: copy.value.amount, sortable: true },
        { key: 'state', label: copy.value.state },
        { key: 'actions', label: '' }
    ])


const rows =
    computed(() => {
        const query =
            search.value
                .trim()
                .toLowerCase()


        return (
            props.data.invoices ||
            []
        ).filter(
            invoice => {
                if (
                    !query
                ) {
                    return true
                }


                return [
                    String(invoice.number || ''),
                    String(invoice.project_name || '')
                ].some(
                    value =>
                        value
                            .toLowerCase()
                            .includes(query)
                )
            }
        )
    })


function money(
    amount,
    currency
) {
    return new Intl.NumberFormat(
        props.locale === 'sk'
            ? 'sk-SK'
            : 'en-IE',
        {
            style: 'currency',
            currency: currency || 'EUR'
        }
    ).format(
        Number(amount || 0) / 100
    )
}


function formatDate(
    value
) {
    if (
        !value
    ) {
        return '—'
    }


    return new Date(value)
        .toLocaleDateString(
            props.locale === 'sk'
                ? 'sk-SK'
                : 'en-IE'
        )
}


function stateLabel(
    invoice
) {
    if (
        invoice.status === 'paid'
    ) {
        return copy.value.paid
    }


    if (
        invoice.status === 'void'
    ) {
        return copy.value.void
    }


    if (
        invoice.status === 'uncollectible'
    ) {
        return copy.value.uncollectible
    }


    return invoice.is_overdue
        ? copy.value.overdue
        : copy.value.unpaid
}


function stateVariant(
    invoice
) {
    if (
        invoice.status === 'paid'
    ) {
        return 'success'
    }


    if (
        invoice.is_overdue ||
        invoice.payment_status === 'failed'
    ) {
        return 'error'
    }


    if (
        invoice.status === 'open'
    ) {
        return 'warning'
    }


    return 'muted'
}
</script>


<template>
    <section
        class="
            w-full
            space-y-12
            lg:space-y-14
        "
    >

        <AdminDataTable
            v-model:search="search"
            :title="copy.tableHeading"
            :columns="columns"
            :rows="rows"
            :search-placeholder="copy.searchPlaceholder"
            :empty-title="copy.noInvoices"
        >

            <!-- Invoice number -->

            <template #cell-number="{ row }">
                <p
                    class="
                        p
                        font-medium
                        uppercase
                    "
                >
                    {{ row.number }}
                </p>
            </template>


            <!-- Project -->

            <template #cell-project_name="{ value }">
                <span
                    class="
                        p
                        uppercase
                    "
                >
                    {{ value || '—' }}
                </span>
            </template>


            <!-- Issue date -->

            <template #cell-issue_date="{ value }">
                <span class="p">
                    {{ formatDate(value) }}
                </span>
            </template>


            <!-- Due date -->

            <template #cell-due_date="{ row }">
                <span class="p">
                    {{
                        row.status === 'paid'
                            ? `${copy.paidOn} ${formatDate(row.paid_at)}`
                            : formatDate(row.due_date)
                    }}
                </span>
            </template>


            <!-- Amount -->

            <template #cell-total="{ row }">
                <span
                    class="
                        p
                        font-medium
                    "
                >
                    {{ money(row.total, row.currency) }}
                </span>
            </template>


            <!-- Status -->

            <template #cell-state="{ row }">
                <Tag
                    :text="stateLabel(row)"
                    :variant="stateVariant(row)"
                />
            </template>


            <!-- Actions -->

            <template #cell-actions="{ row }">
                <div
                    class="
                        flex
                        flex-wrap
                        justify-end
                        gap-2
                    "
                >
                    <a
                        :href="row.pdf_url"
                        class="
                            border
                            border-accent
                            px-3
                            py-2
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                            text-dark
                            transition-colors
                            duration-200
                            hover:bg-accent
                            hover:text-light
                        "
                    >
                        {{ copy.pdf }}
                    </a>

                    <a
                        v-if="row.is_payable"
                        :href="row.pay_url"
                        class="
                            border
                            border-dark
                            bg-dark
                            px-3
                            py-2
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                            text-light
                            transition-colors
                            duration-200
                            hover:bg-accent
                            hover:border-accent
                        "
                    >
                        {{ copy.pay }}
                    </a>
                </div>
            </template>

        </AdminDataTable>

    </section>
</template>
