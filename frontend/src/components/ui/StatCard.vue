<script setup lang="ts">
import { TrendingDown, TrendingUp } from '@lucide/vue'
import type { FunctionalComponent, SVGAttributes } from 'vue'

defineProps<{
  label: string
  value: string | number
  icon?: FunctionalComponent<SVGAttributes>
  trend?: { value: number; direction: 'up' | 'down' }
}>()
</script>

<template>
  <div class="rounded-xl bg-[var(--surface-primary)] p-5 shadow-[var(--shadow-neo-raised)]">
    <div class="flex items-center justify-between">
      <p class="text-sm font-medium text-[var(--text-secondary)]">
        {{ label }}
      </p>
      <component
        :is="icon"
        v-if="icon"
        class="size-5 text-[var(--text-muted)]"
        aria-hidden="true"
      />
    </div>
    <div class="mt-2 flex items-baseline gap-2">
      <p class="text-2xl font-bold tracking-tight text-[var(--text-primary)]">
        {{ value }}
      </p>
      <span
        v-if="trend"
        class="inline-flex items-center gap-1 text-xs font-medium"
        :class="
          trend.direction === 'up'
            ? 'text-[var(--color-success-600)]'
            : 'text-[var(--color-error-600)]'
        "
      >
        <TrendingUp v-if="trend.direction === 'up'" :size="14" aria-hidden="true" />
        <TrendingDown v-else :size="14" aria-hidden="true" />
        {{ trend.value }}%
      </span>
    </div>
  </div>
</template>
