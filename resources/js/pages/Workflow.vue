<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSeoMeta } from '../composables/useSeoMeta';

import Button from '../components/Button.vue';
import Slideshow from '../components/Slideshow.vue';
import Info from '../components/Info.vue';

const { t, tm } = useI18n();

useSeoMeta({
    title: () => t('seo.workflow.title'),
    description: () => t('seo.workflow.description'),
});

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
];

const steps = computed(() => tm('workflowPage.steps') as { heading: string; text: string }[]);

const openRecentProjects = () => {
    window.location.href = '/portfolio';
};
</script>

<template>
    <main class="py-5 flex flex-col gap-20">
        <Slideshow
            :images="images"
            :autoplay="true"
            :interval="5000"
        />

        <div class="flex flex-col">
            <Info
                v-for="(step, index) in steps"
                :key="index"
                :heading="step.heading"
                :text="step.text"
            />
        </div>

        <Button
            :text="t('workflowPage.callToAction')"
            variant="dark"
            @click="openRecentProjects"
        />
    </main>
</template>