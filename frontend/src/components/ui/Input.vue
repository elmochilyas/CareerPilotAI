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
    <label v-if="label" :for="name" class="text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <div class="relative">
      <span
        v-if="$slots.prefix"
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
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
        class="min-h-11 w-full rounded-lg border bg-white text-sm shadow-sm transition-all focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
        :class="[
          error
            ? 'border-red-300 focus:border-red-400 focus:ring-red-500/30'
            : 'border-slate-300 focus:border-primary-400 focus:ring-primary-500/30',
          $slots.prefix ? 'pl-10 pr-3.5' : 'px-3.5',
          maxlength && !$slots.prefix ? 'pr-14' : '',
          !$slots.prefix && $slots.suffix ? 'pr-10' : '',
        ]"
        @input="onInput"
      />
      <span
        v-if="$slots.suffix && !$slots.prefix && !maxlength"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
      >
        <slot name="suffix" />
      </span>
      <span
        v-if="maxlength"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"
      >
        {{ String(modelValue).length }}/{{ maxlength }}
      </span>
    </div>
    <p v-if="error" :id="`${name}-error`" class="text-xs text-red-600" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" :id="`${name}-hint`" class="text-xs text-slate-500">
      {{ hint }}
    </p>
  </div>
</template>
