import { createApp, watch } from 'vue';
import PublicLayout from './layouts/PublicLayout.vue';
import router from './router';
import i18n from './i18n';
import { initializeAnalyticsIfConsented, trackPageViewIfConsented } from './composables/useAnalytics';

const app = createApp(PublicLayout);
app.use(router);
app.use(i18n);
app.mount('#app');

document.documentElement.lang = i18n.global.locale.value;

watch(i18n.global.locale, (locale) => {
    document.documentElement.lang = locale;
});

initializeAnalyticsIfConsented();
trackPageViewIfConsented();

router.afterEach((to) => {
    trackPageViewIfConsented(to.fullPath);
});
