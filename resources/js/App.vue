<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import Navbar from './components/Navbar.vue';
import Footer from './components/Footer.vue';
import BottomNav from './components/BottomNav.vue';
import CookieConsent from './components/CookieConsent.vue';

const route = useRoute();
const theme = computed(() => route.meta.theme ?? 'dark');
const footer = computed(() => route.meta.footer ?? true);

const backgroundColor = computed(() => theme.value === 'light' ? 'bg-light' : 'bg-dark');

const LOADER_DURATION_MS = 2000;
const REVEAL_START_MS = 2100;
const LOADER_SHOWN_SESSION_KEY = 'studio-kristian-loader-shown';

const isPageLoading = ref(true);
const isRevealing = ref(false);

let loaderTimeoutId = null;
let revealTimeoutId = null;

function clearLoaderTimers() {
    if (loaderTimeoutId) {
        window.clearTimeout(loaderTimeoutId);
        loaderTimeoutId = null;
    }

    if (revealTimeoutId) {
        window.clearTimeout(revealTimeoutId);
        revealTimeoutId = null;
    }
}

function startLoader() {
    clearLoaderTimers();

    isPageLoading.value = true;
    isRevealing.value = false;

    revealTimeoutId = window.setTimeout(() => {
        isRevealing.value = true;
        revealTimeoutId = null;
    }, REVEAL_START_MS);

    loaderTimeoutId = window.setTimeout(() => {
        isPageLoading.value = false;
        isRevealing.value = false;
        loaderTimeoutId = null;
    }, LOADER_DURATION_MS);
}

function updateLoaderForRoute() {
    const hasShownLoader = sessionStorage.getItem(LOADER_SHOWN_SESSION_KEY) === '1';

    if (hasShownLoader) {
        clearLoaderTimers();
        isPageLoading.value = false;
        isRevealing.value = false;
        return;
    }

    startLoader();
    sessionStorage.setItem(LOADER_SHOWN_SESSION_KEY, '1');
}

onMounted(() => {
    updateLoaderForRoute();
});

onBeforeUnmount(() => {
    clearLoaderTimers();
});
</script>

<template>
    <div :class="[backgroundColor, 'app-shell']">
        <!-- Page is always rendered underneath -->
        <div
            class="page-shell"
            :class="{ 'page-shell--visible': isRevealing || !isPageLoading }"
        >
            <div class="min-h-screen flex flex-col">
                <Navbar :variant="theme" />
                <main class="flex-1">
                    <RouterView />
                </main>
                <Footer v-if="footer" />
                <CookieConsent />
            </div>
        </div>

        <BottomNav />

        <!-- Loader overlay -->
        <transition name="loader-fade">
            <div
                v-if="isPageLoading"
                class="loader-screen"
                :class="{ 'loader-screen--reveal': isRevealing }"
            >
                <!-- Full overlay with animated hole -->
                <svg
                    class="loader-mask-svg"
                    viewBox="0 0 100 100"
                    preserveAspectRatio="xMidYMid slice"
                    aria-hidden="true"
                >
                    <defs>
                        <mask id="pageRevealMask" maskUnits="userSpaceOnUse" maskContentUnits="userSpaceOnUse">
                            <!-- white = visible overlay -->
                            <rect x="0" y="0" width="100" height="100" fill="white" />

                            <!-- black = transparent hole -->
                            <g class="loader-hole-group">
                                <path
                                    d="M15.80 0.015
                                       H51.2
                                       L40.6 8.5078
                                       L51.2 17
                                       H0.3
                                       C0.3 17 15.80 15.17 15.80 11.39
                                       C15.80 7.60 0.3 8.036 0.3 3.82
                                       C0.3 -0.40 15.80 0.015 15.80 0.015
                                       Z"
                                    fill="black"
                                />
                            </g>
                        </mask>

                        <radialGradient id="loaderVignette" cx="50%" cy="50%" r="65%">
                            <stop offset="0%" stop-color="rgba(0,0,0,0)" />
                            <stop offset="100%" stop-color="rgba(0,0,0,0.18)" />
                        </radialGradient>
                    </defs>

                    <rect
                        x="0"
                        y="0"
                        width="100"
                        height="100"
                        class="loader-overlay-fill"
                        mask="url(#pageRevealMask)"
                    />

                    <rect
                        x="0"
                        y="0"
                        width="100"
                        height="100"
                        fill="url(#loaderVignette)"
                        class="loader-vignette"
                        mask="url(#pageRevealMask)"
                    />
                </svg>

                <!-- Visible logo in the middle before reveal -->
                <svg
                    class="loader-logo"
                    width="220"
                    height="72"
                    viewBox="0 0 52 17"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >
                    <defs>
                        <linearGradient id="paint0_linear_87_99" x1="0" y1="8.5" x2="52" y2="8.5" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#133EB4" />
                            <stop offset="1" />
                        </linearGradient>
                    </defs>

                    <path fill="url(#paint0_linear_87_99)">
                        <animate
                            attributeName="d"
                            begin="0s"
                            dur="2s"
                            fill="freeze"
                            calcMode="linear"
                            keyTimes="0;0.10;0.22;0.36;0.52;0.68;0.84;1"
                            values="
                                M17.5 0
                                H34.5
                                L34.5 8.5
                                L34.5 17
                                H17.5
                                C17.5 17 17.5 15.1128 17.5 11.3385
                                C17.5 7.56423 17.5 8.036 17.5 3.78991
                                C17.5 -0.456182 17.5 0.0155963 17.5 0.0155963
                                Z;

                                M17.45 0.002
                                H35.4
                                L34.7 8.5
                                L35.4 16.998
                                H16.9
                                C16.9 16.998 17.45 15.10 17.45 11.34
                                C17.45 7.57 16.9 8.03 16.9 3.81
                                C16.9 -0.40 17.45 0.016 17.45 0.016
                                Z;

                                M17.15 0.004
                                H37.1
                                L35.1 8.5
                                L37.1 16.996
                                H15.4
                                C15.4 16.996 17.15 15.00 17.15 11.42
                                C17.15 7.72 15.4 8.05 15.4 3.98
                                C15.4 -0.24 17.15 0.018 17.15 0.018
                                Z;

                                M16.7 0.007
                                H39.9
                                L35.8 8.5
                                L39.9 16.998
                                H12.8
                                C12.8 16.998 16.7 15.02 16.7 11.55
                                C16.7 7.92 12.8 8.06 12.8 4.12
                                C12.8 -0.06 16.7 0.020 16.7 0.020
                                Z;

                                M16.25 0.010
                                H43.5
                                L36.9 8.5
                                L43.5 17
                                H8.6
                                C8.6 17 16.25 15.34 16.25 11.57
                                C16.25 7.84 8.6 8.04 8.6 3.99
                                C8.6 -0.10 16.25 0.016 16.25 0.016
                                Z;

                                M15.98 0.012
                                H47.0
                                L38.3 8.5078
                                L47.0 17
                                H4.5
                                C4.5 17 15.98 15.24 15.98 11.49
                                C15.98 7.72 4.5 8.035 4.5 3.92
                                C4.5 -0.20 15.98 0.014 15.98 0.014
                                Z;

                                M15.86 0.014
                                H49.6
                                L39.5 8.5078
                                L49.6 17
                                H1.5
                                C1.5 17 15.86 15.19 15.86 11.43
                                C15.86 7.64 1.5 8.036 1.5 3.86
                                C1.5 -0.31 15.86 0.015 15.86 0.015
                                Z;

                                M15.80 0.015
                                H51.2
                                L40.6 8.5078
                                L51.2 17
                                H0.3
                                C0.3 17 15.80 15.17 15.80 11.39
                                C15.80 7.60 0.3 8.036 0.3 3.82
                                C0.3 -0.40 15.80 0.015 15.80 0.015
                                Z
                            "
                        />
                    </path>
                </svg>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.app-shell {
    position: relative;
    min-height: 100vh;
}

