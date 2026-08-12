<script setup>
defineProps({ meta: { type: Object, default: null } })
defineEmits(['change'])
</script>

<template>
  <nav v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-neutral-300 pt-4" aria-label="Pagination">
    <p class="text-xs text-neutral-600">{{ meta.from }}–{{ meta.to }} of {{ meta.total }}</p>
    <div class="flex gap-2">
      <button class="admin-button admin-button--quiet" :disabled="meta.current_page <= 1" @click="$emit('change', meta.current_page - 1)">Previous</button>
      <button class="admin-button admin-button--quiet" :disabled="meta.current_page >= meta.last_page" @click="$emit('change', meta.current_page + 1)">Next</button>
    </div>
  </nav>
</template>