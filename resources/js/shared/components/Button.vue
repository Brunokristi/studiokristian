<script setup>
import {
    computed
} from 'vue'



const props = defineProps({
    variant: {
        type: String,
        default: 'dark'
    },


    hoverVariant: {
        type: String,
        default: ''
    },


    text: {
        type: String,
        default: ''
    },


    type: {
        type: String,
        default: 'button'
    },


    disabled: {
        type: Boolean,
        default: false
    },


    loading: {
        type: Boolean,
        default: false
    },


    loadingText: {
        type: String,
        default: 'Loading'
    },


    lowercase: {
        type: Boolean,
        default: true
    },


    align: {
        type: String,
        default: 'center'
    }
})



const emit =
    defineEmits([
        'click'
    ])



const textColor =
    computed(() => {
        if (
            props.variant ===
            'light'
        ) {
            return 'text-light'
        }



        if (
            props.variant ===
            'accent'
        ) {
            return 'text-accent'
        }



        return 'text-dark'
    })



const hoverTextColor =
    computed(() => {
        const variant =
            props.hoverVariant ||
            (
                props.variant ===
                'light'
                    ? 'accent'
                    : props.variant ===
                        'accent'
                        ? 'light'
                        : 'accent'
            )



        if (
            variant ===
            'light'
        ) {
            return 'hover:text-light'
        }



        if (
            variant ===
            'accent'
        ) {
            return 'hover:text-accent'
        }



        return 'hover:text-dark'
    })



const buttonColor =
    computed(() => {
        return [
            textColor.value,
            hoverTextColor.value
        ]
    })



const wrapperAlignment =
    computed(() => {
        if (
            props.align ===
            'left'
        ) {
            return 'justify-start'
        }



        if (
            props.align ===
            'right'
        ) {
            return 'justify-end'
        }



        return 'justify-center'
    })
</script>



<template>
    <div
        class="
            flex
            w-full
        "
        :class="
            wrapperAlignment
        "
    >
        <button
            :type="type"
            :disabled="
                disabled ||
                loading
            "
            :aria-busy="
                loading
                    ? 'true'
                    : undefined
            "
            class="
                group
                inline-flex
                cursor-pointer
                flex-col
                items-start
                transition-colors
                duration-300
                disabled:cursor-not-allowed
                disabled:opacity-40
            "
            :class="
                buttonColor
            "
            @click="
                emit(
                    'click',
                    $event
                )
            "
        >
            <span
                class="
                    flex
                    items-center
                    font-mono
                    text-sm
                    font-bold
                "
                :class="{
                    lowercase:
                        lowercase
                }"
            >
                <template
                    v-if="loading"
                >
                    {{ loadingText }}


                    <span
                        class="
                            ml-[1px]
                            inline-flex
                        "
                        aria-hidden="true"
                    >
                        <span
                            class="
                                animate-pulse
                                [animation-delay:0ms]
                            "
                        >
                            .
                        </span>


                        <span
                            class="
                                animate-pulse
                                [animation-delay:200ms]
                            "
                        >
                            .
                        </span>


                        <span
                            class="
                                animate-pulse
                                [animation-delay:400ms]
                            "
                        >
                            .
                        </span>
                    </span>
                </template>


                <template
                    v-else
                >
                    {{ text }}
                </template>
            </span>


            <span
                class="
                    relative
                    mt-1
                    h-[2px]
                    w-full
                    bg-current
                    transition-transform
                    duration-300
                    group-hover:translate-x-1
                    group-disabled:translate-x-0
                "
            >
                <span
                    class="
                        absolute
                        right-0
                        top-1/2
                        h-[7px]
                        w-[7px]
                        -translate-y-1/2
                        rotate-45
                        border-r-2
                        border-t-2
                        border-current
                    "
                />
            </span>
        </button>
    </div>
</template>