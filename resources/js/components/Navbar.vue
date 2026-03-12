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
    <nav class="w-full px-6 h-14 flex items-center justify-between sticky top-0 z-10">
        <h1 :class="['h1', studioColor]">studio</h1>

        <a href="/">
            <img :src="logoSrc" alt="Studio Kristian Logo" class="h-4 w-auto" />
        </a>

        <h1 :class="['h1', kristianColor]">kristian</h1>
    </nav>
</template>


