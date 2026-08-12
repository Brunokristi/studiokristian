<script setup>
defineProps({
    title: {
        type: String,
        required: true
    },


    eyebrow: {
        type: String,
        default: ''
    },


    description: {
        type: String,
        default: ''
    },


    logo: {
        type: String,
        default: '/assets/logo.svg'
    }
})
</script>


<template>
    <header
        class="
            w-full
            space-y-8
            border-b
            border-accent
            pb-8
        "
    >
        <!-- Brand -->
        <div
            class="
                grid
                grid-cols-[1fr_auto_1fr]
                items-center
                gap-4
            "
        >
            <span
                class="
                    font-mono
                    text-xs
                    font-bold
                    text-accent
                    sm:text-sm
                "
            >
                studio
            </span>


            <img
                :src="logo"
                alt="Studio Kristian"
                class="
                    h-auto
                    w-10
                    sm:w-12
                "
            >


            <span
                class="
                    text-right
                    font-mono
                    text-xs
                    font-bold
                    text-dark
                    sm:text-sm
                "
            >
                kristian
            </span>
        </div>


        <!-- Page information -->
        <div
            class="
                flex
                flex-col
                gap-8
                lg:flex-row
                lg:items-end
                lg:justify-between
            "
        >
            <div
                class="
                    min-w-0
                    max-w-3xl
                "
            >
                <p
                    v-if="eyebrow"
                    class="
                        h3
                        mb-3
                        text-accent
                    "
                >
                    {{ eyebrow }}
                </p>


                <h1
                    class="
                        h2
                        text-left
                        text-dark
                    "
                >
                    {{ title }}
                </h1>


                <p
                    v-if="description"
                    class="
                        p
                        mt-3
                        max-w-2xl
                        uppercase
                        text-dark/60
                    "
                >
                    {{ description }}
                </p>
            </div>


            <!-- Actions -->
            <div
                v-if="$slots.default"
                class="
                    flex
                    w-full
                    flex-wrap
                    items-center
                    gap-x-8
                    gap-y-5
                    lg:w-auto
                    lg:shrink-0
                    lg:justify-end
                "
            >
                <slot />
            </div>
        </div>
    </header>
</template>