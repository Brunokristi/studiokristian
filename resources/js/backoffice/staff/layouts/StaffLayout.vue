<script setup>
import WorkspacePage from '@staff/pages/WorkspacePage.vue'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''
</script>

<template>
    <div class="min-h-screen bg-[#f5f5f2] text-dark">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-dark bg-light px-5">
            <strong class="font-mono text-sm uppercase">Studio Kristian / Workspace</strong>
            <form method="POST" action="/logout">
                <input type="hidden" name="_token" :value="csrfToken">
                <button class="font-mono text-xs font-bold uppercase" type="submit">Log out</button>
            </form>
        </header>

        <main class="mx-auto max-w-[1600px] p-4 sm:p-8">
            <WorkspacePage />
        </main>
    </div>
</template>