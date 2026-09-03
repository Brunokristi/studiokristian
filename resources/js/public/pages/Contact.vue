<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Button from '../../shared/components/Button.vue'
import Info from '../../shared/components/Info.vue'

import { useI18n } from 'vue-i18n'
const { t, tm } = useI18n()

import { useSeoMeta } from '../composables/useSeoMeta'
import { useGlobalActions } from '../composables/useGlobalActions'

const {
    openEmail,
    openInstagram,
} = useGlobalActions()

useSeoMeta({
    title: () => t('seo.contact.title'),
    description: () => t('seo.contact.description'),
})

const callerSrc = '/assets/calling.png'

const seconds = ref(0)
const transcriptSource = computed(() =>
    t('contactPage.transcript')
)

let timer: number | undefined

type ContactInfoItem = {
    heading: string
    text: string
}

const items = computed(() => {
    const localizedItems =
        tm('contactPage.items') as ContactInfoItem[]

    return localizedItems.map((item) => ({
        heading: item.heading,
        text: item.text,
        color: 'light',
    }))
})

const topItems = computed(() =>
    items.value.slice(0, 3)
)

const bottomItems = computed(() =>
    items.value.slice(3)
)

const transcriptWords = computed(() =>
    transcriptSource.value
        .trim()
        .split(/\s+/)
)

const visibleWordCount = ref(0)

let transcriptTimer: number | undefined

const isTranscriptFinished = computed(() => {
    return (
        visibleWordCount.value >=
        transcriptWords.value.length
    )
})

const visibleTranscript = computed(() => {
    return transcriptWords.value
        .slice(0, visibleWordCount.value)
        .join(' ')
})

const timeFormatted = computed(() => {
    const mins = Math.floor(seconds.value / 60)
    const secs = seconds.value % 60

    return `${mins}:${secs
        .toString()
        .padStart(2, '0')}`
})

const callStatus = computed(() => {
    return isTranscriptFinished.value
        ? t('contactPage.callEnded', {
            time: timeFormatted.value,
        })
        : timeFormatted.value
})

/*
|--------------------------------------------------------------------------
| Pickup slider
|--------------------------------------------------------------------------
*/

const slider = ref<HTMLElement | null>(null)

const isDragging = ref(false)
const dragX = ref(0)

const knobSize = 40
const sliderPadding = 4

const maxDrag = computed(() => {
    if (!slider.value) {
        return 0
    }

    return Math.max(
        0,
        slider.value.clientWidth -
            knobSize -
            sliderPadding * 2
    )
})

let activePointerId: number | null = null
let startPointerX = 0
let startDragX = 0

function getSliderX(clientX: number) {
    if (!slider.value) {
        return 0
    }

    const rect =
        slider.value.getBoundingClientRect()

    return clientX - rect.left
}

function startDrag(event: PointerEvent) {
    if (isTranscriptFinished.value) {
        return
    }

    const target =
        event.currentTarget as HTMLElement

    target.setPointerCapture?.(
        event.pointerId
    )

    activePointerId = event.pointerId
    isDragging.value = true

    const pointerX =
        getSliderX(event.clientX)

    /*
     * If the user grabs the track itself, place the knob
     * so that its center is directly under the pointer.
     */
    dragX.value = Math.max(
        0,
        Math.min(
            pointerX -
                knobSize / 2 -
                sliderPadding,
            maxDrag.value
        )
    )

    startPointerX = event.clientX
    startDragX = dragX.value
}

function onDrag(event: PointerEvent) {
    if (
        !isDragging.value ||
        isTranscriptFinished.value ||
        activePointerId !== event.pointerId
    ) {
        return
    }

    const delta =
        event.clientX - startPointerX

    const next =
        startDragX + delta

    dragX.value = Math.max(
        0,
        Math.min(
            next,
            maxDrag.value
        )
    )
}

function endDrag(event?: PointerEvent) {
    if (!isDragging.value) {
        return
    }

    if (
        event &&
        activePointerId !== null &&
        event.pointerId !== activePointerId
    ) {
        return
    }

    isDragging.value = false
    activePointerId = null

    const threshold =
        maxDrag.value * 0.75

    if (dragX.value >= threshold) {
        dragX.value = maxDrag.value

        openEmail()
    } else {
        dragX.value = 0
    }
}

/*
|--------------------------------------------------------------------------
| Transcript
|--------------------------------------------------------------------------
*/

function startClock() {
    timer = window.setInterval(() => {
        if (!isTranscriptFinished.value) {
            seconds.value++
        } else if (timer) {
            clearInterval(timer)
        }
    }, 1000)
}

function startTranscript() {
    const tick = () => {
        if (
            visibleWordCount.value <
            transcriptWords.value.length
        ) {
            visibleWordCount.value++

            const nextDelay =
                120 + Math.random() * 180

            transcriptTimer =
                window.setTimeout(
                    tick,
                    nextDelay
                )
        }
    }

    tick()
}

onMounted(() => {
    startClock()
    startTranscript()
})

onUnmounted(() => {
    if (timer) {
        clearInterval(timer)
    }

    if (transcriptTimer) {
        clearTimeout(transcriptTimer)
    }
})
</script>

