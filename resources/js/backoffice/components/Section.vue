<script setup>

defineProps({

    title: {
        type: String,
        default: ''
    },

    description: {
        type: String,
        default: ''
    },

})

</script>


<template>

    <section
        class="
            w-full
            space-y-8
        "
    >

        <div
            v-if="
                title ||
                description ||
                $slots.actions
            "
            class="
                flex
                flex-col
                gap-6
                md:flex-row
                md:items-start
                md:justify-between
            "
        >

            <div
                v-if="
                    title ||
                    description
                "
                class="
                    space-y-2
                    text-accent
                "
            >

                <h2
                    v-if="title"
                    class="
                        h2
                        text-left
                    "
                >

                    {{ title }}

                </h2>


                <p
                    v-if="description"
                    class="
                        max-w-2xl
                        text-sm
                        leading-6
                        text-accent/70
                    "
                >

                    {{ description }}

                </p>

            </div>


            <div
                v-if="$slots.actions"
                class="
                    shrink-0
                "
            >

                <slot name="actions" />

            </div>

        </div>


        <div>

            <slot />

        </div>

    </section>

</template>