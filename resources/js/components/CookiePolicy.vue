<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { disableAnalytics, enableAnalytics, trackPageViewIfConsented } from '../composables/useAnalytics';
import { getCookiePreferences, setCookiePreferences, type CookiePreferences } from '../composables/useCookieConsent';

const { t } = useI18n();

const isOpen = ref(false);
const preferences = ref<CookiePreferences>({
    necessary: true,
    analytics: false,
    marketing: false,
});
const originalPreferences = ref<CookiePreferences>({
    necessary: true,
    analytics: false,
    marketing: false,
});

const isModified = computed(() => {
    return JSON.stringify(preferences.value) !== JSON.stringify(originalPreferences.value);
});

function openModal() {
    isOpen.value = true;
    const saved = getCookiePreferences();
    if (saved) {
        originalPreferences.value = { ...saved };
        preferences.value = { ...saved };
    }
}

function closeModal() {
    isOpen.value = false;
}

function savePreferences() {
    setCookiePreferences(preferences.value);
    
    if (preferences.value.analytics) {
        enableAnalytics();
        trackPageViewIfConsented();
    } else {
        disableAnalytics();
    }
    
    closeModal();
}

function acceptAll() {
    preferences.value = {
        necessary: true,
        analytics: true,
        marketing: true,
    };
    savePreferences();
}

function rejectAll() {
    preferences.value = {
        necessary: true,
        analytics: false,
        marketing: false,
    };
    savePreferences();
}

defineExpose({
    openModal,
});
</script>

<template>
    <teleport to="body">
        <transition name="fade">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[1000] bg-black/50 flex items-center justify-center p-4"
                @click.self="closeModal"
            >
                <div
                    class="bg-light w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="t('cookies.policy.title')"
                >
                    <!-- Header -->
                    <div class="sticky top-0 z-10 bg-light px-6 py-4 flex justify-between items-center">
                        <h2 class="h2 text-accent">{{ t('cookies.policy.title') }}</h2>
                        <button
                            type="button"
                            class="text-dark cursor-pointer hover:opacity-60 transition-opacity"
                            aria-label="Close cookie policy"
                            @click="closeModal"
                        >
                            <i class="bi bi-x-lg text-lg"></i>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="px-6 py-8 space-y-6">
                        <!-- Necessary Cookies -->
                        <div class="bg-dark/2">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="h3">{{ t('cookies.policy.necessary') }}</h3>
                                    </div>
                                    <p class="p text-sm mb-3">{{ t('cookies.policy.necessaryDesc') }}</p>
                                </div>
                                <span class="text-xs font-mono text-accent uppercase tracking-wide">{{ t('cookies.policy.always') }}</span>
                            </div>
                            <div class="bg-dark/5 p-3">
                                <p class="p text-xs text-dark/70">{{ t('cookies.policy.necessaryList') }}</p>
                            </div>
                        </div>

                        <!-- Analytics Cookies -->
                        <div class="">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <div class="flex-1">
                                    <h3 class="h3 mb-2">{{ t('cookies.policy.analytics') }}</h3>
                                    <p class="p text-sm">{{ t('cookies.policy.analyticsDesc') }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="preferences.analytics = !preferences.analytics"
                                    :class="[
                                        'px-3 py-1 font-mono text-xs uppercase tracking-wide cursor-pointer transition-colors duration-200 mt-1',
                                        preferences.analytics
                                            ? 'bg-accent text-light'
                                            : 'bg-dark/10 text-dark'
                                    ]"
                                    :aria-label="`Analytics cookies ${preferences.analytics ? 'on' : 'off'}`"
                                >
                                    {{ preferences.analytics ? 'ON' : 'OFF' }}
                                </button>
                            </div>
                            <div class="bg-dark/5 p-3">
                                <p class="p text-xs text-dark/70">{{ t('cookies.policy.analyticsList') }}</p>
                            </div>
                        </div>

                        <!-- Marketing Cookies -->
                        <div class="">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <div class="flex-1">
                                    <h3 class="h3 mb-2">{{ t('cookies.policy.marketing') }}</h3>
                                    <p class="p text-sm">{{ t('cookies.policy.marketingDesc') }}</p>
                                </div>
                                <button
                                    type="button"
                                    @click="preferences.marketing = !preferences.marketing"
                                    :class="[
                                        'px-3 py-1 font-mono text-xs uppercase tracking-wide cursor-pointer transition-colors duration-200 mt-1',
                                        preferences.marketing
                                            ? 'bg-accent text-light'
                                            : 'bg-dark/10 text-dark'
                                    ]"
                                    :aria-label="`Marketing cookies ${preferences.marketing ? 'on' : 'off'}`"
                                >
                                    {{ preferences.marketing ? 'ON' : 'OFF' }}
                                </button>
                            </div>
                            <div class="bg-dark/5 p-3">
                                <p class="p text-xs text-dark/70">{{ t('cookies.policy.marketingList') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class=" bg-light/50 px-6 py-4 sticky bottom-0 flex flex-col sm:flex-row gap-3 justify-end">
                        <button
                            type="button"
                            class="px-4 py-2 border border-dark text-dark hover:bg-dark/10 transition-colors duration-200 font-mono text-xs uppercase tracking-wide cursor-pointer"
                            @click="rejectAll"
                        >
                            {{ t('cookies.policy.rejectAll') }}
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 border border-dark text-dark hover:bg-dark/10 transition-colors duration-200 font-mono text-xs uppercase tracking-wide cursor-pointer"
                            @click="closeModal"
                        >
                            {{ t('cookies.policy.cancel') }}
                        </button>
                        <button
                            type="button"
                            :class="[
                                'px-4 py-2 font-mono text-xs uppercase tracking-wide cursor-pointer transition-all duration-200',
                                isModified
                                    ? 'bg-accent text-light hover:brightness-110'
                                    : 'bg-dark/20 text-dark/50 cursor-not-allowed'
                            ]"
                            :disabled="!isModified"
                            @click="savePreferences"
                        >
                            {{ t('cookies.policy.save') }}
                        </button>
                        <button
                            type="button"
                            class="px-4 py-2 bg-accent text-light hover:brightness-110 transition-all duration-200 font-mono text-xs uppercase tracking-wide cursor-pointer"
                            @click="acceptAll"
                        >
                            {{ t('cookies.policy.acceptAll') }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
