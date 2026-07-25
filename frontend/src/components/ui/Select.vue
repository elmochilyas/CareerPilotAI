<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'

defineProps<{
  modelValue: string
  label?: string
  name?: string
  options: { value: string; label: string }[]
  placeholder?: string
  error?: string
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

function onChange(e: Event) {
  const target = e.target as HTMLSelectElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div class="grid gap-1.5">
    <label v-if="label" :for="name" class="text-sm font-medium text-slate-700">
      {{ label }}
    </label>
    <div class="relative">
      <select
        :id="name"
        :name="name"
        :value="modelValue"
        :disabled="disabled"
        :aria-invalid="!!error"
        :aria-describedby="error ? `${name}-error` : undefined"
        class="min-h-11 w-full appearance-none rounded-lg border bg-white px-3.5 pr-10 text-sm shadow-sm transition-all focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
        :class="
          error
            ? 'border-red-300 focus:border-red-400 focus:ring-red-500/30'
            : 'border-slate-300 focus:border-primary-400 focus:ring-primary-500/30'
        "
        @change="onChange"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option v-for="opt in options" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>
      <ChevronDown
        class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
      />
    </div>
    <p v-if="error" :id="`${name}-error`" class="text-xs text-red-600" role="alert">
      {{ error }}
    </p>
  </div>
</template>
