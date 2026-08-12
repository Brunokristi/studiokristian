<script setup>
defineProps({
    page: { type: Object, required: true },
    csrfToken: { type: String, required: true },
})
</script>

<template>
    <div class="min-h-screen bg-[#f4f3ef] text-dark">
        <header class="sticky top-0 z-30 border-b border-dark/15 bg-[#f4f3ef]">
            <div class="mx-auto flex h-16 max-w-[1440px] items-center justify-between gap-5 px-5 sm:px-8">
                <a :href="page.urls.dashboard" class="font-mono text-xs font-bold uppercase">Studio Kristian / Client</a>
                <div class="flex items-center gap-5">
                    <span class="hidden text-xs text-dark/50 sm:block">{{ page.contact.company_name }}</span>
                    <form method="POST" :action="page.urls.logout">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <button class="font-mono text-xs font-bold uppercase hover:text-accent" type="submit">Log out</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1200px] px-5 py-10 sm:px-8 sm:py-14">
            <div v-if="page.status" class="mb-6 border-l-2 border-accent bg-white px-5 py-4 text-sm" role="status">
                {{ page.status }}
            </div>
            <div v-if="page.error" class="mb-6 border-l-2 border-red-700 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                {{ page.error }}
            </div>
            <slot />
        </main>
    </div>
</template>
