<script setup>
import { computed } from 'vue'
const props = defineProps({ status: { type: [String, Boolean], required: true } })
const label = computed(() => typeof props.status === 'boolean' ? (props.status ? 'Active' : 'Inactive') : String(props.status).replaceAll('_', ' '))
const positive = computed(() => props.status === true || ['active', 'completed'].includes(props.status))
</script>

<template><span class="inline-flex min-h-6 items-center border px-2 font-mono text-[11px] font-bold uppercase" :class="positive ? 'border-dark bg-accent text-dark' : 'border-neutral-400 bg-neutral-100 text-neutral-700'">{{ label }}</span></template>