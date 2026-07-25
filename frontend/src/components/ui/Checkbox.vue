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
        class="peer size-4 cursor-pointer appearance-none rounded border-2 border-slate-300 bg-white transition-all checked:border-primary-600 checked:bg-primary-600 focus:ring-2 focus:ring-primary-500/30 focus:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50"
        @change="onChange"
      />
      <Check
        v-if="modelValue"
        class="pointer-events-none absolute size-3 text-white"
        stroke-width="3"
      />
    </div>
    <label v-if="label" :for="id" class="cursor-pointer select-none text-sm text-slate-700">
      {{ label }}
    </label>
  </div>
</template>
