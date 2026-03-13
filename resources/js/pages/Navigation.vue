<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import Button from '../components/Button.vue';

const { t } = useI18n();
const router = useRouter();

function openRecentProjects() {
  router.push({ name: 'portfolio' });
}
</script>

<template>
    <main class="py-5 flex flex-col gap-6 justify-center items-center h-[calc(100vh-3.5rem)]" data-theme="dark">        
        <Button
            :text="t('home.recentProjects')"
            variant="accent"
            @click="openRecentProjects"
          />
          <Button
            :text="t('home.recentProjects')"
            variant="accent"
            @click="openRecentProjects"
          />
          <Button
            :text="t('home.recentProjects')"
            variant="accent"
            @click="openRecentProjects"
          />
    </main>
</template>