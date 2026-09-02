<script setup>
import {
    computed,
    onBeforeUnmount,
    ref
} from 'vue'


const props = defineProps({
    src: {
        type: String,
        default: ''
    },
})


const BAR_COUNT = 40
const MIN_BAR_HEIGHT = 8

const audioElement = ref(null)
const isPlaying = ref(false)
const currentTime = ref(0)
const duration = ref(0)
const barHeights = ref(
    Array.from(
        { length: BAR_COUNT },
        () => MIN_BAR_HEIGHT
    )
)

const bars = Array.from(
    { length: BAR_COUNT },
    (_, index) => index
)

let audioContext = null
let analyser = null
let frequencyData = null
let animationFrameId = null


const colorClass = computed(() => {
    return props.color === 'light'
        ? 'text-light'
        : 'text-dark'
})


function formatTime(seconds) {
    if (!Number.isFinite(seconds)) {
        return '0:00'
    }

    const mins = Math.floor(seconds / 60)
    const secs = Math.floor(seconds % 60)

    return `${mins}:${secs.toString().padStart(2, '0')}`
}


const currentTimeLabel = computed(() =>
    formatTime(currentTime.value)
)

const durationLabel = computed(() =>
    formatTime(duration.value)
)


function ensureAudioGraph() {
    if (
        audioContext ||
        !audioElement.value
    ) {
        return
    }

    const AudioContextClass =
        window.AudioContext ||
        window.webkitAudioContext

    if (!AudioContextClass) {
        return
    }

    audioContext = new AudioContextClass()

    analyser = audioContext.createAnalyser()
    analyser.fftSize = 128
    analyser.smoothingTimeConstant = 0.8

    frequencyData = new Uint8Array(
        analyser.frequencyBinCount
    )

    const source =
        audioContext.createMediaElementSource(
            audioElement.value
        )

    source.connect(analyser)
    analyser.connect(audioContext.destination)
}


function updateBars() {
    if (
        !analyser ||
        !frequencyData
    ) {
        return
    }

    analyser.getByteFrequencyData(
        frequencyData
    )

    barHeights.value = bars.map(bar => {
        const dataIndex =
            Math.floor(
                (bar / BAR_COUNT) *
                frequencyData.length
            )

        const value =
            frequencyData[dataIndex] ||
            0

        return Math.max(
            MIN_BAR_HEIGHT,
            Math.round(
                (value / 255) * 100
            )
        )
    })

    animationFrameId =
        requestAnimationFrame(updateBars)
}


function stopVisualizer() {
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId)
        animationFrameId = null
    }

    barHeights.value = bars.map(
        () => MIN_BAR_HEIGHT
    )
}


async function togglePlayback() {
    if (!audioElement.value) {
        return
    }

    if (isPlaying.value) {
        audioElement.value.pause()

        return
    }

    ensureAudioGraph()

    if (
        audioContext?.state ===
        'suspended'
    ) {
        await audioContext.resume()
    }

    audioElement.value.play()
}


function handlePlay() {
    isPlaying.value = true

    if (!animationFrameId) {
        updateBars()
    }
}


function handlePause() {
    isPlaying.value = false

    stopVisualizer()
}


function handleEnded() {
    isPlaying.value = false
    currentTime.value = 0

    stopVisualizer()
}


function handleTimeUpdate() {
    currentTime.value =
        audioElement.value?.currentTime || 0
}


function handleLoadedMetadata() {
    duration.value =
        audioElement.value?.duration || 0
}


onBeforeUnmount(() => {
    stopVisualizer()

    audioElement.value?.pause()

    audioContext?.close()
})
</script>


<template>
    <div
        class="
            flex
            items-center
            gap-4
            color-accent
            text-accent
            justify-center
        "
    >
        <audio
            ref="audioElement"
            :src="src"
            preload="metadata"
            @play="handlePlay"
            @pause="handlePause"
            @ended="handleEnded"
            @timeupdate="handleTimeUpdate"
            @loadedmetadata="handleLoadedMetadata"
        />

        <div
            class="
                flex
                min-w-0
                flex-col
                gap-1
                px-4
                py-2
            "
        >
            <div class="flex justify-center items-center gap-2">
                <div
                    class="
                        flex
                        h-[48px]
                        items-center
                        gap-[4px]
                    "
                >
                    <span
                        v-for="bar in bars"
                        :key="bar"
                        class="audio-bar"
                        :class="
                            isPlaying
                                ? 'audio-bar-active'
                                : ''
                        "
                        :style="{
                            height: `${barHeights[bar]}%`
                        }"
                    />
                </div>

                <p class="p text-[10px]">
                    {{ currentTimeLabel }} / {{ durationLabel }}
                </p>

                <button
                    type="button"
                    class="
                        flex
                        shrink-0
                        cursor-pointer
                        items-center
                        justify-center
                        transition-colors
                        duration-200
                        text-lg
                    "
                    :aria-label="
                        isPlaying ? 'Pause' : 'Play'
                    "
                    @click="togglePlayback"
                >
                    <i
                        class="bi"
                        :class="
                            isPlaying
                                ? 'bi-pause'
                                : 'bi-play-fill'
                        "
                    />
                </button>
            </div>


            
        </div>
    </div>
</template>


<style scoped>
.audio-bar {
    width: 1px;
    height: 8%;
    background: currentColor;
    opacity: 0.5;
    transition: height 80ms linear, opacity 0.2s ease;
}

.audio-bar-active {
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .audio-bar {
        transition: none;
    }
}
</style>
