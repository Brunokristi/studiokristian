<script setup>
const bgUrl = '/assets/bg.svg'

const layers = [
  { id: 1, duration: '26s', delay: '0s', opacity: 0.22, y: '-8px', scale: 1.02 },
  { id: 2, duration: '32s', delay: '-6s', opacity: 0.16, y: '8px', scale: 0.98 },
  { id: 3, duration: '38s', delay: '-12s', opacity: 0.12, y: '0px', scale: 1 },
]
</script>

<template>
  <div class="wave-bg" aria-hidden="true">
    <div
      v-for="layer in layers"
      :key="layer.id"
      class="wave-layer"
      :style="{
        '--duration': layer.duration,
        '--delay': layer.delay,
        '--layer-opacity': layer.opacity,
        '--offset-y': layer.y,
        '--scale': layer.scale,
      }"
    >
      <div class="wave-track">
        <img class="wave-tile" :src="bgUrl" alt="" />
        <img class="wave-tile" :src="bgUrl" alt="" />
        <img class="wave-tile" :src="bgUrl" alt="" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.wave-bg {
  position: absolute;
  inset: 0;
  height: 100%;
  overflow: hidden;
  pointer-events: none;
  z-index: 0;
}

.wave-layer {
  position: absolute;
  inset: 0;
  opacity: var(--layer-opacity);
  transform: translateY(var(--offset-y)) scale(var(--scale));
}

.wave-track {
  display: flex;
  width: max-content;
  height: 100%;
  animation: drift var(--duration) linear infinite;
  animation-delay: var(--delay);
  will-change: transform;
}

.wave-tile {
  display: block;
  height: 100%;
  width: auto;
  flex: 0 0 auto;
  user-select: none;
  -webkit-user-drag: none;
}

@keyframes drift {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-33.3333%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wave-track {
    animation: none;
  }
}
</style>