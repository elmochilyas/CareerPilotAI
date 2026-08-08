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
const variantStyles: Record<
  VariantKey,
  { bg: string; border: string; icon: string; text: string }
> = {
  info: {
    bg: 'bg-blue-50',
    border: 'border-blue-200',
    icon: 'text-blue-500',
    text: 'text-blue-800',
  },
  success: {
    bg: 'bg-emerald-50',
    border: 'border-emerald-200',
    icon: 'text-emerald-500',
    text: 'text-emerald-800',
  },
  warning: {
    bg: 'bg-amber-50',
    border: 'border-amber-200',
    icon: 'text-amber-500',
    text: 'text-amber-800',
  },
  error: {
    bg: 'bg-red-50',
    border: 'border-red-200',
    icon: 'text-red-500',
    text: 'text-red-800',
  },
}
</script>

<template>
  <div
    role="alert"
    class="flex items-start gap-3 rounded-lg border p-4"
    :class="[variantStyles[variant as VariantKey].bg, variantStyles[variant as VariantKey].border]"
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
      class="shrink-0 rounded-md p-1 opacity-70 transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-current"
      :class="variantStyles[variant as VariantKey].text"
      aria-label="Dismiss"
      @click="emit('close')"
    >
      <X :size="14" stroke-width="2" />
    </button>
  </div>
</template>
