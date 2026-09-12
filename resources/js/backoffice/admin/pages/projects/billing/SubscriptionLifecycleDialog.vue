<script setup>
import {
    computed,
    reactive,
    watch
} from 'vue'

import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Modal from '@shared/components/Modal.vue'
import SubscriptionChangePreview from './SubscriptionChangePreview.vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false
    },
    action: {
        type: String,
        default: 'cancel'
    },
    subscription: {
        type: Object,
        default: null
    },
    busy: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits([
    'close',
    'confirm'
])

const form = reactive({
    mode: 'period_end',
    date: '',
    step: 'choose'
})

const isCancel = computed(() => props.action === 'cancel')
const isResume = computed(() => props.action === 'resume')

function formatDate(value) {
    if (!value) {
        return '-'
    }

    const isoDate = String(value).slice(0, 10)
    const parts = isoDate.match(/^(\d{4})-(\d{2})-(\d{2})$/)

    return parts ? `${parts[3]}.${parts[2]}.${parts[1]}` : '-'
}

const title = computed(() => {
    if (isResume.value) {
        return 'Resume billing'
    }

    return isCancel.value ? 'Cancel subscription' : 'Pause billing'
})

const selectedDate = computed(() => {
    if (isResume.value) {
        return 'Now'
    }

    if (form.mode === 'specific') {
        return formatDate(form.date)
    }

    if (form.mode === 'immediate') {
        return 'Now'
    }

    return formatDate(props.subscription?.current_period_end)
})

const reviewItems = computed(() => [
    {
        label: isCancel.value ? 'Cancellation' : isResume.value ? 'Resume' : 'Pause starts',
        value: selectedDate.value
    },
    {
        label: 'Current period',
        value: `${formatDate(props.subscription?.current_period_start)} -> ${formatDate(props.subscription?.current_period_end)}`
    }
])

function choose(mode) {
    form.mode = mode
}

function continueToReview() {
    if (form.mode === 'specific' && !form.date) {
        return
    }

    form.step = 'review'
}

function confirm() {
    if (isResume.value) {
        emit('confirm', { paused: false })

        return
    }

    if (isCancel.value) {
        emit('confirm', form.mode === 'specific'
            ? { ends_at: form.date }
            : { at_period_end: form.mode === 'period_end' }
        )

        return
    }

    emit('confirm', form.mode === 'specific'
        ? { paused_at: form.date }
        : { paused: true }
    )
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.mode = isCancel.value ? 'period_end' : 'immediate'
            form.date = ''
            form.step = 'choose'
        }
    }
)
</script>

<template>
    <Modal
        :open="open"
        :title="title"
        :subtitle="isCancel ? 'Choose when the subscription should end.' : 'Payment collection changes without deleting the subscription.'"
        max-width-class="max-w-3xl"
        @close="emit('close')"
    >
        <div class="space-y-5">
            <template v-if="form.step === 'choose'">
                <div
                    v-if="isResume"
                    class="border border-accent p-4"
                >
                    <div class="font-bold text-dark">Billing paused</div>
                    <p class="mt-2 text-sm text-dark/60">
                        Resume payment collection now. The subscription remains anchored to its existing billing dates.
                    </p>
                </div>

                <div
                    v-else
                    class="space-y-3"
                >
                    <button
                        v-if="isCancel"
                        type="button"
                        class="w-full border p-4 text-left"
                        :class="form.mode === 'period_end' ? 'border-dark bg-accent/10' : 'border-accent'"
                        @click="choose('period_end')"
                    >
                        <div class="font-bold text-dark">At the end of the current billing period</div>
                        <div class="mt-1 text-sm text-dark/60">
                            Current period: {{ formatDate(subscription?.current_period_start) }} -> {{ formatDate(subscription?.current_period_end) }}.
                        </div>
                    </button>

                    <button
                        type="button"
                        class="w-full border p-4 text-left"
                        :class="form.mode === 'specific' ? 'border-dark bg-accent/10' : 'border-accent'"
                        @click="choose('specific')"
                    >
                        <div class="font-bold text-dark">On a specific date</div>
                        <div class="mt-1 text-sm text-dark/60">
                            Keep billing active until the selected date.
                        </div>
                    </button>

                    <FormField
                        v-if="form.mode === 'specific'"
                        v-model="form.date"
                        :label="isCancel ? 'End date' : 'Pause starting'"
                        type="date"
                    />

                    <button
                        type="button"
                        class="w-full border p-4 text-left"
                        :class="form.mode === 'immediate' ? 'border-dark bg-accent/10' : 'border-accent'"
                        @click="choose('immediate')"
                    >
                        <div class="font-bold text-dark">{{ isCancel ? 'Cancel immediately' : 'Pause billing now' }}</div>
                        <div class="mt-1 text-sm text-dark/60">
                            Stripe will calculate any applicable billing effect from the effective date.
                        </div>
                    </button>
                </div>
            </template>

            <SubscriptionChangePreview
                v-else
                :title="isCancel ? 'Review cancellation' : isResume ? 'Review resume' : 'Review pause'"
                :items="reviewItems"
            />
        </div>

        <template #footer>
            <div class="flex justify-end gap-3 border-t border-accent p-6">
                <Button
                    :text="form.step === 'review' ? 'Back' : isCancel ? 'Keep subscription' : 'Cancel'"
                    variant="ghost"
                    @click="form.step === 'review' ? form.step = 'choose' : emit('close')"
                />

                <Button
                    v-if="form.step === 'choose'"
                    text="Continue"
                    variant="primary"
                    :disabled="form.mode === 'specific' && !form.date"
                    @click="continueToReview"
                />

                <Button
                    v-else
                    :text="isCancel ? 'Cancel subscription' : isResume ? 'Resume billing' : 'Pause billing'"
                    :variant="isCancel ? 'danger' : 'primary'"
                    :loading="busy"
                    @click="confirm"
                />
            </div>
        </template>
    </Modal>
</template>
