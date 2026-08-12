<script setup>
import Navbar from '@shared/components/Navbar.vue'

defineProps({
    locale: {
        type: String,
        required: true,
    },
})

defineEmits(['toggle-locale'])
</script>

<template>
    <div class="min-h-screen bg-light text-dark" data-theme="light">
        <Navbar variant="light" />

        <main class="flex min-h-screen items-center justify-center px-5 py-24 sm:px-8">
            <section class="w-full max-w-xl">
                <slot />
            </section>
        </main>

        <button
            class="fixed bottom-6 right-6 z-50 cursor-pointer p uppercase text-dark transition-colors hover:text-accent"
            type="button"
            :title="locale === 'en' ? 'Prepnúť do slovenčiny' : 'Switch to English'"
            :aria-label="locale === 'en' ? 'Prepnúť do slovenčiny' : 'Switch to English'"
            @click="$emit('toggle-locale')"
        >
            <span class="ml-2">{{ locale === 'en' ? 'SK' : 'EN' }}</span>
        </button>
    </div>
</template>