<script setup lang="ts">
import { RouterView, RouterLink } from 'vue-router'
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-[var(--surface-page)] px-4">
    <div class="w-full max-w-sm">
      <div class="mb-8 flex flex-col items-center">
        <RouterLink to="/" class="flex items-center gap-2 no-underline">
          <span
            class="flex size-9 items-center justify-center rounded-xl bg-[var(--color-primary-600)] text-sm font-bold text-[var(--text-on-primary)]"
          >
            CP
          </span>
          <span class="text-xl font-bold tracking-tight text-[var(--text-primary)]">
            CareerPilot
          </span>
        </RouterLink>
      </div>

      <div
        class="rounded-xl border bg-[var(--surface-primary)] p-6 shadow-[var(--elevation-2)]"
        :style="{ borderColor: 'var(--border-default)' }"
      >
        <RouterView />
      </div>
    </div>
  </div>
</template>
