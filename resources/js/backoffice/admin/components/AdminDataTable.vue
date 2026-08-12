<script setup>
defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  emptyTitle: { type: String, default: 'No records yet.' },
  emptyText: { type: String, default: '' },
  sort: { type: String, default: '' },
  direction: { type: String, default: 'asc' },
})
defineEmits(['sort', 'row-click'])
</script>

<template>
  <div class="min-h-[280px] overflow-x-auto border border-dark bg-light">
    <table class="w-full min-w-[760px] border-collapse text-left text-sm">
      <thead><tr class="border-b border-dark bg-neutral-100"><th v-for="column in columns" :key="column.key" scope="col" class="h-12 px-4 font-mono text-xs font-bold uppercase"><button v-if="column.sortable" class="inline-flex items-center gap-2" @click="$emit('sort', column.sortKey || column.key)">{{ column.label }} <span aria-hidden="true">{{ sort === (column.sortKey || column.key) ? (direction === 'asc' ? '↑' : '↓') : '↕' }}</span></button><span v-else>{{ column.label }}</span></th></tr></thead>
      <tbody v-if="loading" aria-live="polite"><tr v-for="index in 5" :key="index" class="border-b border-neutral-300"><td v-for="column in columns" :key="column.key" class="h-16 px-4"><span class="block h-3 w-3/4 animate-pulse bg-neutral-200"></span></td></tr></tbody>
      <tbody v-else-if="rows.length"><tr v-for="row in rows" :key="row.id" class="border-b border-neutral-300 last:border-0 hover:bg-neutral-50" :class="$attrs.onRowClick ? 'cursor-pointer' : ''" @click="$emit('row-click', row)"><td v-for="column in columns" :key="column.key" class="px-4 py-4 align-middle"><slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">{{ row[column.key] ?? '—' }}</slot></td></tr></tbody>
      <tbody v-else><tr><td :colspan="columns.length" class="h-64 px-6 text-center"><p class="font-mono text-base font-bold">{{ emptyTitle }}</p><p v-if="emptyText" class="mx-auto mt-2 max-w-md text-sm text-neutral-600">{{ emptyText }}</p><div class="mt-5"><slot name="empty-action" /></div></td></tr></tbody>
    </table>
  </div>
</template>