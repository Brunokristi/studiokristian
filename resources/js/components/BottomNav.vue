<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import Toast from './Toast.vue'
import router from '../router'

const isDarkBackground = ref(false)
const { locale } = useI18n({ useScope: 'global' })
const route = useRoute()
const showToast = ref(false)
const toastHeading = ref('Language changed')
const toastText = ref('')
const isOnNavPage = computed(() => route.name === 'nav')
const returnToPath = computed(() => {
  const from = route.query.from
  return typeof from === 'string' && from ? from : '/'
})

let sections = []

const toggleLanguage = () => {
  locale.value = locale.value === 'en' ? 'sk' : 'en'
  localStorage.setItem('locale', locale.value)

  const languageName = locale.value === 'en' ? 'English' : 'Slovak'
  toastText.value = `Language changed to ${languageName}.`

  showToast.value = false
  nextTick(() => {
    showToast.value = true
  })
}

const updateThemeFromNavPosition = () => {
  const probeX = Math.max(window.innerWidth - 40, 0)
  const probeY = Math.max(window.innerHeight - 40, 0)

  // Prefer real stacking at the nav point, so fixed overlays do not break theme detection.
  const elementsAtPoint = document.elementsFromPoint(probeX, probeY)
  const sectionFromPoint = elementsAtPoint
    .map((element) => element.closest?.('[data-theme]'))
    .find(Boolean)

  if (sectionFromPoint) {
    const theme = sectionFromPoint.dataset.theme || 'light'
    isDarkBackground.value = theme === 'dark'
    return
  }

  sections = Array.from(document.querySelectorAll('[data-theme]'))
  const activeSection = sections.find((section) => {
    const rect = section.getBoundingClientRect()
    return rect.left <= probeX && rect.right >= probeX && rect.top <= probeY && rect.bottom >= probeY
  })

  const theme = activeSection?.dataset.theme || 'light'
  isDarkBackground.value = theme === 'dark'
}

function openNavigation() {
  if (isOnNavPage.value) {
    router.push(returnToPath.value)
    return
  }

  router.push({
    name: 'nav',
    query: { from: route.fullPath },
  })
}

onMounted(() => {
  sections = Array.from(document.querySelectorAll('[data-theme]'))
  nextTick(() => {
    requestAnimationFrame(updateThemeFromNavPosition)
  })

  window.addEventListener('scroll', updateThemeFromNavPosition, { passive: true })
  window.addEventListener('resize', updateThemeFromNavPosition)
})

watch(
  () => route.fullPath,
  () => {
    nextTick(() => {
      sections = Array.from(document.querySelectorAll('[data-theme]'))
      requestAnimationFrame(updateThemeFromNavPosition)
    })
  }
)

onUnmounted(() => {
  window.removeEventListener('scroll', updateThemeFromNavPosition)
  window.removeEventListener('resize', updateThemeFromNavPosition)
})
</script>

<template>
  <Toast
    v-model="showToast"
    :heading="toastHeading"
    :text="toastText"
    :duration="4000"
  />

  <nav
    class="flex flex-col fixed bottom-6 right-6 gap-2 transition-colors duration-500 z-100"
    :class="isDarkBackground ? 'text-white' : 'text-black'"
  >
    <button
      class="text-inherit"
      type="button"
      @click="toggleLanguage"
      :title="locale === 'en' ? 'Switch to Slovak' : 'Prepnut do anglictiny'"
      :aria-label="locale === 'en' ? 'Switch to Slovak' : 'Prepnut do anglictiny'"
    >
      <i class="bi bi-translate text-inherit"></i>
    </button>

    <button 
      class="text-inherit"
      type="button"
      @click="openNavigation"
      :title="isOnNavPage ? 'Close navigation' : 'Open navigation'"
      :aria-label="isOnNavPage ? 'Close navigation' : 'Open navigation'"
    >
    <i :class="isOnNavPage ? 'bi bi-x-lg text-inherit' : 'bi bi-list text-inherit'"></i>
    </button>

  </nav>
</template>