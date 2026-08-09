<script setup lang="ts">
import { AlertTriangle, CheckCircle, Info, XCircle } from '@lucide/vue'
import type { FunctionalComponent, SVGAttributes } from 'vue'

withDefaults(
  defineProps<{
    variant?: 'info' | 'success' | 'warning' | 'error'
    title?: string
    icon?: FunctionalComponent<SVGAttributes>
  }>(),
  { variant: 'info' },
)

type VariantKey = 'info' | 'success' | 'warning' | 'error'
const variantStyles: Record<
  VariantKey,
  { bg: string; border: string; icon: string; text: string }
> = {
  info: {
    bg: 'bg-[var(--color-info-50)]',
    border: 'border-[var(--color-info-100)]',
    icon: 'text-[var(--color-info-500)]',
    text: 'text-[var(--color-info-700)]',
  },
  success: {
    bg: 'bg-[var(--color-success-50)]',
    border: 'border-[var(--color-success-100)]',
    icon: 'text-[var(--color-success-500)]',
    text: 'text-[var(--color-success-700)]',
  },
  warning: {
    bg: 'bg-[var(--color-warning-50)]',
    border: 'border-[var(--color-warning-100)]',
    icon: 'text-[var(--color-warning-500)]',
    text: 'text-[var(--color-warning-700)]',
  },
  error: {
    bg: 'bg-[var(--color-error-50)]',
    border: 'border-[var(--color-error-100)]',
    icon: 'text-[var(--color-error-500)]',
    text: 'text-[var(--color-error-700)]',
  },
}

const defaultIcons: Record<VariantKey, FunctionalComponent<SVGAttributes>> = {
  info: Info,
  success: CheckCircle,
  warning: AlertTriangle,
  error: XCircle,
}
</script>

<template>
  <div
    role="status"
    class="flex items-start gap-4 rounded-xl border p-5"
    :class="[variantStyles[variant as VariantKey].bg, variantStyles[variant as VariantKey].border]"
  >
    <component
      :is="icon || defaultIcons[variant as VariantKey]"
      class="mt-0.5 size-6 shrink-0"
      :class="variantStyles[variant as VariantKey].icon"
      aria-hidden="true"
    />
    <div class="flex-1 min-w-0">
      <p
        v-if="title"
        class="text-base font-semibold"
        :class="variantStyles[variant as VariantKey].text"
      >
        {{ title }}
      </p>
      <div class="mt-1 text-sm" :class="variantStyles[variant as VariantKey].text">
        <slot />
      </div>
    </div>
    <div v-if="$slots.actions" class="shrink-0">
      <slot name="actions" />
    </div>
  </div>
</template>
