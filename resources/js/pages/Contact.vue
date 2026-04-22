<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import Button from '../components/Button.vue'
import Info from '../components/Info.vue'

import { useI18n } from 'vue-i18n'
const { t, tm } = useI18n()
import { useSeoMeta } from '../composables/useSeoMeta'

import { useGlobalActions } from '../composables/useGlobalActions'
const { openEmail, openMessage, openWhatsApp, openInstagram, openMessenger } = useGlobalActions()

useSeoMeta({
    title: () => t('seo.contact.title'),
    description: () => t('seo.contact.description'),
})

const logoSrc = '/assets/logo_white.svg'
const seconds = ref(0)

const transcriptSource = computed(() => t('contactPage.transcript'))

let timer: number | undefined

type ContactInfoItem = {
    heading: string
    text: string
}

const items = computed(() => {
    const localizedItems = tm('contactPage.items') as ContactInfoItem[]
    return localizedItems.map((item) => ({
        heading: item.heading,
        text: item.text,
        color: 'light',
    }))
})

const topItems = computed(() => items.value.slice(0, 3))
const bottomItems = computed(() => items.value.slice(3))

const transcriptWords = computed(() => transcriptSource.value.trim().split(/\s+/))
const visibleWordCount = ref(0)
let transcriptTimer: number | undefined

const isTranscriptFinished = computed(() => {
    return visibleWordCount.value >= transcriptWords.value.length
})

const visibleTranscript = computed(() => {
    return transcriptWords.value.slice(0, visibleWordCount.value).join(' ')
})

const timeFormatted = computed(() => {
    const mins = Math.floor(seconds.value / 60)
    const secs = seconds.value % 60
    return `${mins}:${secs.toString().padStart(2, '0')}`
})

const callStatus = computed(() => {
    return isTranscriptFinished.value
        ? t('contactPage.callEnded', { time: timeFormatted.value })
        : timeFormatted.value
})

const slider = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const dragX = ref(0)
const startX = ref(0)
const startDragX = ref(0)
const isCalling = ref(false)

const knobSize = 40

const maxDrag = computed(() => {
    if (!slider.value) return 0
    return slider.value.offsetWidth - knobSize - 8
})

function startDrag(event: PointerEvent) {
    if (isCalling.value || isTranscriptFinished.value) return

    isDragging.value = true
    startX.value = event.clientX
    startDragX.value = dragX.value
    ;(event.currentTarget as HTMLElement)?.setPointerCapture?.(event.pointerId)
}

function onDrag(event: PointerEvent) {
    if (!isDragging.value || isCalling.value || isTranscriptFinished.value) return

    const delta = event.clientX - startX.value
    const next = startDragX.value + delta
    dragX.value = Math.max(0, Math.min(next, maxDrag.value))
}

function endDrag() {
    if (!isDragging.value) return
    isDragging.value = false

    const threshold = maxDrag.value * 0.75

    if (dragX.value >= threshold) {
        dragX.value = maxDrag.value
        makeCall()
    } else {
        dragX.value = 0
    }
}

function makeCall() {
    isCalling.value = true
    openWhatsApp()
}

function hangUp() {
    isCalling.value = false
    dragX.value = 0
}

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
        if (visibleWordCount.value < transcriptWords.value.length) {
            visibleWordCount.value++
            const nextDelay = 120 + Math.random() * 180
            transcriptTimer = window.setTimeout(tick, nextDelay)
        }
    }

    tick()
}

onMounted(() => {
    startClock()
    startTranscript()
})

onUnmounted(() => {
    if (timer) clearInterval(timer)
    if (transcriptTimer) clearTimeout(transcriptTimer)
})
</script>

