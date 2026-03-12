<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  frontSrc: {
    type: String,
    required: true,
  },
  backSrc: {
    type: String,
    required: true,
  },
  altFront: {
    type: String,
    default: 'Business card front',
  },
  altBack: {
    type: String,
    default: 'Business card back',
  },
})

const cardRef = ref(null)

const rotationX = ref(0)
const rotationY = ref(0)
const idleX = ref(0)
const idleY = ref(0)
const baseFlip = ref(0)
const isDragging = ref(false)
const hasInteracted = ref(false)

let startX = 0
let startY = 0
let currentX = 0
let currentY = 0

let animationFrameId = null
let startTime = 0

const maxTilt = 18
const flipThreshold = 90

const cardTransform = computed(() => {
  const totalX = rotationX.value + idleX.value
  const totalY = baseFlip.value + rotationY.value + idleY.value

  return `
    rotateX(${totalX}deg)
    rotateY(${totalY}deg)
  `
})

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max)
}

function getPoint(event) {
  if (event.touches && event.touches[0]) {
    return {
      x: event.touches[0].clientX,
      y: event.touches[0].clientY,
    }
  }

  return {
    x: event.clientX,
    y: event.clientY,
  }
}

function startIdleAnimation() {
  function animate(timestamp) {
    if (!startTime) startTime = timestamp

    const t = (timestamp - startTime) / 1000

    if (!isDragging.value && !hasInteracted.value) {
      idleX.value = Math.sin(t * 1.5) * 2.5
      idleY.value = Math.cos(t * 1.5) * 3.5
    } else {
      idleX.value *= 0.9
      idleY.value *= 0.9

      if (Math.abs(idleX.value) < 0.01) idleX.value = 0
      if (Math.abs(idleY.value) < 0.01) idleY.value = 0
    }

    animationFrameId = requestAnimationFrame(animate)
  }

  animationFrameId = requestAnimationFrame(animate)
}

function stopIdleAnimation() {
  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId)
    animationFrameId = null
  }
}

function startDrag(event) {
  const point = getPoint(event)

  hasInteracted.value = true
  isDragging.value = true
  startX = point.x
  startY = point.y
  currentX = point.x
  currentY = point.y
}

function moveDrag(event) {
  if (!isDragging.value) return

  const point = getPoint(event)
  currentX = point.x
  currentY = point.y

  const deltaX = currentX - startX
  const deltaY = currentY - startY

  rotationY.value = clamp(deltaX * 0.25, -maxTilt, maxTilt)
  rotationX.value = clamp(-deltaY * 0.25, -maxTilt, maxTilt)
}

function endDrag() {
  if (!isDragging.value) return

  const draggedFarEnough =
    rotationY.value > flipThreshold / 10 || rotationY.value < -flipThreshold / 10

  if (draggedFarEnough) {
    baseFlip.value = baseFlip.value === 0 ? 180 : 0
  }

  isDragging.value = false
  rotationX.value = 0
  rotationY.value = 0
}

function toggleFlip() {
  hasInteracted.value = true
  if (isDragging.value) return
  baseFlip.value = baseFlip.value === 0 ? 180 : 0
}

onMounted(() => {
  startIdleAnimation()
})

onUnmounted(() => {
  stopIdleAnimation()
})
</script>

<template>
    <div class="w-full flex justify-center">
        <div class="business-card-scene">
            
            <div
            ref="cardRef"
            class="business-card"
            :class="{ dragging: isDragging }"
            :style="{ transform: cardTransform }"
            @mousedown="startDrag"
            @mousemove="moveDrag"
            @mouseup="endDrag"
            @mouseleave="endDrag"
            @touchstart.passive="startDrag"
            @touchmove.passive="moveDrag"
            @touchend="endDrag"
            @click="toggleFlip"
            >
            <div class="business-card__face business-card__face--front">
                <img :src="frontSrc" :alt="altFront" draggable="false" />
            </div>

            <div class="business-card__face business-card__face--back">
                <img :src="backSrc" :alt="altBack" draggable="false" />
            </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.business-card-scene {
  width: 250px;
  height: 350px;
  perspective: 1400px;
  display: grid;
  place-items: center;
  user-select: none;
}

.business-card {
  position: relative;
  width: 100%;
  height: 100%;
  transform-style: preserve-3d;
  transition:
    transform 420ms cubic-bezier(0.22, 1, 0.36, 1),
    box-shadow 250ms ease;
  cursor: grab;
  will-change: transform;
}

.business-card.dragging {
  transition: none;
  cursor: grabbing;
}

.business-card__face {
  position: absolute;
  inset: 0;
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  overflow: hidden;
  transform-style: preserve-3d;
  box-shadow:
    0 18px 40px rgba(0, 0, 0, 0.18),
    0 4px 12px rgba(0, 0, 0, 0.1);
}

.business-card__face img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  pointer-events: none;
}

.business-card__face--front {
  transform: rotateY(0deg) translateZ(1px);
}

.business-card__face--back {
  transform: rotateY(180deg) translateZ(1px);
}
</style>