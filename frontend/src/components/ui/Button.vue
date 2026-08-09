<script setup lang="ts">
import { Loader2 } from '@lucide/vue'

withDefaults(
  defineProps<{
    variant?:
      | 'primary'
      | 'secondary'
      | 'outline'
      | 'ghost'
      | 'danger'
      | 'destructive-ghost'
      | 'warning'
      | 'soft-danger'
      | 'soft-success'
      | 'soft-warning'
    size?: 'sm' | 'md' | 'lg'
    disabled?: boolean
    loading?: boolean
    type?: 'button' | 'submit'
  }>(),
  { variant: 'primary', size: 'md', type: 'button' },
)
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="inline-flex items-center justify-center gap-2 rounded-xl font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    :class="[
      size === 'sm' ? 'min-h-8 px-3 text-xs' : '',
      size === 'md' ? 'min-h-10 px-4 text-sm' : '',
      size === 'lg' ? 'min-h-12 px-6 text-base' : '',
      variant === 'primary'
        ? 'bg-[var(--color-primary-600)] text-white shadow-[var(--shadow-neo-button)] hover:bg-[var(--color-primary-700)] active:bg-[var(--color-primary-800)] active:shadow-[var(--shadow-neo-button-pressed)] active:scale-[0.97]'
        : '',
      variant === 'secondary'
        ? 'bg-white text-[var(--text-primary)] shadow-[var(--shadow-neo-raised-sm)] hover:shadow-[var(--shadow-neo-raised)] active:shadow-[var(--shadow-neo-button-pressed)] active:scale-[0.98]'
        : '',
      variant === 'outline'
        ? 'bg-white text-[var(--text-secondary)] shadow-none hover:shadow-[var(--shadow-neo-raised-sm)] active:shadow-[var(--shadow-neo-button-pressed)] active:scale-[0.98]'
        : '',
      variant === 'ghost'
        ? 'text-[var(--text-secondary)] hover:bg-[var(--surface-secondary)] active:bg-[var(--surface-inset)]'
        : '',
      variant === 'danger'
        ? 'bg-[var(--color-error-600)] text-white shadow-[var(--shadow-neo-button)] hover:bg-[var(--color-error-700)] active:bg-[var(--color-error-600)] active:shadow-[var(--shadow-neo-button-pressed)] active:scale-[0.97]'
        : '',
      variant === 'destructive-ghost'
        ? 'text-[var(--color-error-600)] hover:bg-[var(--color-error-50)] active:bg-[var(--color-error-100)]'
        : '',
      variant === 'warning'
        ? 'bg-[var(--color-warning-600)] text-white shadow-[var(--shadow-neo-button)] hover:bg-[var(--color-warning-700)] active:bg-[var(--color-warning-600)] active:shadow-[var(--shadow-neo-button-pressed)] active:scale-[0.97]'
        : '',
      variant === 'soft-danger'
        ? 'bg-[var(--color-error-50)] text-[var(--color-error-700)] shadow-none hover:bg-[var(--color-error-100)] active:bg-[var(--color-error-50)]'
        : '',
      variant === 'soft-success'
        ? 'bg-[var(--color-success-50)] text-[var(--color-success-700)] shadow-none hover:bg-[var(--color-success-100)] active:bg-[var(--color-success-50)]'
        : '',
      variant === 'soft-warning'
        ? 'bg-[var(--color-warning-50)] text-[var(--color-warning-700)] shadow-none hover:bg-[var(--color-warning-100)] active:bg-[var(--color-warning-50)]'
        : '',
    ]"
  >
    <slot name="prefix" />
    <Loader2
      v-if="loading"
      class="shrink-0 animate-spin"
      :size="size === 'sm' ? 14 : size === 'lg' ? 18 : 16"
    />
    <slot v-else />
    <slot name="suffix" />
  </button>
</template>
