<script setup lang="ts">
import { ref } from 'vue'

defineProps<{
  modelValue: string
  label?: string
  name?: string
  type?: string
  placeholder?: string
  error?: string
  hint?: string
  disabled?: boolean
  required?: boolean
  maxlength?: number
  autocomplete?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const input = ref<HTMLInputElement | null>(null)

function onInput(e: Event) {
  const target = e.target as HTMLInputElement
  emit('update:modelValue', target.value)
}

function focus(): void {
  input.value?.focus()
}

defineExpose({ focus })
</script>

<template>
  <div class="grid gap-1.5">
    <label v-if="label" :for="name" class="text-sm font-medium text-[var(--text-secondary)]">
      {{ label }}
      <span v-if="required" class="text-[var(--color-error-500)]">*</span>
    </label>
    <div class="relative">
      <span
        v-if="$slots.prefix"
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-muted)]"
      >
        <slot name="prefix" />
      </span>
      <input
        ref="input"
        :id="name"
        :name="name"
        :type="type || 'text'"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :maxlength="maxlength"
        :autocomplete="autocomplete"
        :aria-invalid="!!error"
        :aria-describedby="error ? `${name}-error` : hint ? `${name}-hint` : undefined"
        class="min-h-11 w-full rounded-xl bg-[var(--surface-secondary)] text-[var(--text-primary)] text-sm shadow-[var(--shadow-neo-inset)] transition-all duration-200 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50 placeholder:text-[var(--text-muted)]"
        :class="[
          error
            ? 'focus:ring-[var(--color-error-500)]/30'
            : 'focus:ring-[var(--color-primary-500)]/20',
          $slots.prefix ? 'pl-10 pr-3.5' : 'px-3.5',
          maxlength && !$slots.prefix ? 'pr-14' : '',
          !$slots.prefix && $slots.suffix ? 'pr-10' : '',
        ]"
        @input="onInput"
      />
      <span
        v-if="$slots.suffix && !$slots.prefix && !maxlength"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[var(--text-muted)]"
      >
        <slot name="suffix" />
      </span>
      <span
        v-if="maxlength"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[var(--text-muted)]"
      >
        {{ String(modelValue).length }}/{{ maxlength }}
      </span>
    </div>
    <p
      v-if="error"
      :id="`${name}-error`"
      class="text-xs text-[var(--color-error-600)]"
      role="alert"
    >
      {{ error }}
    </p>
    <p v-else-if="hint" :id="`${name}-hint`" class="text-xs text-[var(--text-muted)]">
      {{ hint }}
    </p>
  </div>
</template>
