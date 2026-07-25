<script setup lang="ts">
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
    :disabled="disabled || loading"
    class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-all focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50"
    :class="[
      size === 'sm' ? 'min-h-8 px-3 text-xs' : '',
      size === 'md' ? 'min-h-10 px-4 text-sm' : '',
      size === 'lg' ? 'min-h-12 px-6 text-base' : '',
      variant === 'primary'
        ? 'bg-primary-600 text-white shadow-sm hover:bg-primary-700 active:bg-primary-800'
        : '',
      variant === 'secondary'
        ? 'bg-slate-100 text-slate-700 shadow-sm hover:bg-slate-200 active:bg-slate-300'
        : '',
      variant === 'outline'
        ? 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50 active:bg-slate-100'
        : '',
      variant === 'ghost' ? 'text-slate-600 hover:bg-slate-100 active:bg-slate-200' : '',
      variant === 'danger'
        ? 'bg-red-600 text-white shadow-sm hover:bg-red-700 active:bg-red-800'
        : '',
    ]"
  >
    <slot />
  </button>
</template>
