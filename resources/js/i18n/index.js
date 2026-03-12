import { createI18n } from 'vue-i18n';
import en from './messages/en';
import sk from './messages/sk';

const supportedLocales = ['en', 'sk'];

const browserLocale = (navigator.language || 'en').slice(0, 2).toLowerCase();
const savedLocale = typeof window !== 'undefined' ? window.localStorage.getItem('locale') : null;
const locale = supportedLocales.includes(savedLocale)
    ? savedLocale
    : (supportedLocales.includes(browserLocale) ? browserLocale : 'en');

const i18n = createI18n({
    legacy: false,
    locale,
    fallbackLocale: 'en',
    messages: {
        en,
        sk,
    },
});

export default i18n;
