<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'dark',
    },
});

const studioColor = computed(() => props.variant === 'light' ? 'text-accent' : 'text-accent');
const kristianColor = computed(() => props.variant === 'light' ? 'text-dark' : 'text-accent');
const logoSrc = '/assets/logo.svg';
</script>

<template>
    <a class="w-full px-6 h-14 flex items-center justify-between sticky top-0 z-50" href="/">
        <h1 :class="['h1', studioColor, 'w-50']">studio</h1>

        <img :src="logoSrc" alt="Studio Kristian Logo" class="h-4 w-auto" />

        <h1 :class="['h1', kristianColor, 'w-50', 'text-end']">kristian</h1>
    </a>
</template>


