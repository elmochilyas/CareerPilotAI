<script setup lang="ts">
import type { FunctionalComponent, SVGAttributes } from 'vue'
import Badge from '@/components/ui/Badge.vue'

withDefaults(
  defineProps<{
    title: string
    icon?: FunctionalComponent<SVGAttributes>
    inference?: boolean
  }>(),
  { inference: false },
)
</script>

<template>
  <div
    class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-primary)] p-5 sm:p-6"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <span
          v-if="icon"
          class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-[var(--color-primary-100)] bg-[var(--color-primary-50)]"
          aria-hidden="true"
        >
          <component :is="icon" class="size-4 text-[var(--color-primary-600)]" />
        </span>
        <h3 class="text-sm font-semibold leading-none text-[var(--text-primary)]">{{ title }}</h3>
      </div>
      <Badge v-if="inference" variant="warning" size="sm">INFERENCE</Badge>
    </div>
    <div class="mt-4">
      <slot />
    </div>
  </div>
</template>
