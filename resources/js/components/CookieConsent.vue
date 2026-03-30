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
  <teleport to="body">
    <transition name="toast">
      <div
        v-if="isVisible"
        class="fixed bottom-6 inset-x-0 z-50 w-[70vw] mx-auto border border-dark bg-light px-4 py-4 shadow-lg"
        role="dialog"
        aria-live="polite"
        :aria-label="t('cookies.title')"
      >
        <button
          class="absolute right-3 top-3 text-dark cursor-pointer"
          aria-label="Close cookie consent"
          @click="rejectCookies"
        >
          <i class="bi bi-x-lg"></i>
        </button>
        <div class="">
          <h3 class="h3 mb-2">{{ t('cookies.title') }}</h3>
          <p class="p">
            {{ t('cookies.text') }}
          </p>

          <button
            type="button"
            class="p underline underline-offset-2 text-accent text-sm hover:text-dark transition-colors duration-200 mb-5"
            @click="openPrivacyPage"
        >
            {{ t('cookies.learnMore') }}
        </button>

          <div class="flex flex-col sm:flex-row gap-2">
            <button
              type="button"
              class="px-4 py-2 border border-dark text-dark hover:bg-dark/10 transition-colors duration-200 font-mono text-xs uppercase tracking-wide cursor-pointer"
              @click="rejectCookies"
            >
              {{ t('cookies.reject') }}
            </button>
            <button
              type="button"
              class="px-4 py-2 bg-accent text-light hover:brightness-110 transition-all duration-200 font-mono text-xs uppercase tracking-wide cursor-pointer"
              @click="acceptCookies"
            >
              {{ t('cookies.accept') }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(24px);
}

.toast-enter-to,
.toast-leave-from {
  opacity: 1;
  transform: translateY(0);
}
</style>
