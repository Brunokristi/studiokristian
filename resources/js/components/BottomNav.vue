<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'

const isDarkBackground = ref(false)
const { locale } = useI18n({ useScope: 'global' })

let observer

const toggleLanguage = () => {
  locale.value = locale.value === 'en' ? 'sk' : 'en'
  localStorage.setItem('locale', locale.value)
}

onMounted(() => {
  const sections = document.querySelectorAll('[data-theme]')

  observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const theme = entry.target.dataset.theme || 'light'
        isDarkBackground.value = theme === 'dark'
      }
    })
  }, {
    threshold: 0.6
  })

  sections.forEach(section => observer.observe(section))
})

onUnmounted(() => {
  if (observer) observer.disconnect()
})
</script>

<template>
  <nav
    class="flex flex-col fixed bottom-6 right-6 gap-2 transition-colors duration-500"
    :class="isDarkBackground ? 'text-white' : 'text-black'"
  >
    <button
      type="button"
      @click="toggleLanguage"
      :title="locale === 'en' ? 'Switch to Slovak' : 'Prepnut do anglictiny'"
      :aria-label="locale === 'en' ? 'Switch to Slovak' : 'Prepnut do anglictiny'"
    >
      <i class="bi bi-translate"></i>
    </button>
    <a href="#"><i class="bi bi-list"></i></a>
  </nav>
</template>