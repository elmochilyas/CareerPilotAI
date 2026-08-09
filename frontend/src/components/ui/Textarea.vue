<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  modelValue: string
  label?: string
  name?: string
  placeholder?: string
  error?: string
  disabled?: boolean
  required?: boolean
  maxlength?: number
  rows?: number
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const textarea = ref<HTMLTextAreaElement | null>(null)

function onInput(e: Event) {
  const target = e.target as HTMLTextAreaElement
  emit('update:modelValue', target.value)
}

function focus(): void {
  textarea.value?.focus()
}

defineExpose({ focus })
</script>

<template>
  <div class="grid gap-1.5">
    <label v-if="label" :for="name" class="text-sm font-medium text-[var(--text-secondary)]">
      {{ label }}
      <span v-if="required" class="text-[var(--color-error-500)]">*</span>
    </label>
    <textarea
      ref="textarea"
      :id="name"
      :name="name"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :maxlength="maxlength"
      :rows="rows || 4"
      :aria-invalid="!!error"
      :aria-describedby="error ? `${name}-error` : undefined"
      class="w-full rounded-xl bg-[var(--surface-secondary)] p-3.5 text-[var(--text-primary)] text-sm shadow-[var(--shadow-neo-inset-lg)] transition-all duration-200 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50 placeholder:text-[var(--text-muted)]"
      :class="
        error
          ? 'focus:ring-[var(--color-error-500)]/30'
          : 'focus:ring-[var(--color-primary-500)]/20'
      "
      @input="onInput"
    />
    <div class="flex items-center justify-between">
      <p
        v-if="error"
        :id="`${name}-error`"
        class="text-xs text-[var(--color-error-600)]"
        role="alert"
      >
        {{ error }}
      </p>
      <span v-if="maxlength" class="ml-auto text-xs text-[var(--text-muted)]">
        {{ String(modelValue).length }}/{{ maxlength }}
      </span>
    </div>
  </div>
</template>
