<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-6 text-center">
    <h2 class="text-base font-semibold text-slate-900">Profile needs more trusted data</h2>
    <p class="mt-2 text-sm leading-relaxed text-slate-600">
      A match needs a profile with at least 50% completion and at least one verified or claimed
      skill. Add evidence and trusted skills to unlock your Career Intelligence Brief.
    </p>
    <RouterLink
      to="/profile"
      class="mt-5 inline-flex min-h-10 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-medium text-white hover:bg-primary-700 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 focus-visible:outline-none"
    >
      Complete my profile
    </RouterLink>
  </div>
</template>
