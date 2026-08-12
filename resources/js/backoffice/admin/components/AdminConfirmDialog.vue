<script setup>
import {
    onBeforeUnmount,
    watch
} from 'vue'


import Button from '@shared/components/Button.vue'


const props =
    defineProps({
        open: {
            type: Boolean,
            default: false
        },

        title: {
            type: String,
            required: true
        },

        text: {
            type: String,
            required: true
        },

        confirmLabel: {
            type: String,
            default: 'confirm'
        },

        busy: {
            type: Boolean,
            default: false
        }
    })


const emit =
    defineEmits([
        'confirm',
        'close'
    ])


function close() {
    if (
        props.busy
    ) {
        return
    }


    emit('close')
}


function handleKeydown(
    event
) {
    if (
        event.key ===
        'Escape'
    ) {
        close()
    }
}


watch(
    () => props.open,
    value => {
        if (value) {
            document.addEventListener(
                'keydown',
                handleKeydown
            )

            document.body.style.overflow =
                'hidden'
        } else {
            document.removeEventListener(
                'keydown',
                handleKeydown
            )

            document.body.style.overflow =
                ''
        }
    }
)


onBeforeUnmount(() => {
    document.removeEventListener(
        'keydown',
        handleKeydown
    )

    document.body.style.overflow =
        ''
})
</script>


<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="
                fixed
                inset-0
                z-50
                flex
                items-center
                justify-center
                bg-dark/50
                p-5
                sm:p-8
            "
            role="presentation"
            @mousedown.self="close"
        >
            <section
                class="
                    w-full
                    max-w-lg
                    border
                    border-accent
                    bg-light
                    p-6
                    sm:p-8
                "
                role="dialog"
                aria-modal="true"
                :aria-labelledby="
                    `confirm-${title}`
                "
            >
                <!-- Content -->
                <div
                    class="
                    "
                >
                    <h2
                        :id="
                            `confirm-${title}`
                        "
                        class="
                            h3
                            text-accent
                        "
                    >
                        {{ title }}
                    </h2>


                    <p
                        class="
                            p
                            mt-4
                            max-w-xl
                            uppercase
                            text-dark
                        "
                    >
                        {{ text }}
                    </p>
                </div>


                <!-- Actions -->
                <div
                    class="
                        flex
                        flex-col
                        gap-4
                        pt-6
                    "
                >
                    <Button
                        type="button"
                        text="cancel"
                        :disabled="busy"
                        @click="close"
                        align="right"
                    />


                    <Button
                        type="button"
                        :text="confirmLabel"
                        loading-text="working"
                        :loading="busy"
                        :disabled="busy"
                        variant="accent"
                        @click="
                            emit(
                                'confirm'
                            )
                        "
                        align="right"
                        hover-variant="dark"
                    />
                </div>
            </section>
        </div>
    </Teleport>
</template>