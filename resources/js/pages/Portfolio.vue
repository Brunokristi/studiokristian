<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

import Button from '../components/Button.vue';
import GridLayout from '../components/GridLayout.vue';

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
const isLoading = ref(true);

async function loadProjects() {
  isLoading.value = true;

  try {
    const response = await fetch('/api/projects');
    if (!response.ok) {
      throw new Error('Failed to load projects');
    }

    const projects: ApiProject[] = await response.json();
    cards.value = projects.map((project) => ({
      heading: project.name,
      text: project.summary || 'Web solutions that perform, convert and scale.',
      image: project.logo_path || project.cover_image || '',
      bgColor: project.hex_color || '',
      link: `/portfolio/${project.url}`,
    }));
  } catch (error) {
    console.error(error);
    cards.value = [];
  } finally {
    isLoading.value = false;
  }
}

const openRecentProjects = () => {
  console.log(t('home.recentProjectsClicked'));
};

onMounted(() => {
  loadProjects();
});

</script>

<template>
    <main class="py-5 flex flex-col gap-20" data-theme="light">
      <p v-if="isLoading" class="p">Loading projects...</p>
      <GridLayout v-else :cards="cards" />
      <Button
        :text="t('home.recentProjects')"
        variant="dark"
        @click="openRecentProjects"
        />
    </main>
</template>


