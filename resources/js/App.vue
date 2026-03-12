<script setup>
import { computed } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';

const route = useRoute();
const theme = computed(() => route.meta.theme ?? 'dark');
const footer = computed(() => route.meta.footer ?? true);

const backgroundColor = computed(() => theme.value === 'light' ? 'bg-light' : 'bg-dark');

</script>

<template>
    <div :class="[backgroundColor, 'min-h-screen flex flex-col']">
        <Navbar :variant="theme" />
        <main class="flex-1">
            <RouterView />
        </main>
        <Footer v-if="footer" />
        <BottomNav />
    </div>
</template>