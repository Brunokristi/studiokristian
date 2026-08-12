<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import Toast from '../../components/Toast.vue'

const menuOpen = ref(false)
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
</script>

<template>
  <div class="min-h-screen bg-[#f5f5f2] text-dark">
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-dark bg-light px-4 sm:px-6">
      <RouterLink :to="{ name: 'dashboard' }" class="font-mono text-sm font-bold uppercase">Studio Kristian / Admin</RouterLink>
      <button class="grid h-10 w-10 place-items-center border border-dark lg:hidden" aria-label="Toggle navigation" @click="menuOpen = !menuOpen"><i class="bi bi-list text-xl"></i></button>
      <RouterLink :to="{ name: 'portfolio.index' }" class="hidden font-mono text-xs font-bold uppercase lg:block">Portfolio</RouterLink>
    </header>
    <div class="mx-auto grid max-w-[1600px] lg:grid-cols-[230px_minmax(0,1fr)]">
      <aside :class="menuOpen ? 'block' : 'hidden'" class="border-b border-dark bg-light p-4 lg:block lg:min-h-[calc(100vh-64px)] lg:border-b-0 lg:border-r lg:p-6">
        <nav class="grid gap-1 font-mono text-xs font-bold uppercase" @click="menuOpen = false">
          <RouterLink :to="{ name: 'dashboard' }" class="admin-nav-link">Dashboard</RouterLink>
          <RouterLink :to="{ name: 'clients.index' }" class="admin-nav-link">Clients</RouterLink>
          <RouterLink :to="{ name: 'projects.index' }" class="admin-nav-link">Projects</RouterLink>
          <RouterLink :to="{ name: 'service-products.index' }" class="admin-nav-link">Service Products</RouterLink>
          <RouterLink :to="{ name: 'portfolio.index' }" class="admin-nav-link">Portfolio</RouterLink>
        </nav>
        <form method="POST" action="/logout" class="mt-8"><input type="hidden" name="_token" :value="csrfToken"><button class="admin-nav-link w-full text-left" type="submit">Log out</button></form>
      </aside>
      <main class="min-w-0 p-4 sm:p-8 lg:p-10"><RouterView /></main>
    </div>
    <Toast />
  </div>
</template>

<style>
.admin-button { display:inline-flex; min-height:40px; align-items:center; justify-content:center; border:1px solid #000; padding:0 14px; font-family:'Space Mono',monospace; font-size:12px; font-weight:700; text-transform:uppercase; transition:background-color .15s,color .15s; }
.admin-button:hover:not(:disabled) { background:#133EB4; color:#fff; }
.admin-button:disabled { cursor:not-allowed; opacity:.45; }
.admin-button--quiet { background:transparent; color:#000; }
.admin-nav-link { display:block; border:1px solid transparent; padding:11px 12px; color:#000; text-decoration:none; }
.admin-nav-link:hover,.admin-nav-link.router-link-exact-active { border-color:#000; background:#d9ff43; }
.admin-field label { display:block; margin-bottom:6px; font-family:'Space Mono',monospace; font-size:11px; font-weight:700; text-transform:uppercase; }
.admin-field input,.admin-field textarea,.admin-field select { width:100%; border:1px solid #737373; border-radius:0; background:#fff; padding:10px 12px; font-size:14px; }
.admin-field input:focus,.admin-field textarea:focus,.admin-field select:focus { border-color:#000; outline:2px solid #133EB4; outline-offset:1px; }
.admin-error { margin-top:5px; color:#a51d14; font-size:12px; }
</style>