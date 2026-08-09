<script setup lang="ts">
import { Upload } from '@lucide/vue'
import { ref } from 'vue'

const props = defineProps<{
  accept?: string
  maxSize?: number
  disabled?: boolean
}>()

const emit = defineEmits<{
  file: [file: File]
}>()

const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const error = ref<string | null>(null)

function openBrowse() {
  fileInput.value?.click()
}

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) validateAndEmit(file)
  if (fileInput.value) fileInput.value.value = ''
}

function onDragOver(e: DragEvent) {
  e.preventDefault()
  if (!props.disabled) isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
  if (props.disabled) return
  const file = e.dataTransfer?.files?.[0]
  if (file) validateAndEmit(file)
}

function validateAndEmit(file: File) {
  error.value = null
  if (props.maxSize && file.size > props.maxSize) {
    error.value = `File exceeds max size of ${Math.round(props.maxSize / 1024 / 1024)}MB`
    return
  }
  emit('file', file)
}
</script>

<template>
  <div>
    <div
      class="flex flex-col items-center justify-center gap-2 rounded-xl p-8 text-center transition-all duration-200"
      :class="
        disabled
          ? 'cursor-not-allowed bg-[var(--surface-sunken)] text-[var(--text-muted)]'
          : isDragging
            ? 'bg-[var(--color-primary-50)] text-[var(--color-primary-600)] shadow-[var(--shadow-neo-button-pressed)]'
            : 'cursor-pointer bg-[var(--surface-secondary)] text-[var(--text-secondary)] shadow-[var(--shadow-neo-inset-lg)] hover:bg-[var(--color-primary-25)] hover:text-[var(--color-primary-600)]'
      "
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @drop="onDrop"
      @click="openBrowse"
    >
      <Upload class="size-8" aria-hidden="true" />
      <div>
        <p class="text-sm font-medium">
          <span class="text-[var(--color-primary-600)]">Click to browse</span>
          <span v-if="!disabled"> or drag and drop</span>
        </p>
        <p v-if="accept" class="mt-1 text-xs text-[var(--text-muted)]">Accepted: {{ accept }}</p>
      </div>
    </div>
    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      :disabled="disabled"
      class="hidden"
      @change="onFileChange"
    />
    <p v-if="error" class="mt-1.5 text-xs text-[var(--color-error-600)]" role="alert">
      {{ error }}
    </p>
  </div>
</template>
