<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import Button from '../components/Button.vue';
import { useGlobalActions } from '../composables/useGlobalActions';

const { t } = useI18n();
const router = useRouter();
const { openRecentProjects, openWorkflow, openContacts } = useGlobalActions();

</script>

<template>
    <main class="py-5 flex flex-col gap-6 justify-center items-center h-[calc(100vh-3.5rem)]" data-theme="dark">        
          <Button
            :text="t('navigation.home')"
            variant="accent"
            @click="router.push('/')"
          />
          <Button
            :text="t('navigation.portfolio')"
            variant="accent"
            @click="openRecentProjects"
          />
          <Button
            :text="t('navigation.workflow')"
            variant="accent"
            @click="openWorkflow"
          />
          <Button
            :text="t('navigation.contact')"
            variant="accent"
            @click="openContacts"
          />
    </main>
</template>