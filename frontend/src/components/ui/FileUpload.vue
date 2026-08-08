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
      class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-8 text-center transition-colors"
      :class="
        disabled
          ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400'
          : isDragging
            ? 'border-primary-400 bg-primary-50 text-primary-600'
            : 'cursor-pointer border-slate-300 bg-white text-slate-500 hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-600'
      "
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @drop="onDrop"
      @click="openBrowse"
    >
      <Upload class="size-8" aria-hidden="true" />
      <div>
        <p class="text-sm font-medium">
          <span class="text-primary-600">Click to browse</span>
          <span v-if="!disabled"> or drag and drop</span>
        </p>
        <p v-if="accept" class="mt-1 text-xs text-slate-400">Accepted: {{ accept }}</p>
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
    <p v-if="error" class="mt-1.5 text-xs text-red-600" role="alert">
      {{ error }}
    </p>
  </div>
</template>
