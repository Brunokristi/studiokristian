import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import i18n from './i18n';
import { initializeAnalyticsIfConsented, trackPageViewIfConsented } from './composables/useAnalytics';

const app = createApp(App);
app.use(router);
app.use(i18n);
app.mount('#app');

initializeAnalyticsIfConsented();
trackPageViewIfConsented();

router.afterEach((to) => {
    trackPageViewIfConsented(to.fullPath);
});
