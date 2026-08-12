<script setup>
defineProps({ open: Boolean, title: { type: String, required: true }, text: { type: String, required: true }, confirmLabel: { type: String, default: 'Confirm' }, busy: Boolean })
defineEmits(['confirm', 'close'])
</script>

<template>
  <teleport to="body"><div v-if="open" class="fixed inset-0 z-50 grid place-items-center bg-dark/55 p-4" role="presentation" @click.self="$emit('close')"><section class="w-full max-w-md border border-dark bg-light p-6" role="dialog" aria-modal="true" :aria-labelledby="`confirm-${title}`"><h2 :id="`confirm-${title}`" class="font-mono text-xl font-bold">{{ title }}</h2><p class="mt-3 text-sm leading-relaxed text-neutral-600">{{ text }}</p><div class="mt-7 flex justify-end gap-2"><button class="admin-button admin-button--quiet" :disabled="busy" @click="$emit('close')">Cancel</button><button class="admin-button bg-dark text-light" :disabled="busy" @click="$emit('confirm')">{{ busy ? 'Working…' : confirmLabel }}</button></div></section></div></teleport>
</template>