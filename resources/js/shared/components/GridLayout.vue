<script setup>
import GridCard from './GridCard.vue'

const props = defineProps({
  cards: {
    type: Array,
    default: () => []
  }
})
</script>

<template>
  <div class="grid grid-cols-2 gap-px bg-accent border border-accent">
    <GridCard
      v-for="(card, index) in cards"
      :key="index"
      v-bind="card"
    />
  </div>
</template>