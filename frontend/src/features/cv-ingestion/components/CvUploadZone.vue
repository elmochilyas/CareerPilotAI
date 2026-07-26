<script setup lang="ts">
import { ref } from 'vue'
import { Upload, FileText, AlertCircle } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'

const ALLOWED_TYPES = [
  'application/pdf',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
]
const ALLOWED_EXTENSIONS = ['.pdf', '.docx']
const MAX_SIZE = 20 * 1024 * 1024

const emit = defineEmits<{
  fileSelected: [file: File]
}>()

const dropActive = ref(false)
const selectedFile = ref<File | null>(null)
const error = ref('')
const fileInput = ref<HTMLInputElement | null>(null)

function validate(file: File): boolean {
  error.value = ''
  if (!ALLOWED_TYPES.includes(file.type)) {
    const ext = '.' + file.name.split('.').pop()?.toLowerCase()
    if (!ALLOWED_EXTENSIONS.includes(ext)) {
      error.value = 'Only PDF and DOCX files are supported.'
      return false
    }
  }
  if (file.size > MAX_SIZE) {
    const mb = Math.round(MAX_SIZE / (1024 * 1024))
    error.value = `File exceeds the maximum size of ${mb} MB.`
    return false
  }
  return true
}

function onFilePick(e: Event): void {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (file && validate(file)) {
    selectedFile.value = file
  }
}

function onDrop(e: DragEvent): void {
  dropActive.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file && validate(file)) {
    selectedFile.value = file
  }
}

function onDragOver(e: DragEvent): void {
  e.preventDefault()
  dropActive.value = true
}

function onDragLeave(): void {
  dropActive.value = false
}

function openFilePicker(): void {
  fileInput.value?.click()
}

function clearSelection(): void {
  selectedFile.value = null
  error.value = ''
  if (fileInput.value) fileInput.value.value = ''
}

function confirm(): void {
  if (selectedFile.value) emit('fileSelected', selectedFile.value)
}

const sizeLabel = (bytes: number): string => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<template>
  <div class="space-y-4">
    <div
      role="button"
      tabindex="0"
      :class="[
        'relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors',
        dropActive
          ? 'border-blue-500 bg-blue-50'
          : 'border-slate-300 bg-slate-50 hover:border-slate-400',
      ]"
      @click="openFilePicker"
      @keydown.enter="openFilePicker"
      @keydown.space.prevent="openFilePicker"
      @drop.prevent="onDrop"
      @dragover.prevent="onDragOver"
      @dragleave="onDragLeave"
      aria-label="Upload your CV or resume. Drop a file or click to browse."
    >
      <input
        ref="fileInput"
        type="file"
        accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        class="sr-only"
        @change="onFilePick"
        aria-hidden="true"
      />
      <Upload class="mb-3 h-10 w-10 text-slate-400" aria-hidden="true" />
      <p class="text-sm font-medium text-slate-700">Drop your CV here or click to browse</p>
      <p class="mt-1 text-xs text-slate-500">PDF or DOCX up to 20 MB</p>
    </div>

    <div
      v-if="error"
      role="alert"
      class="flex items-start gap-2 rounded-md bg-red-50 p-3 text-sm text-red-700"
    >
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
      <span>{{ error }}</span>
    </div>

    <div v-if="selectedFile" class="rounded-lg border border-slate-200 bg-white p-4">
      <div class="flex items-start justify-between">
        <div class="flex items-start gap-3">
          <FileText class="mt-0.5 h-5 w-5 text-slate-500" aria-hidden="true" />
          <div>
            <p class="text-sm font-medium text-slate-900">{{ selectedFile.name }}</p>
            <p class="text-xs text-slate-500">{{ sizeLabel(selectedFile.size) }}</p>
          </div>
        </div>
        <button
          type="button"
          class="text-sm text-slate-400 hover:text-slate-600"
          @click="clearSelection"
          aria-label="Remove selected file"
        >
          &times;
        </button>
      </div>
      <div class="mt-3 flex justify-end">
        <Button size="sm" @click="confirm">Upload CV</Button>
      </div>
    </div>
  </div>
</template>
