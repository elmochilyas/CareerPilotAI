<script setup lang="ts">
defineProps<{
  label?: string
  error?: string
  hint?: string
  required?: boolean
  htmlFor?: string
}>()
</script>

<template>
  <div class="grid gap-1.5">
    <label v-if="label" :for="htmlFor" class="text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-red-500" aria-hidden="true">*</span>
    </label>
    <slot />
    <p v-if="error" class="text-xs text-red-600" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" class="text-xs text-slate-500">
      {{ hint }}
    </p>
  </div>
</template>
