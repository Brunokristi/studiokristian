<script setup lang="ts">
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

import Button from '../components/Button.vue';
import Slideshow from '../components/Slideshow.vue';
import Info from '../components/Info.vue';

const img1 = '/assets/img1.jpg';
const img2 = '/assets/card_front.svg';
const img3 = '/assets/card_front.svg';
const images = [
  {
    src: img1,
    alt: 'Project preview one',
    caption: 'Project one',
  },
  {
    src: img2,
    alt: 'Project preview two',
    caption: 'Project two',
  },
  {
    src: img3,
    alt: 'Project preview three',
    caption: 'Project three',
  },
]
const items = [
  {
    heading: 'What services do you offer?',
    text: 'I create visual identities, websites and digital experiences.',
  },
  {
    heading: 'Do you work internationally?',
    text: 'Yes, I work with clients from all around the world.',
  },
  {
    heading: 'What is your design process?',
    text: 'My design process is collaborative and iterative, ensuring the best results for my clients.',
  },
];

const openRecentProjects = () => {
  console.log(t('home.recentProjectsClicked'));
};

</script>

<template>
    <main class="py-5 flex flex-col gap-20">
        <Slideshow
            :images="images"
            :autoplay="true"
            :interval="5000"
        />

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
    </main>
</template>


