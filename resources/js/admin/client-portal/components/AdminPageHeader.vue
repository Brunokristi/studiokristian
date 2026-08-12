<script setup>
defineProps({ title: { type: String, required: true }, eyebrow: { type: String, default: '' }, description: { type: String, default: '' } })
</script>

<template>
  <header class="flex flex-col gap-5 border-b border-dark pb-6 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <p v-if="eyebrow" class="mb-2 font-mono text-xs font-bold uppercase">{{ eyebrow }}</p>
      <h1 class="font-mono text-3xl font-bold leading-tight sm:text-4xl">{{ title }}</h1>
      <p v-if="description" class="mt-3 max-w-2xl text-sm leading-relaxed text-neutral-600">{{ description }}</p>
    </div>
    <div class="flex shrink-0 flex-wrap gap-2"><slot /></div>
  </header>
</template>