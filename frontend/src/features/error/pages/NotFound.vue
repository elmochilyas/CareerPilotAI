<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="py-4 text-center">
    <h1 class="text-2xl font-bold tracking-tight text-[var(--text-primary)]">Page not found</h1>
    <p class="mt-2 text-sm text-[var(--text-secondary)]">
      The page you are looking for does not exist or has been moved.
    </p>
    <RouterLink
      :to="{ name: 'home' }"
      class="mt-6 inline-flex items-center justify-center rounded-[var(--radius-md)] bg-[var(--color-primary-600)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-primary-700)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary-600)]"
    >
      Go to home
    </RouterLink>
  </div>
</template>
