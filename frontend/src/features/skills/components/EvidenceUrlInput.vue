<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{ modelValue: { value: string; label: string } }>()
const emit = defineEmits<{
  'update:modelValue': [value: { value: string; label: string }]
  valid: [value: boolean]
}>()

const localValue = ref(props.modelValue.value)
const localLabel = ref(props.modelValue.label)
const error = ref('')

const unsafeSchemes = ['javascript:', 'data:', 'file:', 'vbscript:']

function validate(url: string): boolean {
  error.value = ''
  const trimmed = url.trim()
  if (!trimmed) {
    error.value = 'URL is required'
    emit('valid', false)
    return false
  }
  let parsed: URL
  try {
    parsed = new URL(trimmed)
  } catch {
    error.value = 'Invalid URL format'
    emit('valid', false)
    return false
  }
  const scheme = parsed.protocol
  if (unsafeSchemes.includes(scheme)) {
    error.value = 'URL scheme is not allowed'
    emit('valid', false)
    return false
  }
  if (scheme !== 'https:') {
    error.value = 'Only HTTPS URLs are allowed'
    emit('valid', false)
    return false
  }
  emit('valid', true)
  return true
}

function onInput() {
  validate(localValue.value)
  emit('update:modelValue', { value: localValue.value, label: localLabel.value })
}
</script>

<template>
  <div class="space-y-3">
    <div>
      <label class="block text-sm font-medium text-slate-700">URL</label>
      <input
        v-model="localValue"
        type="url"
        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm transition-all focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
        placeholder="https://example.com/certificate"
        :aria-describedby="error ? 'url-error' : undefined"
        :aria-invalid="!!error"
        @input="onInput"
      />
      <p v-if="error" id="url-error" class="mt-1 text-xs text-red-600" role="alert">{{ error }}</p>
    </div>
    <div>
      <label class="block text-sm font-medium text-slate-700">Label (optional)</label>
      <input
        v-model="localLabel"
        type="text"
        class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm transition-all focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
        placeholder="e.g. Professional Certificate"
        @input="onInput"
      />
    </div>
  </div>
</template>
