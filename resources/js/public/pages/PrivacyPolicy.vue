<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
  <main class="py-10 px-4 md:px-8">
    <section class="mx-auto w-full max-w-3xl flex flex-col gap-8" data-theme="light">
      <header class="flex flex-col gap-3 text-center md:text-left">
        <h1 class="h2 text-accent">{{ t('privacy.title') }}</h1>
        <p class="p text-center">{{ t('privacy.lastUpdated') }}</p>
      </header>

      <div class="flex flex-col gap-6">
        <section class="flex flex-col gap-2">
          <h2 class="h3">{{ t('privacy.sectionOverviewTitle') }}</h2>
          <p class="p">{{ t('privacy.sectionOverviewText') }}</p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="h3">{{ t('privacy.sectionCookiesTitle') }}</h2>
          <p class="p">{{ t('privacy.sectionCookiesText') }}</p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="h3">{{ t('privacy.sectionAnalyticsTitle') }}</h2>
          <p class="p">{{ t('privacy.sectionAnalyticsText') }}</p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="h3">{{ t('privacy.sectionControlTitle') }}</h2>
          <p class="p">{{ t('privacy.sectionControlText') }}</p>
        </section>

        <section class="flex flex-col gap-2">
          <h2 class="h3">{{ t('privacy.sectionContactTitle') }}</h2>
          <p class="p">{{ t('privacy.sectionContactText') }}</p>
        </section>
      </div>
    </section>
  </main>
</template>
