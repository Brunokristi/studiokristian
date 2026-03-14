<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'dark',
  },
  text: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['click'])

const buttonColor = computed(() =>
  props.variant === 'light' ? 'text-light hover:text-accent' :
  props.variant === 'dark' ? 'text-dark hover:text-accent' :
  props.variant === 'accent' ? 'text-accent hover:text-light' :
  'text-dark'
)
</script>

<template>
    <div class="w-full flex justify-center">
    <button
      type="button"
            class="group inline-flex flex-col items-start cursor-pointer transition-color duration-300"
            :class="buttonColor"
      @click="emit('click')"
        >
            <span class="font-mono text-sm font-bold lowercase">
            {{ props.text }}
            </span>

            <span class="relative mt-1 h-[2px] w-full bg-current transition-transform duration-300 group-hover:translate-x-1">
            <span
                class="absolute right-0 top-1/2 h-[7px] w-[7px] -translate-y-1/2 border-t-2 border-r-2 border-current rotate-45"
            ></span>
            </span>
            </button>
    </div>
</template>