<script setup>
import AdminModalShell from './AdminModalShell.vue'


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

    maxWidthClass: {
        type: String,
        default: 'max-w-2xl'
    },

    maxHeightClass: {
        type: String,
        default: 'max-h-[90vh]'
    },

    panelClass: {
        type: String,
        default: ''
    },

    headerClass: {
        type: String,
        default: ''
    },

    bodyClass: {
        type: String,
        default: ''
    },

    overlayClass: {
        type: String,
        default: ''
    },

    closeOnBackdrop: {
        type: Boolean,
        default: true
    },

    showCloseButton: {
        type: Boolean,
        default: true
    }
})


const emit = defineEmits([
    'close'
])


function emitClose() {
    emit('close')
}
</script>


<template>
    <AdminModalShell
        :open="open"
        :title="title"
        :subtitle="subtitle"
        :aria-label="ariaLabel"
        :close-label="closeLabel"
        :max-width-class="maxWidthClass"
        :max-height-class="maxHeightClass"
        :panel-class="panelClass || undefined"
        :header-class="headerClass || undefined"
        :body-class="bodyClass || undefined"
        :overlay-class="overlayClass || undefined"
        :close-on-backdrop="closeOnBackdrop"
        :show-close-button="showCloseButton"
        @close="emitClose"
    >
        <template
            v-if="$slots.header"
            #header
        >
            <slot name="header" />
        </template>

        <slot />

        <template
            v-if="$slots.footer"
            #footer
        >
            <slot name="footer" />
        </template>
    </AdminModalShell>
</template>
