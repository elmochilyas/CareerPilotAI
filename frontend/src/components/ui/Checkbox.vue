<script setup lang="ts">
import { Check } from '@lucide/vue'

defineProps<{
  modelValue: boolean
  id?: string
  label?: string
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

function onChange(e: Event) {
  const target = e.target as HTMLInputElement
  emit('update:modelValue', target.checked)
}
</script>

<template>
  <div class="flex items-center gap-2.5">
    <div class="relative flex items-center justify-center">
      <input
        :id="id"
        type="checkbox"
        :checked="modelValue"
        :disabled="disabled"
        class="peer size-4.5 cursor-pointer appearance-none rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30 focus:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50"
        :class="
          modelValue
            ? 'bg-[var(--color-primary-600)] shadow-[var(--shadow-neo-button-pressed)]'
            : 'bg-[var(--surface-secondary)] shadow-[var(--shadow-neo-inset)]'
        "
        @change="onChange"
      />
      <Check
        v-if="modelValue"
        class="pointer-events-none absolute size-3 text-white"
        stroke-width="3"
      />
    </div>
    <label
      v-if="label"
      :for="id"
      class="cursor-pointer select-none text-sm text-[var(--text-secondary)]"
    >
      {{ label }}
    </label>
  </div>
</template>