<template>
    <main class="py-5 flex flex-col gap-20" data-theme="dark">
        <section class="flex flex-col gap-10 h-[calc(100vh-150px)] w-full max-w-[400px] mx-auto px-6">
            <div class="flex items-center gap-4">
                <div class="bg-accent rounded-full w-16 h-16 flex items-center justify-center">
                    <img :src="logoSrc" alt="Title image" class="w-10" />
                </div>

                <div class="flex flex-col p-3">
                    <p class="p text-light">
                        {{ callStatus }}
                    </p>

                    <h3 class="h3 uppercase text-light">{{ t('contactPage.title') }}</h3>
                </div>
            </div>

            <div class="relative flex-1 min-h-0 overflow-hidden">
                <div class="absolute inset-x-0 top-0 h-[25rem] z-10 pointer-events-none transcript-fade"></div>

                <div class="h-full flex items-end overflow-hidden">
                    <p class="p text-light whitespace-pre-wrap leading-7">
                        {{ visibleTranscript }}
                    </p>
                </div>
            </div>

            <div v-if="!isCalling" class="px-8">
                <div
                    ref="slider"
                    class="relative h-12 bg-accent rounded-full overflow-hidden select-none shrink-0 pickup-track"
                    @pointermove="onDrag"
                    @pointerup="endDrag"
                    @pointercancel="endDrag"
                    @pointerleave="endDrag"
                >
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <p
                            class="p text-dark transition-opacity duration-300"
                            :class="isDragging ? 'opacity-60' : 'opacity-100'"
                        >
                            {{ t('contactPage.dragToCall') }}
                        </p>
                    </div>

                    <div
                        class="absolute top-1 left-1 w-10 h-10 cursor-grab active:cursor-grabbing transition-transform duration-200 z-20"
                        :class="{ 'transition-none': isDragging }"
                        :style="{ transform: `translateX(${dragX}px)` }"
                        @pointerdown="startDrag"
                    >
                        <div
                            class="w-10 h-10 rounded-full bg-dark flex items-center justify-center pickup-knob-inner"
                            :class="{ 'pickup-knob-inner-animated': !isDragging }"
                        >
                            <i class="bi bi-telephone text-accent"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col items-center gap-4">
                <button
                    class="h-12 w-12 rounded-full bg-accent text-dark flex items-center justify-center cursor-pointer"
                    @click="hangUp"
                >
                    <i class="bi bi-telephone rotate-[135deg]"></i>
                </button>
            </div>
        </section>

        <section data-theme="dark">
            <Info
                v-for="(item, index) in topItems"
                :key="`top-${index}`"
                :heading="item.heading"
                :text="item.text"
                :color="item.color"
            />
        </section>

        <section class="space-y-4 px-6" data-theme="dark">
            <Button
                :text="t('contact.email')"
                variant="light"
                @click="openEmail"
            />

            <Button
                :text="t('contact.whatsapp')"
                variant="light"
                @click="openWhatsApp"
            />

            <Button
                :text="t('contact.message')"
                variant="light"
                @click="openMessage"
            />
            <Button
                :text="t('contact.instagram')"
                variant="light"
                @click="openInstagram"
            />
            <Button
                :text="t('contact.messenger')"
                variant="light"
                @click="openMessenger"
            />


        </section>

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
.transcript-fade {
    background: linear-gradient(
        to bottom,
        rgba(0, 0, 0, 1) 20%,
        rgba(0, 0, 0, 0.92) 40%,
        rgba(0, 0, 0, 0.55) 70%,
        rgba(0, 0, 0, 0) 90%
    );
}

.pickup-knob-inner-animated {
    animation: pickupKnobNudge 5s cubic-bezier(0.22, 1, 0.36, 1) infinite;
}

@keyframes pickupKnobNudge {
    0% {
        transform: translateX(0);
    }
    20% {
        transform: translateX(10px);
    }
    40% {
        transform: translateX(0px);
    }
    60% {
        transform: translateX(10px);
    }
    80% {
        transform: translateX(0px);
    }
    100% {
        transform: translateX(0px);
    }
}
</style>