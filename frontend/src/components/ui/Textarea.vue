<script setup lang="ts">
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

function onInput(e: Event) {
  const target = e.target as HTMLTextAreaElement
  emit('update:modelValue', target.value)
}
</script>

<template>
  <div class="grid gap-1.5">
    <label v-if="label" :for="name" class="text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <textarea
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
      class="w-full rounded-lg border bg-white p-3.5 text-sm shadow-sm transition-all focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400"
      :class="
        error
          ? 'border-red-300 focus:border-red-400 focus:ring-red-500/30'
          : 'border-slate-300 focus:border-primary-400 focus:ring-primary-500/30'
      "
      @input="onInput"
    />
    <div class="flex items-center justify-between">
      <p v-if="error" :id="`${name}-error`" class="text-xs text-red-600" role="alert">
        {{ error }}
      </p>
      <span v-if="maxlength" class="ml-auto text-xs text-slate-400">
        {{ String(modelValue).length }}/{{ maxlength }}
      </span>
    </div>
  </div>
</template>
