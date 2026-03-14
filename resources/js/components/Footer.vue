<script setup>
const logoSrc = '/assets/logo_white.svg'
const date = new Date().getFullYear()
</script>

<template>
  <footer
    class="w-full px-6 py-16 md:py-24 flex flex-col gap-12 items-center justify-center bg-gradient-to-b from-transparent from-0% via-accent via-70% to-black to-100%"
    data-theme="dark"
  >
    <div
      class="w-full max-w-6xl flex flex-col items-center gap-12 md:flex-row md:items-start md:justify-between"
    >
      <div class="flex items-center shrink-0">
        <a href="/" class="flex flex-col gap-2 items-center">
            <h1 class="h1 text-light">studio</h1>
            <img :src="logoSrc" alt="Studio Kristian Logo" class="h-10 w-auto" />
            <h1 class="h1 text-light>">kristian</h1>
        </a>
      </div>

      <div
        class="w-full grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-10 text-center md:text-left"
      >
        <div class="flex flex-col gap-4">
          <a
            href="https://www.instagram.com/studiokristian/"
            target="_blank"
            class="text-muted hover:text-light transition-colors duration-300"
          >
            <p class="p underline uppercase text-light">instagram</p>
          </a>

          <a
            href="https://www.instagram.com/studiokristian/"
            target="_blank"
            class="text-muted hover:text-light transition-colors duration-300"
          >
            <p class="p underline uppercase text-light">instagram</p>
          </a>

          <a
            href="https://www.instagram.com/studiokristian/"
            target="_blank"
            class="text-muted hover:text-light transition-colors duration-300"
          >
            <p class="p underline uppercase text-light">instagram</p>
          </a>
        </div>

        <div class="flex flex-col gap-4">
          <a
            href="https://www.instagram.com/studiokristian/"
            target="_blank"
            class="text-muted hover:text-light transition-colors duration-300"
          >
            <p class="p underline uppercase text-light">instagram</p>
          </a>

          <a
            href="https://www.instagram.com/studiokristian/"
            target="_blank"
            class="text-muted hover:text-light transition-colors duration-300"
          >
            <p class="p underline uppercase text-light">facebook</p>
          </a>
        </div>

        <div class="flex flex-col gap-4">
          <a
            href="https://www.instagram.com/studiokristian/"
            target="_blank"
            class="text-muted hover:text-light transition-colors duration-300"
          >
            <p class="p underline uppercase text-light">instagram</p>
          </a>
        </div>
      </div>
    </div>

    <p class="p text-dark uppercase text-center">
      © {{ date }} Studio Kristian. All rights reserved.
    </p>
  </footer>
</template>