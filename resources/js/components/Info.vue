<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  heading: {
    type: String,
    default: '',
  },
  text: {
    type: String,
    default: '',
  },
  color: {
    type: String,
    default: 'dark',
  },
})

const isOpen = ref(false)

function toggleOpen() {
  isOpen.value = !isOpen.value
}

const iconClass = computed(() =>
  isOpen.value ? 'rotate-180' : 'rotate-0'
)

const headingColorClass = computed(() => {
  if (props.color === 'light') return 'text-light'
  if (props.color === 'accent') return 'text-accent'
  return 'text-dark'
})
</script>

<template>
  <div
    class="w-full border-t last:border-b transition-colors duration-300"
    :class="[
      isOpen
        ? 'bg-accent text-light border-light'
        : 'bg-transparent border-accent hover:bg-accent hover:text-light hover:border-light',
      !isOpen ? headingColorClass : ''
    ]"
  >
    <button
      class="w-full flex items-center justify-between gap-6 py-3 px-4 text-left cursor-pointer"
      @click="toggleOpen"
      :aria-expanded="isOpen"
    >
      <h3 class="h3">
        {{ heading }}
      </h3>

      <span
        class="shrink-0 transition-transform duration-300"
        :class="iconClass"
      >
        <i class="bi bi-arrow-down"></i>
      </span>
    </button>

    <transition name="accordion">
      <div v-show="isOpen" class="accordion-content pb-6 pr-12 pl-4">
        <p class="p">
          {{ text }}
        </p>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.accordion-content {
  overflow: hidden;
}

.accordion-enter-active,
.accordion-leave-active {
  transition: max-height 0.3s ease, opacity 0.3s ease;
}

.accordion-enter-from,
.accordion-leave-to {
  opacity: 0;
  max-height: 0;
}

.accordion-enter-to,
.accordion-leave-from {
  opacity: 1;
  max-height: 300px;
}
</style>