<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
    heading: {
        type: String,
        default: '',
    },

    text: {
        type: String,
        default: '',
    },

    bgColor: {
        type: String,
        default: '',
    },

    image: {
        type: String,
        default: '',
    },

    link: {
        type: String,
        default: '',
    },

    textColor: {
        type: String,
        default: 'text-dark',
    },
})

const tag = computed(() =>
    props.link
        ? RouterLink
        : 'article'
)

const isHexColor = computed(() =>
    /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(
        props.bgColor || ''
    )
)

const hoverBgClass = computed(() => {
    if (
        !props.bgColor ||
        isHexColor.value
    ) {
        return 'bg-accent'
    }

    return props.bgColor
})

const hoverBgStyle = computed(() => {
    if (!isHexColor.value) {
        return undefined
    }

    return {
        backgroundColor: props.bgColor,
    }
})
</script>

<template>
    <component
        :is="tag"
        :to="link || undefined"
        class="
            group
            relative
            min-h-[250px]
            p-6
            overflow-hidden
            flex
            flex-col
            justify-between
            bg-light
        "
        :class="[
            link
                ? 'cursor-pointer'
                : ''
        ]"
    >
        <!-- Hover background -->
        <div
            class="
                absolute
                inset-0
                opacity-0
                transition-opacity
                duration-300
                group-hover:opacity-100
            "
            :class="hoverBgClass"
            :style="hoverBgStyle"
        ></div>

        <!-- Content -->
        <div
            class="
                relative
                z-10
                transition-colors
                duration-300
            "
            :class="[
                textColor,
                'group-hover:text-light'
            ]"
        >
            <!-- Heading -->
            <div
                class="
                    h-[56px]
                    overflow-hidden
                "
            >
                <h3
                    class="
                        h3
                        line-clamp-2
                    "
                >
                    {{ heading }}
                </h3>
            </div>

            <!-- Description -->
            <div
                class="
                    h-[96px]
                    mt-6
                    overflow-hidden
                "
            >
                <p
                    v-if="text"
                    class="
                        p
                        line-clamp-5
                    "
                >
                    {{ text }}
                </p>
            </div>
        </div>

        <!-- Logo -->
        <div
            v-if="image"
            class="
                relative
                z-10
                mt-8
                flex
                justify-center
                opacity-0
                transition-opacity
                duration-300
                group-hover:opacity-100
            "
        >
            <img
                :src="image"
                alt=""
                class="
                    max-h-6
                    object-contain
                "
            />
        </div>
    </component>
</template>

