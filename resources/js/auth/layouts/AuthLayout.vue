<script setup>
import Navbar from '../../public/components/Navbar.vue'
import LanguageToggle from '@shared/components/LanguageToggle.vue'

defineProps({
    locale: {
        type: String,
        required: true,
    },
})

defineEmits(['set-locale'])
</script>

<template>
    <div class="min-h-screen bg-light text-dark" data-theme="light">
        <Navbar variant="light" />

        <main class="flex min-h-screen items-center justify-center px-5 py-24 sm:px-8">
            <section class="w-full max-w-xl">
                <slot />
            </section>
        </main>

        <LanguageToggle
            :model-value="locale"
            class="fixed bottom-6 right-6 z-50"
            @update:model-value="$emit('set-locale', $event)"
        />
    </div>
</template>