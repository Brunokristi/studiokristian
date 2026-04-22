<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    images: {
        type: Array,
        default: () => [],
    },
    interval: {
        type: Number,
        default: 4000,
    },
    showArrows: {
        type: Boolean,
        default: true,
    },
})

const currentIndex = ref(0)
const displayIndex = ref(0)
const slideDirection = ref('next')
const isLightboxOpen = ref(false)
let timer = null

const currentImage = computed(() => props.images[currentIndex.value] || null)
const displayImage = computed(() => props.images[displayIndex.value] || null)

const transitionName = computed(() =>
    slideDirection.value === 'prev' ? 'slide-right' : 'slide-left'
)

function next() {
    if (!props.images.length) return
    slideDirection.value = 'next'
    currentIndex.value = (currentIndex.value + 1) % props.images.length
}

function prev() {
    if (!props.images.length) return
    slideDirection.value = 'prev'
    currentIndex.value =
        (currentIndex.value - 1 + props.images.length) % props.images.length
}

function openLightbox(index = currentIndex.value) {
    currentIndex.value = index
    displayIndex.value = index
    isLightboxOpen.value = true
    document.body.style.overflow = 'hidden'
}

function closeLightbox() {
    isLightboxOpen.value = false
    document.body.style.overflow = ''
}

function handleKeydown(event) {
    if (!isLightboxOpen.value) return

    if (event.key === 'Escape') closeLightbox()
    if (event.key === 'ArrowRight') next()
    if (event.key === 'ArrowLeft') prev()
}

function syncDisplayImage() {
    displayIndex.value = currentIndex.value
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
    document.body.style.overflow = ''
})
</script>

<template>
    <div class="flex justify-center flex-col items-center gap-2">
        <div class="flex items-center gap-3">
            <button
                v-if="showArrows && images.length > 1"
                class="z-10 cursor-pointer text-dark"
                @click="prev"
                aria-label="Previous slide"
            >
                <i class="bi bi-arrow-left"></i>
            </button>

            <div class="relative h-[350px] w-[250px] overflow-hidden">
                <div class="relative h-full w-full">
                    <!-- bottom image -->
                    <img
                        v-if="displayImage"
                        :src="displayImage.src"
                        :alt="displayImage.alt || ''"
                        class="absolute inset-0 h-full w-full object-cover"
                    />

                    <!-- top animated image -->
                    <transition :name="transitionName" @after-enter="syncDisplayImage">
                        <div
                            v-if="currentImage && currentIndex !== displayIndex"
                            :key="currentIndex"
                            class="absolute inset-0 z-[2]"
                        >
                            <img
                                :src="currentImage.src"
                                :alt="currentImage.alt || ''"
                                class="h-full w-full object-cover"
                            />
                        </div>
                    </transition>

                    <!-- initial / synced state -->
                    <div
                        v-if="currentImage && currentIndex === displayIndex"
                        class="absolute inset-0 z-[2]"
                    >
                        <img
                            :src="currentImage.src"
                            :alt="currentImage.alt || ''"
                            class="h-full w-full object-cover"
                        />
                    </div>
                </div>
            </div>

            <button
                v-if="showArrows && images.length > 1"
                class="z-10 cursor-pointer text-dark"
                @click="next"
                aria-label="Next slide"
            >
                <i class="bi bi-arrow-right"></i>
            </button>
        </div>
        <button
                class="cursor-pointer text-dark"
                aria-label="Open image fullscreen"
                @click="openLightbox()"
            >
                <i class="bi bi-arrows-angle-expand adaptive-text"></i>
            </button>
    </div>
    

    <teleport to="body">
        <transition name="fade">
            <div
                v-if="isLightboxOpen"
                class="fixed inset-0 z-[999] flex items-center justify-center bg-dark p-6"
            >
                <div class="flex h-full w-full items-center justify-center">
                    <div class="relative w-full max-w-[95vw] md:max-w-[25vw]">
                        <div class="relative w-full overflow-hidden">
                            <!-- bottom image -->
                            <img
                                v-if="displayImage"
                                :src="displayImage.src"
                                :alt="displayImage.alt || ''"
                                class="block h-auto w-full object-contain"
                            />

                            <!-- top animated image -->
                            <transition :name="transitionName" @after-enter="syncDisplayImage">
                                <img
                                    v-if="currentImage && currentIndex !== displayIndex"
                                    :key="`lightbox-${currentIndex}`"
                                    :src="currentImage.src"
                                    :alt="currentImage.alt || ''"
                                    class="absolute inset-0 h-auto w-full object-contain"
                                />
                            </transition>

                            <!-- synced state -->
                            <img
                                v-if="currentImage && currentIndex === displayIndex"
                                :src="currentImage.src"
                                :alt="currentImage.alt || ''"
                                class="absolute inset-0 h-auto w-full object-contain"
                            />
                        </div>

                        <div class="mt-2 flex w-full items-center justify-between">
                            <button
                                v-if="images.length > 1"
                                class="flex h-12 w-12 cursor-pointer items-center justify-start"
                                aria-label="Previous image"
                                @click="prev"
                            >
                                <i class="bi bi-arrow-left text-accent"></i>
                            </button>

                            <div class="flex items-center justify-center h-full">
                                <p class="p text-center uppercase text-light">
                                    {{ currentImage?.caption || currentImage?.alt || '' }}
                                </p>
                            </div>

                            <button
                                v-if="images.length > 1"
                                class="flex h-12 w-12 cursor-pointer items-center justify-end"
                                aria-label="Next image"
                                @click="next"
                            >
                                <i class="bi bi-arrow-right text-accent"></i>
                            </button>
                        </div>

                        
                    </div>
                </div>

                <button
                    class="absolute right-6 top-6 cursor-pointer text-accent"
                    aria-label="Close lightbox"
                    @click="closeLightbox"
                >
                    <i class="bi bi-arrows-angle-contract"></i>
                </button>
            </div>
        </transition>
    </teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-left-enter-active,
.slide-right-enter-active {
    transition: transform 0.35s ease;
    z-index: 2;
}

.slide-left-leave-active,
.slide-right-leave-active {
    z-index: 1;
}

.slide-left-enter-from {
    transform: translateX(100%);
}

.slide-left-enter-to {
    transform: translateX(0);
}

.slide-right-enter-from {
    transform: translateX(-100%);
}

.slide-right-enter-to {
    transform: translateX(0);
}
</style>