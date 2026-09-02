<script setup>
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

import { useGlobalActions } from '@public/composables/useGlobalActions';

const {
    openContacts,
    openRecentProjects,
    openWorkflow,
    openPrivacyPolicy,
    openInstagram,
    openMessenger
} = useGlobalActions();

const logoSrc = '/assets/logo_white.svg';
const date = new Date().getFullYear();
</script>

<template>
    <footer
        class="w-full px-8 sm:px-16 md:px-24 lg:px-30 pt-20 md:pt-28 pb-10 flex flex-col bg-gradient-to-b from-transparent from-0% via-accent via-70% to-black to-100% mt-20"
        data-theme="dark"
    >
        <div class="w-full max-w-[1600px] mx-auto">

            <!-- Main footer -->
            <div class="flex flex-col items-center justify-center gap-16">

                <!-- Desktop: Brand | Navigation -->
                <!-- Mobile: Brand / Navigation stacked -->
                <div
                    class="grid grid-cols-1 md:grid-cols-2 w-full items-center"
                >

                    <!-- Brand -->
                    <div
                        class="flex justify-center md:justify-end md:pr-16"
                    >
                        <a
                            href="/"
                            class="flex flex-col gap-2 items-center text-light w-fit"
                        >
                            <h1 class="h1">
                                studio
                            </h1>

                            <img
                                :src="logoSrc"
                                alt="Studio Kristian Logo"
                                class="h-10 w-auto"
                            />

                            <h1 class="h1">
                                kristian
                            </h1>
                        </a>
                    </div>

                    <!-- Navigation -->
                    <div
                        class="relative mt-16 md:mt-0 md:pl-16 w-full"
                    >

                        <!-- Desktop Center Divider -->
                        <div
                            class="hidden md:block absolute left-0 top-1/2 -translate-y-1/2 w-px h-16 bg-light"
                        ></div>

                        <!-- Navigation columns -->
                        <div
                            class="grid grid-cols-1 sm:grid-cols-3 md:flex md:flex-col gap-8 md:gap-2 items-center md:items-start"
                        >

                            <!-- Explore -->
                            <div
                                class="flex flex-col md:flex-row gap-2 md:gap-4 items-center md:items-baseline"
                            >
                                <p class="p text-light uppercase">
                                    Explore
                                </p>

                                <button
                                    @click="openRecentProjects"
                                    class="text-light hover:text-accent transition-colors duration-300 text-center md:text-left w-fit"
                                >
                                    <p class="p underline">
                                        {{ t('footer.portfolio') }}
                                    </p>
                                </button>

                                <button
                                    @click="openWorkflow"
                                    class="text-light hover:text-accent transition-colors duration-300 text-center md:text-left w-fit"
                                >
                                    <p class="p underline">
                                        {{ t('footer.workflow') }}
                                    </p>
                                </button>
                            </div>

                            <!-- Connect -->
                            <div
                                class="flex flex-col md:flex-row gap-2 md:gap-4 items-center md:items-baseline"
                            >
                                <p class="p text-light uppercase">
                                    Connect
                                </p>

                                <button
                                    @click="openContacts"
                                    class="text-light hover:text-accent transition-colors duration-300 text-center md:text-left w-fit"
                                >
                                    <p class="p underline">
                                        {{ t('footer.contact') }}
                                    </p>
                                </button>

                                <button
                                    @click="openInstagram"
                                    class="text-light hover:text-accent transition-colors duration-300 text-center md:text-left w-fit"
                                >
                                    <p class="p underline">
                                        {{ t('footer.instagram') }}
                                    </p>
                                </button>

                                <button
                                    @click="openMessenger"
                                    class="text-light hover:text-accent transition-colors duration-300 text-center md:text-left w-fit"
                                >
                                    <p class="p underline">
                                        {{ t('footer.facebook') }}
                                    </p>
                                </button>
                            </div>

                            <!-- Legal -->
                            <div
                                class="flex flex-col md:flex-row gap-2 md:gap-4 items-center md:items-baseline"
                            >
                                <p class="p text-light uppercase">
                                    Legal
                                </p>

                                <button
                                    @click="openPrivacyPolicy"
                                    class="text-light hover:text-accent transition-colors duration-300 text-center md:text-left w-fit"
                                >
                                    <p class="p underline">
                                        {{ t('footer.privacy') }}
                                    </p>
                                </button>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- Footer Information -->
                <p class="p text-light uppercase text-center pb-20">
                    © {{ t('footer.company') }} {{ date }} {{ t('footer.copyright') }}
                </p>

            </div>
        </div>
    </footer>
</template>