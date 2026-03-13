<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  heading: {
    type: String,
    default: '',
  },
  text: {
    type: String,
    default: '',
  },
  duration: {
    type: Number,
    default: 3000,
  },
  modelValue: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const isVisible = ref(props.modelValue)
let timeoutId = null

function clearTimer() {
  if (timeoutId) {
    clearTimeout(timeoutId)
    timeoutId = null
  }
}

function startTimer() {
  clearTimer()

  if (props.duration > 0) {
    timeoutId = setTimeout(() => {
      closeToast()
    }, props.duration)
  }
}

function closeToast() {
  isVisible.value = false
  emit('update:modelValue', false)
  clearTimer()
}

watch(
  () => props.modelValue,
  (newValue) => {
    isVisible.value = newValue

    if (newValue) {
      startTimer()
    } else {
      clearTimer()
    }
  },
  { immediate: true }
)

onBeforeUnmount(() => {
  clearTimer()
})
</script>

<template>
  <teleport to="body">
    <transition name="toast">
      <div
        v-if="isVisible"
        class="fixed bottom-6 inset-x-0 z-50 w-[70vw] mx-auto border border-dark bg-light px-4 py-4 shadow-lg"
        role="status"
        aria-live="polite"
      >
        <button
          class="absolute right-3 top-3 text-dark cursor-pointer"
          aria-label="Close toast"
          @click="closeToast"
        >
          <i class="bi bi-x-lg"></i>
        </button>

        <div class="pr-8">
          <h3 class="h3 mb-2">
            {{ heading }}
          </h3>

          <p class="p">
            {{ text }}
          </p>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(24px);
}

.toast-enter-to,
.toast-leave-from {
  opacity: 1;
  transform: translateY(0);
}
</style>