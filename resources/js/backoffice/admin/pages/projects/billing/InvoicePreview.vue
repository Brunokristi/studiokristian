<script setup>
import {
    computed,
    onBeforeUnmount,
    ref,
    watch
} from 'vue'

import api from '../../../composables/useAdminApi'
import Button from '@shared/components/Button.vue'

const props = defineProps({
    projectId: {
        type: [String, Number],
        required: true
    },
    itemIds: {
        type: Array,
        default: () => []
    },
    issueDate: {
        type: String,
        default: ''
    },
    deliveryDate: {
        type: String,
        default: ''
    },
    dueDate: {
        type: String,
        default: ''
    },
    notes: {
        type: String,
        default: ''
    }
})

const loading = ref(false)
const error = ref('')
const previewUrl = ref('')
let debounceTimer = null
let requestSerial = 0

const canPreview = computed(() => props.itemIds.length > 0)

function clearPreview() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
        previewUrl.value = ''
    }
}

function previewParams() {
    const params = new URLSearchParams()

    props.itemIds.forEach((itemId) => {
        params.append('billing_item_ids[]', itemId)
    })

    if (props.issueDate) {
        params.append('issue_date', props.issueDate)
    }

    if (props.deliveryDate) {
        params.append('delivery_date', props.deliveryDate)
    }

    if (props.dueDate) {
        params.append('due_date', props.dueDate)
    }

    if (props.notes) {
        params.append('notes', props.notes)
    }

    return params
}

async function loadPreview() {
    if (!canPreview.value) {
        clearPreview()
        error.value = ''
        loading.value = false

        return
    }

    const serial = ++requestSerial
    loading.value = true
    error.value = ''

    try {
        const response = await api.get(
            `/projects/${props.projectId}/billing/invoices/preview`,
            {
                params: previewParams(),
                responseType: 'blob',
                headers: {
                    Accept: 'application/pdf'
                }
            }
        )

        if (serial !== requestSerial) {
            return
        }

        clearPreview()
        previewUrl.value = URL.createObjectURL(
            new Blob([response.data], { type: 'application/pdf' })
        )
    } catch {
        if (serial === requestSerial) {
            clearPreview()
            error.value = 'Unable to generate preview.'
        }
    } finally {
        if (serial === requestSerial) {
            loading.value = false
        }
    }
}

function schedulePreview() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(loadPreview, 300)
}

watch(
    () => [
        props.itemIds.join(','),
        props.issueDate,
        props.deliveryDate,
        props.dueDate,
        props.notes
    ],
    schedulePreview,
    { immediate: true }
)

onBeforeUnmount(() => {
    clearTimeout(debounceTimer)
    clearPreview()
})
</script>

<template>
    <section class="flex min-h-[520px] flex-col border border-accent bg-light">
        <div class="flex items-center justify-between border-b border-accent p-4">
            <div>
                <div class="text-xs font-bold uppercase tracking-wide text-dark/60">Invoice preview</div>
                <div class="mt-1 text-sm text-dark/70">Generated from the final invoice PDF renderer.</div>
            </div>

            <Button
                text="Refresh"
                variant="ghost"
                :disabled="!canPreview || loading"
                @click="loadPreview"
            />
        </div>

        <div class="relative flex flex-1 items-center justify-center bg-white/60 p-4">
            <div
                v-if="!canPreview"
                class="max-w-xs text-center text-sm text-dark/60"
            >
                Select an item to preview the invoice.
            </div>

            <div
                v-else-if="error"
                class="space-y-3 text-center text-sm text-dark/70"
            >
                <div>{{ error }}</div>
                <Button text="Retry" variant="secondary" @click="loadPreview" />
            </div>

            <div
                v-else-if="loading && !previewUrl"
                class="text-sm text-dark/60"
            >
                Generating preview...
            </div>

            <iframe
                v-if="previewUrl"
                :src="previewUrl"
                title="Invoice PDF preview"
                class="h-[68vh] min-h-[480px] w-full border border-accent bg-white"
            />

            <div
                v-if="loading && previewUrl"
                class="absolute right-6 top-6 border border-accent bg-light px-3 py-2 text-xs font-bold uppercase tracking-wide text-dark"
            >
                Updating preview...
            </div>
        </div>
    </section>
</template>