<template>
    <main
        class="py-5 flex flex-col gap-20"
        data-theme="dark"
    >
        <!--
        |--------------------------------------------------------------------------
        | CALL / TRANSCRIPT
        |--------------------------------------------------------------------------
        -->

        <section
            class="flex flex-col gap-10 h-[calc(100vh-150px)] w-full max-w-[400px] mx-auto px-6"
        >
            <div
                class="flex items-center gap-4"
            >
                <div
                    class="bg-accent rounded-full w-16 h-16 flex items-center justify-center"
                >
                    <img
                        :src="callerSrc"
                        alt="Title image"
                        class="w-full h-full object-cover rounded-full"
                    />
                </div>

                <div
                    class="flex flex-col p-3"
                >
                    <p class="p text-light">
                        {{ callStatus }}
                    </p>

                    <h3
                        class="h3 uppercase text-light"
                    >
                        {{ t('contactPage.title') }}
                    </h3>
                </div>
            </div>

            <div
                class="relative flex-1 min-h-0 overflow-hidden"
            >
                <div
                    class="absolute inset-x-0 top-0 h-[25rem] z-10 pointer-events-none transcript-fade"
                ></div>

                <div
                    class="h-full flex items-end overflow-hidden"
                >
                    <p
                        class="p text-light whitespace-pre-wrap leading-7"
                    >
                        {{ visibleTranscript }}
                    </p>
                </div>
            </div>

            <!--
            |--------------------------------------------------------------------------
            | EMAIL SLIDER
            |--------------------------------------------------------------------------
            -->

            <div class="px-8">
                <div
                    ref="slider"
                    class="relative h-12 bg-accent rounded-full select-none shrink-0 pickup-track"
                    :class="{
                        'pickup-track-dragging':
                            isDragging,
                        'pickup-track-disabled':
                            isTranscriptFinished,
                    }"
                    @pointerdown="startDrag"
                    @pointermove="onDrag"
                    @pointerup="endDrag"
                    @pointercancel="endDrag"
                >
                    <div
                        class="absolute inset-0 flex items-center justify-center pointer-events-none"
                    >
                        <p
                            class="p text-dark transition-opacity duration-300"
                            :class="
                                isDragging
                                    ? 'opacity-60'
                                    : 'opacity-100'
                            "
                        >
                            {{
                                t(
                                    'contactPage.dragToCall'
                                )
                            }}
                        </p>
                    </div>

                    <div
                        class="absolute top-1 left-1 w-10 h-10 z-20 pointer-events-none"
                        :class="{
                            'transition-none':
                                isDragging,
                        }"
                        :style="{
                            transform: `translateX(${dragX}px)`,
                        }"
                    >
                        <div
                            class="w-10 h-10 rounded-full bg-dark flex items-center justify-center pickup-knob-inner"
                            :class="{
                                'pickup-knob-inner-animated':
                                    !isDragging,
                            }"
                        >
                            <i
                                class="bi bi-arrow-up-right text-accent"
                            ></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!--
        |--------------------------------------------------------------------------
        | TOP INFO
        |--------------------------------------------------------------------------
        -->

        <section data-theme="dark">
            <Info
                v-for="(item, index) in topItems"
                :key="`top-${index}`"
                :heading="item.heading"
                :text="item.text"
                :color="item.color"
            />
        </section>

        <!--
        |--------------------------------------------------------------------------
        | OTHER CONTACT OPTIONS
        |--------------------------------------------------------------------------
        -->

        <section
            class="space-y-4 px-6"
            data-theme="dark"
        >
            <Button
                :text="t('contact.email')"
                variant="light"
                @click="openEmail"
            />

            <Button
                :text="t('contact.instagram')"
                variant="light"
                @click="openInstagram"
            />
        </section>

        <!--
        |--------------------------------------------------------------------------
        | BOTTOM INFO
        |--------------------------------------------------------------------------
        -->

        <section data-theme="dark">
            <Info
                v-for="(item, index) in bottomItems"
                :key="`bottom-${index}`"
                :heading="item.heading"
                :text="item.text"
                :color="item.color"
            />
        </section>
    </main>
</template>

<style>
/*
|--------------------------------------------------------------------------
| Transcript fade
|--------------------------------------------------------------------------
*/

.transcript-fade {
    background: linear-gradient(
        to bottom,
        rgba(0, 0, 0, 1) 20%,
        rgba(0, 0, 0, 0.92) 40%,
        rgba(0, 0, 0, 0.55) 70%,
        rgba(0, 0, 0, 0) 90%
    );
}

/*
|--------------------------------------------------------------------------
| Pickup slider
|--------------------------------------------------------------------------
*/

.pickup-track {
    touch-action: none;
    cursor: grab;
}

.pickup-track:active,
.pickup-track-dragging {
    cursor: grabbing;
}

.pickup-track-disabled {
    cursor: default;
}

/*
|--------------------------------------------------------------------------
| Pickup knob
|--------------------------------------------------------------------------
*/

.pickup-knob-inner {
    will-change: transform;
}

.pickup-knob-inner-animated {
    animation:
        pickupKnobNudge
        5s
        cubic-bezier(0.22, 1, 0.36, 1)
        infinite;
}

@keyframes pickupKnobNudge {
    0% {
        transform: translateX(0);
    }

    20% {
        transform: translateX(10px);
    }

    40% {
        transform: translateX(0);
    }

    60% {
        transform: translateX(10px);
    }

    80% {
        transform: translateX(0);
    }

    100% {
        transform: translateX(0);
    }
}
</style>
