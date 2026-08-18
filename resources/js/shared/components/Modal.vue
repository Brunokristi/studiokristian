<script setup>
import {
    computed,
    useSlots
} from 'vue'


const props = defineProps({
    open: {
        type: Boolean,
        default: false
    },

    title: {
        type: String,
        default: ''
    },

    subtitle: {
        type: String,
        default: ''
    },

    ariaLabel: {
        type: String,
        default: ''
    },

    closeLabel: {
        type: String,
        default: 'Close dialog'
    },

    closeOnBackdrop: {
        type: Boolean,
        default: true
    },

    showCloseButton: {
        type: Boolean,
        default: true
    },

    overlayClass: {
        type: String,
        default: 'fixed inset-0 z-[110] flex items-center justify-center bg-dark/55 p-4 backdrop-blur-sm'
    },

    panelClass: {
        type: String,
        default: 'border border-accent bg-light shadow-xl'
    },

    maxWidthClass: {
        type: String,
        default: 'max-w-2xl'
    },

    maxHeightClass: {
        type: String,
        default: 'max-h-[90vh]'
    },

    headerClass: {
        type: String,
        default: 'flex items-start justify-between gap-6 p-6'
    },

    bodyClass: {
        type: String,
        default: 'p-6'
    }
})


const emit = defineEmits([
    'close'
])


const slots =
    useSlots()


const hasHeader =
    computed(() => {
        return Boolean(
            props.title ||
            props.subtitle ||
            props.showCloseButton ||
            slots.header
        )
    })


function requestClose() {
    emit('close')
}


function handleBackdropClick() {
    if (!props.closeOnBackdrop) {
        return
    }

    requestClose()
}
</script>


<template>
    <Teleport to="body">
        <div
            v-if="open"
            :class="overlayClass"
            @click.self="handleBackdropClick"
        >
            <section
                class="w-full overflow-hidden"
                :class="[
                    maxWidthClass,
                    maxHeightClass,
                    panelClass
                ]"
                role="dialog"
                aria-modal="true"
                :aria-label="
                    ariaLabel ||
                    title ||
                    'Dialog'
                "
            >
                <div
                    v-if="hasHeader"
                    :class="headerClass"
                >
                    <slot name="header">
                        <div>
                            <p
                                v-if="title"
                                class="h3 text-accent"
                            >
                                {{ title }}
                            </p>

                            <p
                                v-if="subtitle"
                                class="p mt-2 text-dark uppercase"
                            >
                                {{ subtitle }}
                            </p>
                        </div>
                    </slot>

                    <button
                        v-if="showCloseButton"
                        type="button"
                        class="shrink-0 p font-mono text-lg leading-none text-dark transition-colors hover:text-accent"
                        :aria-label="closeLabel"
                        @click="requestClose"
                    >
                        <i class="bi bi-x-lg p" />
                    </button>
                </div>

                <div :class="bodyClass">
                    <slot />
                </div>

                <slot name="footer" />
            </section>
        </div>
    </Teleport>
</template>
