<script setup lang="ts">
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <router-link to="/" class="text-2xl font-bold text-gray-900 no-underline">
          CareerPilot
        </router-link>
      </div>
      <div class="rounded-lg bg-white p-8 shadow-sm ring-1 ring-gray-900/5">
        <router-view />
      </div>
    </div>
  </div>
</template>
