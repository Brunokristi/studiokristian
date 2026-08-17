<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },

    eyebrow: {
        type: String,
        default: '',
    },

    breadcrumbs: {
        type: Array,
        default: () => [],
    },

    homeUrl: {
        type: String,
        default: '/client',
    },
})
</script>

<template>
    <header class="pb-4">
        <nav
            v-if="breadcrumbs.length || eyebrow"
            aria-label="Breadcrumb"
            class="mb-2 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 p uppercase"
        >
            <a
                :href="homeUrl"
                class="text-dark transition-colors hover:text-accent"
            >
                Client
            </a>

            <template
                v-for="(breadcrumb, index) in breadcrumbs"
                :key="`${breadcrumb.label}-${index}`"
            >
                <span class="text-dark" aria-hidden="true">/</span>

                <a
                    v-if="breadcrumb.href"
                    :href="breadcrumb.href"
                    class="min-w-0 max-w-[12rem] truncate text-dark transition-colors hover:text-accent sm:max-w-none"
                >
                    {{ breadcrumb.label }}
                </a>

                <span
                    v-else
                    class="min-w-0 max-w-[12rem] truncate text-accent sm:max-w-none"
                    aria-current="page"
                >
                    {{ breadcrumb.label }}
                </span>
            </template>

            <template v-if="!breadcrumbs.length && eyebrow">
                <span class="text-dark">/</span>
                <span class="text-accent">{{ eyebrow }}</span>
            </template>
        </nav>

        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="h2 text-left">{{ title }}</h1>
            </div>

            <div
                v-if="$slots.default"
                class="flex flex-wrap gap-x-4 gap-y-3 md:justify-end"
            >
                <slot />
            </div>
        </div>
    </header>
</template>