.page-shell {
    min-height: 100vh;
    opacity: 0;
    transform: scale(1.02);
    filter: blur(10px);
    transition:
        opacity 0.9s ease,
        transform 1.2s cubic-bezier(0.22, 1, 0.36, 1),
        filter 1.2s ease;
}

.page-shell--visible {
    opacity: 1;
    transform: none;
    filter: none;
}

.loader-screen {
    position: fixed;
    inset: 0;
    z-index: 9999;
    pointer-events: none;
    overflow: hidden;
    background: transparent;
}

.loader-mask-svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    display: block;
}

.loader-overlay-fill {
    fill: #f5f5f5;
    opacity: 1;
    transition: opacity 0.7s ease;
}

.loader-vignette {
    opacity: 0.65;
    transition: opacity 0.7s ease;
}

.loader-logo {
    position: absolute;
    top: 50%;
    left: 50%;
    width: min(220px, 46vw);
    height: auto;
    display: block;
    transform: translate(-50%, -50%) scale(1);
    transform-origin: center center;
    will-change: transform, opacity, filter;
    filter: drop-shadow(0 0 28px rgba(19, 62, 180, 0.10));
    transition:
        opacity 0.65s ease,
        transform 1s cubic-bezier(0.16, 1, 0.3, 1),
        filter 0.8s ease;
}

/*
The path itself is about 50.9 x 17.4.
This translation centers it inside the 100 x 100 mask viewBox.
*/
.loader-hole-group {
    transform-box: fill-box;
    transform-origin: center;
    transform: translate(24.25px, 41.7px) scale(0.08);
    will-change: transform;
}

.loader-screen--reveal .loader-logo {
    opacity: 0;
    transform: translate(-50%, -50%) scale(1.25);
    filter: blur(8px) drop-shadow(0 0 28px rgba(19, 62, 180, 0));
}

.loader-screen--reveal .loader-hole-group {
    animation: loaderHoleExpand 1.05s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.loader-screen--reveal .loader-overlay-fill {
    opacity: 0.96;
}

.loader-screen--reveal .loader-vignette {
    opacity: 0.35;
}

.loader-fade-enter-active,
.loader-fade-leave-active {
    transition: opacity 0.65s ease;
}

.loader-fade-enter-from,
.loader-fade-leave-to {
    opacity: 0;
}

@keyframes loaderHoleExpand {
    0% {
        transform: translate(24.25px, 41.7px) scale(0.08);
    }
    100% {
        transform: translate(24.25px, 41.7px) scale(18);
    }
}
</style>