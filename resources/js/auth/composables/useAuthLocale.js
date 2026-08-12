import { computed, ref } from 'vue'

const supportedLocales = ['en', 'sk']

function initialLocale() {
    const savedLocale = window.localStorage.getItem('locale')
    const browserLocale = (window.navigator.language || 'en').slice(0, 2).toLowerCase()

    if (supportedLocales.includes(savedLocale)) {
        return savedLocale
    }

    return supportedLocales.includes(browserLocale) ? browserLocale : 'en'
}

export function useAuthLocale(messages) {
    const locale = ref(initialLocale())
    const copy = computed(() => messages[locale.value])

    document.documentElement.lang = locale.value

    function toggleLocale() {
        locale.value = locale.value === 'en' ? 'sk' : 'en'
        window.localStorage.setItem('locale', locale.value)
        document.documentElement.lang = locale.value
    }

    return { copy, locale, toggleLocale }
}