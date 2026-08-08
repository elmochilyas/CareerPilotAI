<script setup lang="ts">
import { Loader2 } from '@lucide/vue'

withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger'
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
    class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-all focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50"
    :class="[
      size === 'sm' ? 'min-h-8 px-3 text-xs' : '',
      size === 'md' ? 'min-h-10 px-4 text-sm' : '',
      size === 'lg' ? 'min-h-12 px-6 text-base' : '',
      variant === 'primary'
        ? 'bg-primary-600 text-white shadow-sm hover:bg-primary-700 active:bg-primary-800 focus:ring-primary-500/40'
        : '',
      variant === 'secondary'
        ? 'bg-slate-100 text-slate-700 shadow-sm hover:bg-slate-200 active:bg-slate-300 focus:ring-slate-500/40'
        : '',
      variant === 'outline'
        ? 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 active:bg-slate-100 focus:ring-primary-500/40'
        : '',
      variant === 'ghost'
        ? 'text-slate-600 hover:bg-slate-100 active:bg-slate-200 focus:ring-slate-500/40'
        : '',
      variant === 'danger'
        ? 'bg-red-600 text-white shadow-sm hover:bg-red-700 active:bg-red-800 focus:ring-red-500/40'
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
