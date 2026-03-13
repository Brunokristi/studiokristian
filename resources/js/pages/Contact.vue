<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Button from '../components/Button.vue'

const { t } = useI18n()
const router = useRouter()
const logoSrc = '/assets/logo_white.svg'

const seconds = ref(0)
let timer: number | undefined

const transcriptSource = `
Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec vel sapien eget nunc efficitur efficitur.
Sed at felis a enim efficitur convallis. Curabitur ac ligula id mi commodo efficitur.
Donec in nunc sed enim efficitur fermentum. Proin ut odio a metus efficitur tincidunt.
Donec sed nisl a enim efficitur fermentum. Donec sed nisl a enim efficitur fermentum.
Vivamus gravida tortor sit amet augue ultrices, nec dapibus ligula vulputate.
`

const transcriptWords = transcriptSource.trim().split(/\s+/)
const visibleWordCount = ref(0)
let transcriptTimer: number | undefined

const isTranscriptFinished = computed(() => {
  return visibleWordCount.value >= transcriptWords.length
})

const visibleTranscript = computed(() => {
  return transcriptWords.slice(0, visibleWordCount.value).join(' ')
})

const timeFormatted = computed(() => {
  const mins = Math.floor(seconds.value / 60)
  const secs = seconds.value % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
})

const slider = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const dragX = ref(0)
const startX = ref(0)
const startDragX = ref(0)
const completed = ref(false)

const knobSize = 60

const maxDrag = computed(() => {
  if (!slider.value) return 0
  return slider.value.offsetWidth - knobSize - 8
})

function startDrag(event: PointerEvent) {
  if (completed.value || isTranscriptFinished.value) return

  isDragging.value = true
  startX.value = event.clientX
  startDragX.value = dragX.value
  ;(event.target as HTMLElement)?.setPointerCapture?.(event.pointerId)
}

function onDrag(event: PointerEvent) {
  if (!isDragging.value || completed.value || isTranscriptFinished.value) return

  const delta = event.clientX - startX.value
  const next = startDragX.value + delta
  dragX.value = Math.max(0, Math.min(next, maxDrag.value))
}

function endDrag() {
  if (!isDragging.value) return
  isDragging.value = false

  const threshold = maxDrag.value * 0.9

  if (dragX.value >= threshold) {
    dragX.value = maxDrag.value
    completed.value = true
    makeCall()
  } else {
    dragX.value = 0
  }
}

function makeCall() {
  console.log('Calling...')
}

function callBack() {
  window.location.href = 'tel:+421123456789'
}

function sendMessage() {
  window.location.href = 'sms:+421123456789'
}

function sendEmail() {
  window.location.href = 'mailto:hello@studiokristian.com'
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
    if (visibleWordCount.value < transcriptWords.length) {
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
  <main
    class="py-5 px-6 flex flex-col gap-10 h-[calc(100vh-3.5rem)] max-w-xl"
    data-theme="dark"
  >
    <div class="flex items-center gap-6">
      <div class="bg-accent rounded-full w-16 h-16 flex items-center justify-center">
        <img :src="logoSrc" alt="Title image" class="w-10" />
      </div>

      <div class="flex flex-col p-3">
        <p class="p text-light">{{ isTranscriptFinished ? `Zmeškaný hovor (${timeFormatted})` : timeFormatted }}</p>
        <h3 class="h3 uppercase text-light">Centrála SK</h3>
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

    <div v-if="!isTranscriptFinished">
      <div
        ref="slider"
        class="relative h-18 bg-accent rounded-full overflow-hidden select-none shrink-0"
        @pointermove="onDrag"
        @pointerup="endDrag"
        @pointercancel="endDrag"
        @pointerleave="endDrag"
      >
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
          <p class="p text-dark">
            {{ completed ? 'Volanie...' : 'Potiahnutím zavolať' }}
          </p>
        </div>

        <div
          class="absolute top-1.5 left-2 w-[60px] h-[60px] rounded-full bg-dark flex items-center justify-center cursor-grab active:cursor-grabbing transition-transform duration-200"
          :class="{ 'transition-none': isDragging }"
          :style="{ transform: `translateX(${dragX}px)` }"
          @pointerdown="startDrag"
        >
          <i
            class="bi bi-telephone text-accent"
          ></i>
        </div>
      </div>
    </div>

    <div v-else class="flex justify-between">
        <button
            class="h-10 w-10 rounded-full border border-accent text-accent flex items-center justify-center hover:bg-accent hover:text-dark"
            @click="sendMessage"
        >
            <i class="bi bi-chat"></i>
        </button>

        <button
            class="h-10 w-10 rounded-full border border-accent text-accent flex items-center justify-center  "
            @click="sendMessage"
        >
            <i class="bi bi-whatsapp"></i>
        </button>

        <button
                class="h-10 w-10 rounded-full border border-accent text-accent flex items-center justify-center  "
                @click="callBack"
            >
                <i class="bi bi-telephone"></i>
        </button>

        <button
            class="h-10 w-10 rounded-full border border-accent text-accent flex items-center justify-center  "
            @click="sendEmail"
        >
            <i class="bi bi-envelope"></i>
        </button>
    </div>
  </main>
</template>

<style>
.transcript-fade {
  background: linear-gradient(
    to bottom,
    rgba(0, 0, 0, 1) 40%,
    rgba(0, 0, 0, 0.92) 50%,
    rgba(0, 0, 0, 0.55) 70%,
    rgba(0, 0, 0, 0) 100%
  );
}
</style>