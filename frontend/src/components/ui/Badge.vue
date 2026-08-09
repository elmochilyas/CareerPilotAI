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
    class="inline-flex items-center gap-1 rounded-full font-medium shadow-[var(--shadow-neo-raised-sm)] text-[0.6875rem] uppercase tracking-[var(--tracking-wide)]"
    :class="[
      size === 'sm' ? 'px-2 py-px text-[9px]' : 'px-2.5 py-0.5',
      variant === 'default' ? 'bg-[var(--color-neutral-100)] text-[var(--text-secondary)]' : '',
      variant === 'primary' ? 'bg-[var(--color-primary-50)] text-[var(--color-primary-700)]' : '',
      variant === 'success' ? 'bg-[var(--color-success-50)] text-[var(--color-success-700)]' : '',
      variant === 'warning' ? 'bg-[var(--color-warning-50)] text-[var(--color-warning-700)]' : '',
      variant === 'error' ? 'bg-[var(--color-error-50)] text-[var(--color-error-700)]' : '',
      variant === 'info' ? 'bg-[var(--color-info-50)] text-[var(--color-info-700)]' : '',
    ]"
  >
    <span
      v-if="dot"
      class="shrink-0 rounded-full"
      :class="[
        size === 'sm' ? 'size-1.5' : 'size-2',
        variant === 'default' ? 'bg-[var(--color-neutral-500)]' : '',
        variant === 'primary' ? 'bg-[var(--color-primary-500)]' : '',
        variant === 'success' ? 'bg-[var(--color-success-500)]' : '',
        variant === 'warning' ? 'bg-[var(--color-warning-500)]' : '',
        variant === 'error' ? 'bg-[var(--color-error-500)]' : '',
        variant === 'info' ? 'bg-[var(--color-info-500)]' : '',
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
