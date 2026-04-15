<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
const { t, locale } = useI18n();
const route = useRoute();
import { useSeoMeta } from '../composables/useSeoMeta';

import Button from '../components/Button.vue';
import Slideshow from '../components/Slideshow.vue';
import Info from '../components/Info.vue';

type ApiProject = {
  name: string;
  url: string;
  summary: string | null;
  images: Array<{ path: string; description: string | null }>;
  features: Array<{ title: string; description: string }>;
  live_url?: string | null;
};

const projectName = ref('');
const projectSummary = ref('');
const images = ref<{ src: string; alt: string; caption: string }[]>([]);
const items = ref<{ heading: string; text: string }[]>([]);
const isLoading = ref(true);
const liveUrl = ref<string | null>(null);

function preloadImage(src: string): Promise<void> {
  return new Promise((resolve) => {
    const img = new Image();

    img.onload = () => resolve();
    img.onerror = () => resolve();
    img.src = src;

    if (img.complete) {
      resolve();
    }
  });
}

async function preloadImages(sources: string[]) {
  await Promise.all(sources.map((src) => preloadImage(src)));
}

useSeoMeta({
  title: () => {
    const name = projectName.value.trim();
    return name ? `${name} | Studio Kristian` : t('seo.project.title');
  },
  description: () => {
    const summary = projectSummary.value.trim();
    return summary || t('seo.project.description');
  },
});

async function loadProject() {
  const url = route.params.url;
  if (typeof url !== 'string' || !url) {
    return;
  }

  isLoading.value = true;

  try {
    const response = await fetch(`/api/projects/${url}?locale=${encodeURIComponent(locale.value)}`);
    if (!response.ok) {
      throw new Error('Failed to load project');
    }

    const project: ApiProject = await response.json();
    const mappedImages = project.images.map((image, index) => ({
      src: image.path,
      alt: image.description || `Project image ${index + 1}`,
      caption: image.description || `Image ${index + 1}`,
    }));

    await preloadImages(mappedImages.map((image) => image.src));

    projectName.value = project.name;
    projectSummary.value = project.summary || '';
    images.value = mappedImages;
    items.value = project.features.map((feature) => ({
      heading: feature.title,
      text: feature.description,
    }));
    liveUrl.value = project.live_url || null;
  } catch (error) {
    console.error(error);
    projectName.value = 'Project not found';
    projectSummary.value = '';
    images.value = [];
    items.value = [];
  } finally {
    isLoading.value = false;
  }
}

const openRecentProjects = () => {
  if (liveUrl.value) {
    window.open(liveUrl.value, '_blank');
  } else {
    console.log(t('home.recentProjectsClicked'));
  }
};

onMounted(() => {
  loadProject();
});

watch(
  () => route.params.url,
  () => {
    loadProject();
  }
);

watch(
  () => locale.value,
  () => {
    loadProject();
  }
);

</script>

<template>
    <main class="py-5 flex flex-col gap-20">
        <p v-if="isLoading" class="p">Loading project...</p>

        <template v-else>
        <Slideshow
            :images="images"
            :autoplay="true"
            :interval="5000"
        />

        <h2 class="h2 text-accent">{{ projectName }}</h2>

        <div>
            <Info
                v-for="(item, index) in items"
                :key="index"
                :heading="item.heading"
                :text="item.text"
            />
        </div>

        <Button
            :text="t('home.recentProjects')"
            variant="dark"
            @click="openRecentProjects"
        />
        </template>
    </main>
</template>


