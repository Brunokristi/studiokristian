<script setup>
import { computed } from 'vue'

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },

    compact: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue'])

const options = [
    { value: 'en', label: 'EN' },
    { value: 'sk', label: 'SK' },
]

const buttonClass = computed(() => {
    if (props.compact) {
        return 'px-2 py-1 font-mono text-[10px] font-bold uppercase transition-colors'
    }

    return 'px-3 py-2 font-mono text-xs font-bold uppercase transition-colors'
})

function selectLocale(nextLocale) {
    if (nextLocale === props.modelValue) {
        return
    }

    emit('update:modelValue', nextLocale)
}
</script>

<template>
    <div class="flex items-center gap-1 border border-dark bg-light p-1">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :class="[
                buttonClass,
                modelValue === option.value
                    ? 'bg-accent text-light'
                    : 'text-dark hover:text-accent'
            ]"
            :aria-pressed="modelValue === option.value"
            @click="selectLocale(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>