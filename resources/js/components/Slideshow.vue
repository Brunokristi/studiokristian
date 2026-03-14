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
const slideDirection = ref('next')
const isLightboxOpen = ref(false)
let timer = null

const currentImage = computed(() => props.images[currentIndex.value] || null)
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

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="flex justify-center">
    <div class="flex items-center gap-3">
      
      <button v-if="showArrows && images.length > 1" class="z-10 text-dark cursor-pointer" @click="prev" aria-label="Previous slide">
        <i class="bi bi-arrow-left"></i>
      </button>

      <div class="relative w-[250px] h-[350px] overflow-hidden">
        <div class="relative h-full">
          <transition :name="transitionName">
            <div
              v-if="currentImage"
              :key="currentIndex"
              class="absolute inset-0"
            >
              <img
                :src="currentImage.src"
                :alt="currentImage.alt || ''"
                class="h-full w-full object-cover"
              />

              <button
                class="absolute bottom-3 right-3 z-20 text-light cursor-pointer"
                aria-label="Open image fullscreen"
                @click="openLightbox()"
              >
                <i class="bi bi-arrows-angle-expand text-dark"></i>
              </button>
            </div>
          </transition>
        </div>
      </div>

      <button
        v-if="showArrows && images.length > 1"
        class="z-10 text-dark cursor-pointer" 
        @click="next"
        aria-label="Next slide"
      >
        <i class="bi bi-arrow-right"></i>
      </button>
    </div>
  </div>

    <teleport to="body">
      <transition name="fade">
        <div v-if="isLightboxOpen" class="fixed inset-0 z-[999] flex flex-col items-center justify-center bg-dark p-6">
            <div class="relative flex flex-col w-full max-w-[600px]">
              <div class="relative flex min-h-0 h-[400px] w-full flex-col overflow-hidden">
                <transition :name="transitionName">
                  <div
                    v-if="currentImage"
                    :key="`lightbox-${currentIndex}`"
                    class="absolute inset-0"
                  >
                    <img
                      :src="currentImage.src"
                      :alt="currentImage.alt || ''"
                      class="w-full h-full object-cover"
                    />
                    
                    <button
                      class="absolute bottom-3 right-3 z-20 text-light cursor-pointer"
                      aria-label="Close lightbox"
                      @click="closeLightbox()"
                    >
                      <i class="bi bi-arrows-angle-contract text-accent"></i>
                  </button>
                  </div>
                </transition>
                </div>

                <div class="flex justify-between w-full mt-2">
                    <button v-if="images.length > 1" class="flex h-12 w-12 cursor-pointer" aria-label="Previous image" @click="prev">
                        <i class="bi bi-arrow-left text-accent"></i>
                    </button>

                    <button v-if="images.length > 1" class="flex h-12 w-12 cursor-pointer justify-end" aria-label="Next image" @click="next">
                        <i class="bi bi-arrow-right text-accent"></i>
                    </button>
                </div>

                <p class="p uppercase text-accent text-center">{{ currentImage?.caption || currentImage?.alt || '' }}</p>
            </div>
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
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
  transition: transform 0.35s ease;
}

.slide-left-enter-active,
.slide-right-enter-active {
  z-index: 2;
}

.slide-left-leave-active,
.slide-right-leave-active {
  z-index: 1;
}

.slide-left-enter-from {
  transform: translateX(100%);
}

.slide-left-leave-to {
  transform: translateX(0);
}

.slide-right-enter-from {
  transform: translateX(-100%);
}

.slide-right-leave-to {
  transform: translateX(0);
}
</style>