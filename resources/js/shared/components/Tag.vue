<script setup>
defineProps({
    label: {
        type: String,
        default: ''
    },

    text: {
        type: String,
        default: ''
    }
})
</script>


<template>
    <span
        class="
            inline-flex
            w-fit
            items-center
            bg-accent
            px-2
            py-1
            font-mono
            text-[10px]
            font-bold
            uppercase
            leading-none
            text-light
        "
    >
        <slot>
            {{ label || text }}
        </slot>
    </span>
</template>