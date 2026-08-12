<script setup>
defineProps({
    data: { type: Object, required: true },
})
</script>

<template>
    <section>
        <p class="font-mono text-xs font-bold uppercase text-dark/45">{{ data.company_name }}</p>
        <h1 class="mt-3 font-mono text-4xl font-bold uppercase sm:text-5xl">Your projects</h1>

        <div class="mt-10 grid gap-3 md:grid-cols-2">
            <a
                v-for="project in data.projects"
                :key="project.id"
                :href="project.url"
                class="flex min-h-48 flex-col justify-between border border-dark/20 bg-white p-6 transition-colors hover:border-dark"
            >
                <div>
                    <p class="text-xs text-dark/45">{{ project.service_name }}</p>
                    <h2 class="mt-3 font-mono text-xl font-bold">{{ project.name }}</h2>
                </div>
                <div class="flex items-end justify-between gap-4 font-mono text-xs uppercase">
                    <span>{{ project.status }}</span>
                    <span>{{ project.action_count ? `Action required: ${project.action_count}` : '↗' }}</span>
                </div>
            </a>
            <p v-if="!data.projects.length" class="border border-dark/20 bg-white p-6 text-sm text-dark/50">
                There are no active projects here yet.
            </p>
        </div>
    </section>
</template>
