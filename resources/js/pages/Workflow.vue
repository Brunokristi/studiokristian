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

const img1 = '/assets/26.png';
const img2 = '/assets/27.png';
const img3 = '/assets/28.png';
const img4 = '/assets/29.png';
const img5 = '/assets/30.png';


const images = computed(() =>
    (tm('workflowPage.images') as { alt: string; caption: string }[]).map((img, index) => ({
        src: [img1, img2, img3, img4, img5][index],
        alt: img.alt,
        caption: img.caption,
    }))
)

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