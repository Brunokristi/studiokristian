<script setup>
import {
    RouterLink
} from 'vue-router'


defineProps({
    title: {
        type: String,
        required: true
    },


    eyebrow: {
        type: String,
        default: ''
    },


    breadcrumbs: {
        type: Array,
        default: () => []
    }
})
</script>


<template>
    <header class="pb-4">
        <!-- Breadcrumbs -->
        <nav
            v-if="
                breadcrumbs.length ||
                eyebrow
            "
            aria-label="Breadcrumb"
            class="
                mb-2
                flex
                min-w-0
                flex-wrap
                items-center
                gap-x-2
                gap-y-1
                p
                uppercase
            "
        >
            <RouterLink
                :to="{
                    name: 'dashboard'
                }"
                class="
                    text-dark
                    transition-colors
                    hover:text-accent
                "
            >
                Admin
            </RouterLink>


            <template
                v-for="(
                    breadcrumb,
                    index
                ) in breadcrumbs"
                :key="
                    `${breadcrumb.label}-${index}`
                "
            >
                <span
                    class="
                        text-dark
                    "
                    aria-hidden="true"
                >
                    /
                </span>


                <RouterLink
                    v-if="
                        breadcrumb.to
                    "
                    :to="
                        breadcrumb.to
                    "
                    class="
                        min-w-0
                        max-w-[12rem]
                        truncate
                        text-dark
                        transition-colors
                        hover:text-accent
                        sm:max-w-none
                    "
                >
                    {{ breadcrumb.label }}
                </RouterLink>


                <span
                    v-else
                    class="
                        min-w-0
                        max-w-[12rem]
                        truncate
                        text-accent
                        sm:max-w-none
                    "
                    aria-current="page"
                >
                    {{ breadcrumb.label }}
                </span>
            </template>


            <template
                v-if="
                    !breadcrumbs.length &&
                    eyebrow
                "
            >
                <span
                    class="
                        text-dark
                    "
                >
                    /
                </span>


                <span
                    class="
                        text-accent
                    "
                >
                    {{ eyebrow }}
                </span>
            </template>
        </nav>


        <div
            class="
                    flex
                    flex-col
                    gap-6
                    md:flex-row
                    md:items-end
                    md:justify-between
                "
            >
                <div>
                    <h1 class="h2 text-left">
                        {{ title }}
                    </h1>
                </div>

                <div
                    v-if="$slots.default"
                    class="
                        flex
                        flex-wrap
                        gap-x-7
                        gap-y-4
                        md:justify-end
                    "
                >
                    <slot />
                </div>
        </div>
    </header>
</template>