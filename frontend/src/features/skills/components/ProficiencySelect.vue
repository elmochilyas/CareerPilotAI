<script setup lang="ts">
import type { ProficiencyLevel } from '../types'

defineProps<{ modelValue: ProficiencyLevel; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: ProficiencyLevel] }>()

const levels: { value: ProficiencyLevel; label: string }[] = [
  { value: 'beginner', label: 'Beginner' },
  { value: 'elementary', label: 'Elementary' },
  { value: 'intermediate', label: 'Intermediate' },
  { value: 'advanced', label: 'Advanced' },
  { value: 'expert', label: 'Expert' },
]
</script>

<template>
  <select
    :value="modelValue"
    :disabled="disabled"
    class="block w-full rounded-[var(--radius-lg)] bg-[var(--surface-secondary)] px-3 py-2 text-sm shadow-[var(--shadow-neo-inset)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30 disabled:cursor-not-allowed disabled:bg-[var(--surface-sunken)]"
    @change="
      emit('update:modelValue', ($event.target as HTMLSelectElement).value as ProficiencyLevel)
    "
  >
    <option v-for="lvl in levels" :key="lvl.value" :value="lvl.value">
      {{ lvl.label }}
    </option>
  </select>
</template>
