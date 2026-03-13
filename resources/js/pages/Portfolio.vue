<script setup lang="ts">
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

import Button from '../components/Button.vue';
import GridLayout from '../components/GridLayout.vue';

const kaq_image = '/assets/kaq_logo.svg';

const cards = [
  {
    heading: 'Case Study',
    text: 'Web solutions that perform, convert and scale.',
    image: '',
    link: '/case-study-1',
  },
  {
    heading: 'Case Study',
    text: 'Web solutions that perform, convert and scale.',
    image: '',
    link: '/case-study-2',
  },
  {
    heading: 'Case Study',
    text: 'Web solutions that perform, convert and scale.',
    image: '',
    link: '/case-study-3',
  },
  {
    heading: 'Case Study',
    text: 'Web solutions that perform, convert and scale.',
    bgColor: 'bg-[#4E6F3D]',
    image: kaq_image,
    link: '/case-study-4',
  },
  {
    heading: 'Case Study',
    text: 'Web solutions that perform, convert and scale.',
    image: '',
    link: '/case-study-3',
  },
  {
    heading: 'Case Study',
    text: 'Web solutions that perform, convert and scale.',
    bgColor: 'bg-[#4E6F3D]',
    image: kaq_image,
    link: '/case-study-4',
  },
];

const openRecentProjects = () => {
  console.log(t('home.recentProjectsClicked'));
};

</script>

<template>
    <main class="py-5 flex flex-col gap-20" data-theme="light">
      <GridLayout :cards="cards" />
      <Button
        :text="t('home.recentProjects')"
        variant="dark"
        @click="openRecentProjects"
        />
    </main>
</template>


