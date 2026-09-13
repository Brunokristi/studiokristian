<script setup>

defineProps({

    text: {
        type: String,
        default: 'Loading...'
    }

})

</script>


<template>

    <div
        class="
            flex
            min-h-24
            items-center
        "
        role="status"
        aria-live="polite"
    >

        <div
            class="
                flex
                items-center
                gap-3
            "
        >

            <span
                class="
                    h-2
                    w-2
                    animate-pulse
                    rounded-full
                    bg-accent
                "
            />

            <p
                class="
                    p
                    uppercase
                    text-dark/40
                "
            >

                {{ text }}

            </p>

        </div>

    </div>

</template>