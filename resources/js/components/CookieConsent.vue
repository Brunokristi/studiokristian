<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { disableAnalytics, enableAnalytics, trackPageViewIfConsented } from '../composables/useAnalytics';
import { getCookieConsent, setCookieConsent } from '../composables/useCookieConsent';

const { t } = useI18n();
const router = useRouter();
const consent = ref(getCookieConsent());

const isVisible = computed(() => consent.value === null);

function acceptCookies() {
  setCookieConsent('accepted');
  consent.value = 'accepted';
  enableAnalytics();
  trackPageViewIfConsented();
}

function rejectCookies() {
  setCookieConsent('rejected');
  consent.value = 'rejected';
  disableAnalytics();
}

function openPrivacyPage() {
  router.push({ name: 'privacy-policy' });
}
</script>

<template>
  <Transition name="cookie-slide">
    <aside
      v-if="isVisible"
      class="fixed bottom-4 left-4 right-4 z-[120] bg-dark text-light border border-accent/40 rounded-xl p-4 md:p-5 shadow-[0_18px_50px_rgba(0,0,0,0.45)]"
      data-theme="dark"
      role="dialog"
      aria-live="polite"
      :aria-label="t('cookies.title')"
    >
      <div class="mx-auto max-w-4xl flex flex-col gap-3 md:gap-4">
        <h3 class="h3 text-light">{{ t('cookies.title') }}</h3>
        <p class="p text-light/90">
          {{ t('cookies.text') }}
          <button
            type="button"
            class="underline underline-offset-2 text-accent hover:text-light transition-colors duration-200 ml-1"
            @click="openPrivacyPage"
          >
            {{ t('cookies.learnMore') }}
          </button>
        </p>

        <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
          <button
            type="button"
            class="px-4 py-2 rounded-md border border-light/35 text-light hover:bg-light/10 transition-colors duration-200 font-mono text-xs uppercase tracking-wide"
            @click="rejectCookies"
          >
            {{ t('cookies.reject') }}
          </button>

          <button
            type="button"
            class="px-4 py-2 rounded-md bg-accent text-light hover:brightness-110 transition-all duration-200 font-mono text-xs uppercase tracking-wide"
            @click="acceptCookies"
          >
            {{ t('cookies.accept') }}
          </button>
        </div>
      </div>
    </aside>
  </Transition>
</template>

<style scoped>
.cookie-slide-enter-active,
.cookie-slide-leave-active {
  transition: all 240ms cubic-bezier(0.22, 1, 0.36, 1);
}

.cookie-slide-enter-from,
.cookie-slide-leave-to {
  opacity: 0;
  transform: translateY(14px);
}
</style>
