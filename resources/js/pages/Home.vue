<script setup lang="ts">
import { useI18n } from 'vue-i18n';

import Button from '../components/Button.vue';
import GridLayout from '../components/GridLayout.vue';
import BusinessCard from '../components/BusinessCard.vue';

const cardFront = '/assets/card_front.svg';
const cardBack = '/assets/card_front.svg';
const kaq_image = '/assets/kaq_logo.svg';

const { t } = useI18n();

const openRecentProjects = () => {
  console.log(t('home.recentProjectsClicked'));
};

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
];

</script>

<template>
    <main class="px-4 sm:px-6 lg:px-8 py-12">
        <h1 class="h1 text-dark">{{ t('home.title') }}</h1>
        <p class="p uppercase">{{ t('home.description') }}</p>
        <h2 class="h2">{{ t('home.title') }}</h2>
        <h3 class="h3">{{ t('home.title') }}</h3>
        <p class="p">{{ t('home.description') }}</p>

        <GridLayout :cards="cards" />

        <BusinessCard
            :front-src="cardFront"
            :back-src="cardBack"
        />

        <Button :text="t('home.recentProjects')" @click="openRecentProjects" />



    </main>
</template>
