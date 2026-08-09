<script setup lang="ts">
import { AlertTriangle, CheckCircle, Info, X, XCircle } from '@lucide/vue'

withDefaults(
  defineProps<{
    variant?: 'info' | 'success' | 'warning' | 'error'
    title?: string
    closable?: boolean
  }>(),
  { variant: 'info', closable: false },
)

const emit = defineEmits<{
  close: []
}>()

type VariantKey = 'info' | 'success' | 'warning' | 'error'
const variantStyles: Record<VariantKey, { bg: string; icon: string; text: string }> = {
  info: {
    bg: 'bg-[var(--color-info-50)]',
    icon: 'text-[var(--color-info-500)]',
    text: 'text-[var(--color-info-700)]',
  },
  success: {
    bg: 'bg-[var(--color-success-50)]',
    icon: 'text-[var(--color-success-500)]',
    text: 'text-[var(--color-success-700)]',
  },
  warning: {
    bg: 'bg-[var(--color-warning-50)]',
    icon: 'text-[var(--color-warning-500)]',
    text: 'text-[var(--color-warning-700)]',
  },
  error: {
    bg: 'bg-[var(--color-error-50)]',
    icon: 'text-[var(--color-error-500)]',
    text: 'text-[var(--color-error-700)]',
  },
}
</script>

<template>
  <div
    role="alert"
    class="flex items-start gap-3 rounded-xl shadow-[var(--shadow-neo-raised-sm)] p-4"
    :class="[variantStyles[variant as VariantKey].bg]"
  >
    <component
      :is="
        variant === 'info'
          ? Info
          : variant === 'success'
            ? CheckCircle
            : variant === 'warning'
              ? AlertTriangle
              : XCircle
      "
      class="mt-0.5 size-5 shrink-0"
      :class="variantStyles[variant as VariantKey].icon"
      aria-hidden="true"
    />
    <div class="flex-1 min-w-0">
      <p
        v-if="title"
        class="text-sm font-semibold"
        :class="variantStyles[variant as VariantKey].text"
      >
        {{ title }}
      </p>
      <div class="text-sm" :class="variantStyles[variant as VariantKey].text">
        <slot />
      </div>
    </div>
    <button
      v-if="closable"
      type="button"
      class="shrink-0 rounded-lg p-1 opacity-70 transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current"
      :class="variantStyles[variant as VariantKey].text"
      aria-label="Dismiss"
      @click="emit('close')"
    >
      <X :size="14" stroke-width="2" />
    </button>
  </div>
</template>
