<script setup>
import { useI18n } from 'vue-i18n';
const { t, locale } = useI18n();

import { useGlobalActions } from '../composables/useGlobalActions';
const { openContacts, openRecentProjects, openWorkflow, openEmail } = useGlobalActions();

const logoSrc = '/assets/logo_white.svg'
const date = new Date().getFullYear()
</script>

<template>
  <footer
    class="w-full w-max-[500px] sm:px-16 md:px-30 py-16 md:py-24 flex flex-col gap-12 items-center justify-center bg-gradient-to-b from-transparent from-0% via-accent via-70% to-black to-100% mt-20"
    data-theme="dark"
  >
    <div class="w-full flex flex-col md:flex-row items-center sm:gap-20 gap-10 justify-between" >
      
      <div>
        <div class="flex items-center shrink-0">
          <a href="/" class="flex flex-col gap-2 items-center text-light">
              <h1 class="h1">studio</h1>
              <img :src="logoSrc" alt="Studio Kristian Logo" class="h-10 w-auto" />
              <h1 class="h1">kristian</h1>
          </a>
        </div>
      </div>


      <div class="flex flex-col gap-10">

        <div class="grid md:grid-cols-3 grid-cols-1 text-left md:gap-8 gap-4 item-center md:text-left">

          <div class="flex flex-col gap-4 items-start md:items-end">
            <button
              @click="openContacts"
              class="text-light hover:text-accent transition-colors duration-300"
            >
              <p class="p underline uppercase">{{ t('footer.privacy') }}</p>
            </button>

            <button
              @click="openEmail"
              class="text-light hover:text-accent transition-colors duration-300"
            >
              <p class="p underline uppercase">{{ t('footer.email') }}</p>
            </button>
          </div>

          <div class="flex flex-col gap-4 items-end">
            <a
              href="https://www.instagram.com/studiokristian/"
              target="_blank"
              class="text-light hover:text-accent transition-colors duration-300"
            >
              <p class="p underline uppercase">{{ t('footer.instagram') }}</p>
            </a>

            <a
              href="https://www.instagram.com/studiokristian/"
              target="_blank"
              class="text-light hover:text-accent transition-colors duration-300"
            >
              <p class="p underline uppercase">{{ t('footer.facebook') }}</p>
            </a>
          </div>

          <div class="flex flex-col gap-4 items-start md:items-end">
            <button
              @click="openContacts"
              class="text-light hover:text-accent transition-colors duration-300"
            >
              <p class="p underline uppercase">{{ t('footer.contact') }}</p>
            </button>

            <button
              @click="openRecentProjects"
              class="text-light hover:text-accent transition-colors duration-300"
            >
              <p class="p underline uppercase">{{ t('footer.portfolio') }}</p>
            </button>

            <button
              @click="openWorkflow"
              class="text-light hover:text-accent transition-colors duration-300"
            >
              <p class="p underline uppercase">{{ t('footer.workflow') }}</p>
            </button>
          </div>
        </div>

        <p class="p text-light uppercase w-full text-center md:text-end"> © {{ date }} {{ t('footer.copyright') }}</p>
      </div>
    </div>


  </footer>
</template>