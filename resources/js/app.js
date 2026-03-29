import './bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import i18n from './i18n';
import { initializeAnalyticsIfConsented, trackPageViewIfConsented } from './composables/useAnalytics';

initializeAnalyticsIfConsented();

router.afterEach((to) => {
    trackPageViewIfConsented(to.fullPath);
});

createApp(App).use(router).use(i18n).mount('#app');
