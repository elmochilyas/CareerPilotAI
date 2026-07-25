<script setup lang="ts">
import { RouterView, RouterLink } from 'vue-router'
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-sm">
      <div class="mb-8 text-center">
        <router-link to="/" class="text-xl font-bold text-slate-900 no-underline">
          CareerPilot
        </router-link>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <router-view />
      </div>
    </div>
  </div>
</template>
