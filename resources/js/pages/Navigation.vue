<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import Button from '../components/Button.vue';
import { useGlobalActions } from '../composables/useGlobalActions';

const { t } = useI18n();
const router = useRouter();
const { openRecentProjects, openWorkflow, openContacts } = useGlobalActions();
const isIntroAnimating = ref(true);
let introTimer: ReturnType<typeof setTimeout> | null = null;

const navLinks = [
  {
    key: 'home',
    textKey: 'navigation.home',
    action: () => router.push('/'),
  },
  {
    key: 'portfolio',
    textKey: 'navigation.portfolio',
    action: openRecentProjects,
  },
  {
    key: 'workflow',
    textKey: 'navigation.workflow',
    action: openWorkflow,
  },
  {
    key: 'contact',
    textKey: 'navigation.contact',
    action: openContacts,
  },
];

onMounted(() => {
  // End intro color override once staggered reveal has finished.
  introTimer = setTimeout(() => {
    isIntroAnimating.value = false;
  }, 1000);
});

onBeforeUnmount(() => {
  if (introTimer) {
    clearTimeout(introTimer);
  }
});

</script>

<template>
    <main class="py-5 flex flex-col gap-6 justify-center items-center h-[calc(100vh-3.5rem)]" data-theme="dark">
        <div
          v-for="(link, index) in navLinks"
          :key="link.key"
          class="nav-item"
          :class="{ 'nav-intro': isIntroAnimating }"
          :style="{ '--delay': `${index * 90}ms` }"
        >
          <Button
            :text="t(link.textKey)"
            variant="accent"
            @click="link.action"
          />
        </div>
    </main>
</template>

<style scoped>
.nav-item {
    width: 100%;
    opacity: 0;
    transform: translateY(10px) scale(0.985);
    animation: nav-reveal 480ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: var(--delay, 0ms);
    transition: transform 220ms ease;
}

.nav-item:hover {
    transform: translateY(-2px) scale(1.01);
}

.nav-item:active {
    transform: translateY(0) scale(0.995);
}

.nav-item.nav-intro :deep(button) {
  animation: nav-color-shift 620ms ease forwards;
  animation-delay: calc(var(--delay, 0ms) + 70ms);
}

@keyframes nav-reveal {
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes nav-color-shift {
  0% {
    color: #ffffff;
  }

  55% {
    color: #ffffff;
  }

  100% {
    color: #133eb4;
  }
}

@media (prefers-reduced-motion: reduce) {
    .nav-item {
        opacity: 1;
        transform: none;
        animation: none;
        transition: none;
    }

  .nav-item.nav-intro :deep(button) {
    animation: none;
    color: #133eb4;
  }
}
</style>