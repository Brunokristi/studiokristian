<script setup>
const props = defineProps({
    title: {
        type: String,
        default: 'Billing preview'
    },
    summary: {
        type: String,
        default: ''
    },
    items: {
        type: Array,
        default: () => []
    }
})
</script>

<template>
    <section class="border border-accent bg-accent/5 p-4">
        <div class="text-xs font-bold uppercase tracking-wide text-dark/60">
            {{ title }}
        </div>

        <p class="mt-2 text-sm text-dark/70">
            {{ summary || 'Stripe will calculate prorations, credits, and the next invoice from the selected effective date.' }}
        </p>

        <div
            v-if="items.length"
            class="mt-4 space-y-2"
        >
            <div
                v-for="item in items"
                :key="item.label"
                class="flex items-center justify-between gap-4 text-sm"
            >
                <span class="text-dark/60">{{ item.label }}</span>
                <span class="font-bold text-dark">{{ item.value }}</span>
            </div>
        </div>
    </section>
</template>
