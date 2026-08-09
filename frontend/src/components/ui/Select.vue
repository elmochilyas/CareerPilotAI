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
    <label v-if="label" :for="name" class="text-sm font-medium text-[var(--text-secondary)]">
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
        class="min-h-11 w-full appearance-none rounded-xl bg-[var(--surface-secondary)] px-3.5 pr-10 text-sm shadow-[var(--shadow-neo-inset)] transition-all duration-200 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50"
        :class="
          error
            ? 'focus:ring-[var(--color-error-500)]/30'
            : 'focus:ring-[var(--color-primary-500)]/20'
        "
        @change="onChange"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option v-for="opt in options" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>
      <ChevronDown
        class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[var(--text-muted)]"
      />
    </div>
    <p
      v-if="error"
      :id="`${name}-error`"
      class="text-xs text-[var(--color-error-600)]"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>
