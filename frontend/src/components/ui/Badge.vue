<script setup lang="ts">
import type { FunctionalComponent, SVGAttributes } from 'vue'

withDefaults(
  defineProps<{
    variant?: 'default' | 'primary' | 'success' | 'warning' | 'error' | 'info'
    size?: 'sm' | 'md'
    dot?: boolean
    icon?: FunctionalComponent<SVGAttributes>
  }>(),
  { variant: 'default', size: 'md' },
)
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-full border font-medium"
    :class="[
      size === 'sm' ? 'px-2 py-px text-[10px]' : 'px-2.5 py-0.5 text-xs',
      variant === 'default' ? 'border-slate-300 bg-slate-50 text-slate-700' : '',
      variant === 'primary' ? 'border-primary-200 bg-primary-50 text-primary-700' : '',
      variant === 'success' ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : '',
      variant === 'warning' ? 'border-amber-300 bg-amber-50 text-amber-700' : '',
      variant === 'error' ? 'border-red-300 bg-red-50 text-red-700' : '',
      variant === 'info' ? 'border-blue-300 bg-blue-50 text-blue-700' : '',
    ]"
  >
    <span
      v-if="dot"
      class="shrink-0 rounded-full"
      :class="[
        size === 'sm' ? 'size-1.5' : 'size-2',
        variant === 'default' ? 'bg-slate-500' : '',
        variant === 'primary' ? 'bg-primary-500' : '',
        variant === 'success' ? 'bg-emerald-500' : '',
        variant === 'warning' ? 'bg-amber-500' : '',
        variant === 'error' ? 'bg-red-500' : '',
        variant === 'info' ? 'bg-blue-500' : '',
      ]"
      aria-hidden="true"
    />
    <component
      :is="icon"
      v-if="icon"
      :class="size === 'sm' ? 'size-3' : 'size-3.5'"
      aria-hidden="true"
    />
    <slot />
  </span>
</template>
