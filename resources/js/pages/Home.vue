<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';

import { useI18n } from 'vue-i18n';
const { t, locale } = useI18n();

import Button from '../components/Button.vue';
import GridLayout from '../components/GridLayout.vue';
import BusinessCard from '../components/BusinessCard.vue';

import { useGlobalActions } from '../composables/useGlobalActions';
const { openContacts, openRecentProjects, openWorkflow, openVcard } = useGlobalActions();

const cardFront = '/assets/card_front.svg';
const cardBack = '/assets/card_back.svg';
const bgUrl = '/assets/bg.svg'
const bgUrl2 = '/assets/bg2.svg'

type ApiProject = {
  name: string;
  url: string;
  summary: string | null;
  hex_color: string | null;
  logo_path: string | null;
  cover_image: string | null;
};

const cards = ref<Array<{
  heading: string;
  text: string;
  image: string;
  bgColor: string;
  link: string;
}>>([]);

async function loadRecentProjects() {
  try {
    const response = await fetch(`/api/projects?locale=${encodeURIComponent(locale.value)}`);
    if (!response.ok) {
      throw new Error('Failed to load recent projects');
    }

    const projects: ApiProject[] = await response.json();
    cards.value = projects.slice(0, 4).map((project) => ({
      heading: project.name,
      text: project.summary || 'Web solutions that perform, convert and scale.',
      image: project.logo_path || project.cover_image || '',
      bgColor: project.hex_color || '',
      link: `/portfolio/${project.url}`,
    }));
  } catch (error) {
    console.error(error);
    cards.value = [];
  }
}

onMounted(() => {
  loadRecentProjects();
});

watch(
  () => locale.value,
  () => {
    loadRecentProjects();
  }
);

</script>

<template>
    <main class="py-5 flex flex-col gap-30">
      <section class="relative overflow-hidden flex justify-center h-[500px]" data-theme="dark">
        <img
          :src="bgUrl"
          alt="Wave background"
          class="block max-w-none h-auto"
        />

        <div class="absolute inset-0 z-10 flex flex-col items-center justify-center text-center gap-4 p-4">
          <h2 class="h2 text-light">{{ t('home.title') }}</h2>
          <p class="p uppercase text-light">{{ t('home.description') }}</p>
          <Button
            :text="t('home.callToAction')"
            variant="light"
            @click="openContacts"
            class="mt-12"
          />
        </div>
      </section>

      <section class="gap-4 flex flex-col p-4" data-theme="light">
        <h2 class="h2 text-accent">{{ t('home.subtitle1') }}</h2>
        <p class="p uppercase text-center">{{ t('home.description1') }}</p>
      </section>

      <section class="flex flex-col gap-4" data-theme="light">
        <GridLayout :cards="cards" />
        <Button :text="t('home.recentProjects')" @click="openRecentProjects"/>
      </section>

      <section class="relative overflow-hidden flex justify-center" data-theme="dark">
        <img
          :src="bgUrl2"
          alt="Dark blue and black gradient background"
          class="block max-w-none h-auto"
        />

        <div class="absolute inset-0 z-10 flex flex-col items-center justify-center text-center gap-4 p-4">
          <h2 class="h2 text-light">{{ t('home.subtitle2') }}</h2>
          <p class="p uppercase text-light">{{ t('home.description2') }}</p>
          <Button
            :text="t('home.workflow')"
            variant="light"
            @click="openWorkflow"
            class="mt-12"
          />
        </div>
      </section>

      <section class="gap-4 flex flex-col p-4" data-theme="light">
        <h2 class="h2 text-accent">{{ t('home.quote') }}</h2>
      </section>

      <section class="flex flex-col gap-6 p-4" data-theme="light">
          <Button :text="t('home.contact')" @click="openContacts" />

          <BusinessCard
              :front-src="cardFront"
              :back-src="cardBack"
          />
          <Button :text="t('home.vcard')" @click="openVcard" />
      </section>
    </main>
</template>
