<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <nav class="border-b border-gray-200 bg-white" aria-label="Main navigation">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
      <RouterLink
        to="/"
        class="text-lg font-semibold text-gray-900 no-underline hover:text-primary-600"
      >
        CareerPilot
      </RouterLink>
      <div class="flex items-center gap-4">
        <RouterLink
          to="/"
          class="text-sm font-medium text-gray-600 no-underline hover:text-primary-600"
        >
          Home
        </RouterLink>
      </div>
    </div>
  </nav>
</template>
