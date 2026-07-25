<script setup lang="ts">
import type { FunctionalComponent, SVGAttributes } from 'vue'

withDefaults(
  defineProps<{
    variant?: 'default' | 'success' | 'warning' | 'error' | 'info'
    icon?: FunctionalComponent<SVGAttributes>
  }>(),
  { variant: 'default' },
)
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium"
    :class="[
      variant === 'default' ? 'border-slate-300 bg-slate-50 text-slate-700' : '',
      variant === 'success' ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : '',
      variant === 'warning' ? 'border-amber-300 bg-amber-50 text-amber-700' : '',
      variant === 'error' ? 'border-red-300 bg-red-50 text-red-700' : '',
      variant === 'info' ? 'border-blue-300 bg-blue-50 text-blue-700' : '',
    ]"
  >
    <component :is="icon" v-if="icon" class="size-3.5" aria-hidden="true" />
    <slot />
  </span>
</template>
