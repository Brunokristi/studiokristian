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

function applyLocaleToDocument(locale) {
    document.documentElement.lang = locale
}

export function useClientLocale() {
    const locale = ref(initialLocale())

    applyLocaleToDocument(locale.value)

    const isEnglish = computed(() => locale.value === 'en')

    function setLocale(nextLocale) {
        if (!supportedLocales.includes(nextLocale)) {
            return
        }

        locale.value = nextLocale
        window.localStorage.setItem('locale', nextLocale)
        applyLocaleToDocument(nextLocale)
    }

    function toggleLocale() {
        setLocale(locale.value === 'en' ? 'sk' : 'en')
    }

    return {
        locale,
        isEnglish,
        setLocale,
        toggleLocale,
    }
}
