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
  <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <p class="text-sm font-medium text-slate-500">
        {{ label }}
      </p>
      <component :is="icon" v-if="icon" class="size-5 text-slate-400" aria-hidden="true" />
    </div>
    <div class="mt-2 flex items-baseline gap-2">
      <p class="text-2xl font-bold tracking-tight text-slate-900">
        {{ value }}
      </p>
      <span
        v-if="trend"
        class="inline-flex items-center gap-1 text-xs font-medium"
        :class="trend.direction === 'up' ? 'text-emerald-600' : 'text-red-600'"
      >
        <TrendingUp v-if="trend.direction === 'up'" :size="14" aria-hidden="true" />
        <TrendingDown v-else :size="14" aria-hidden="true" />
        {{ trend.value }}%
      </span>
    </div>
  </div>
</template>
