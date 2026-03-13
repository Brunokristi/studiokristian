<script setup lang="ts">
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

import Button from '../components/Button.vue';
import GridLayout from '../components/GridLayout.vue';
import BusinessCard from '../components/BusinessCard.vue';


const cardFront = '/assets/card_front.svg';
const cardBack = '/assets/card_front.svg';
const kaq_image = '/assets/kaq_logo.svg';
const img1 = '/assets/img1.jpg';
const img2 = '/assets/card_front.svg';
const img3 = '/assets/card_front.svg';
const bgUrl = '/assets/tilted_bg.svg'



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



const items = [
  {
    heading: 'What services do you offer?',
    text: 'I create visual identities, websites and digital experiences.',
  },
  {
    heading: 'Do you work internationally?',
    text: 'Yes, I work with clients across different countries and time zones.',
  },
  {
    heading: 'How can we start?',
    text: 'Send me a message with your project goals, timeline and scope.',
  },
]

</script>

<template>
    <main class="py-5 flex flex-col gap-20">
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
            :text="t('home.recentProjects')"
            variant="light"
            @click="openRecentProjects"
            class="mt-12"
          />
        </div>
      </section>


      <section class="gap-4 flex flex-col p-4" data-theme="light">
        <h2 class="h2 text-accent">{{ t('home.title') }}</h2>
        <p class="p uppercase text-center">{{ t('home.description') }}</p>
      </section>

      <section class="flex flex-col gap-4" data-theme="light">
        <GridLayout :cards="cards" />
        <Button :text="t('home.recentProjects')" @click="openRecentProjects"/>
      </section>

      <section class="relative overflow-hidden flex justify-center" data-theme="dark">
        <img
          :src="bgUrl"
          alt="Wave background"
          class="block max-w-none h-auto"
        />

        <div class="absolute inset-0 z-10 flex flex-col items-center justify-center text-center gap-4 p-4">
          <h2 class="h2 text-light">{{ t('home.title') }}</h2>
          <p class="p uppercase text-light">{{ t('home.description') }}</p>
          <Button
            :text="t('home.recentProjects')"
            variant="light"
            @click="openRecentProjects"
            class="mt-12"
          />
        </div>
      </section>

      <section class="gap-4 flex flex-col p-4" data-theme="light">
        <h2 class="h2 text-accent">{{ t('home.title') }}</h2>
      </section>

      <section class="flex flex-col gap-4" data-theme="light">
        <GridLayout :cards="cards" />
        <Button :text="t('home.recentProjects')" @click="openRecentProjects" class="mb-1" />
      </section>

      <section class="flex flex-col gap-4 p-4" data-theme="light">
        <div>
          <BusinessCard
              :front-src="cardFront"
              :back-src="cardBack"
          />
        </div>
        <Button :text="t('home.recentProjects')" @click="openRecentProjects" />
      </section>


        

        
    </main>
</template>
