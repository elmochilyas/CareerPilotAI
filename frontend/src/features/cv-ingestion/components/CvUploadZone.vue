<script setup lang="ts">
import { ref } from 'vue'
import { Upload, FileText, AlertCircle, CheckCircle2 } from '@lucide/vue'
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

const fileExtension = (name: string): string => {
  const ext = name.split('.').pop()?.toUpperCase()
  return ext ?? ''
}
</script>

<template>
  <div class="space-y-4">
    <!-- Drop zone -->
    <div
      role="button"
      tabindex="0"
      :class="[
        'group relative flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-10 transition-all duration-300',
        dropActive
          ? 'border-primary-400 bg-primary-50/80 shadow-lg shadow-primary-200/30'
          : selectedFile
            ? 'border-emerald-300 bg-emerald-50/50'
            : 'border-slate-300 bg-slate-50 hover:border-primary-300 hover:bg-primary-50/30 hover:shadow-md',
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

      <div
        :class="[
          'mb-4 flex h-14 w-14 items-center justify-center rounded-xl transition-all duration-300',
          dropActive
            ? 'bg-primary-100 text-primary-600 scale-110'
            : selectedFile
              ? 'bg-emerald-100 text-emerald-600'
              : 'bg-slate-100 text-slate-400 group-hover:bg-primary-50 group-hover:text-primary-500',
        ]"
      >
        <Upload
          v-if="!selectedFile"
          class="h-7 w-7 transition-transform duration-300 group-hover:scale-110"
          aria-hidden="true"
        />
        <CheckCircle2 v-else class="h-7 w-7 text-emerald-600" aria-hidden="true" />
      </div>

      <p v-if="!selectedFile" class="text-sm font-medium text-slate-700">
        <span class="text-primary-600">Click to upload</span>
        or drag and drop
      </p>
      <p v-else class="text-sm font-medium text-emerald-800">File selected — ready to upload</p>
      <p class="mt-1 text-xs text-slate-400">PDF or DOCX up to 20 MB</p>

      <div class="mt-4 flex items-center gap-2">
        <span
          class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-500"
        >
          <FileText class="h-3 w-3" aria-hidden="true" /> PDF
        </span>
        <span
          class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-500"
        >
          <FileText class="h-3 w-3" aria-hidden="true" /> DOCX
        </span>
      </div>
    </div>

    <!-- Error message -->
    <div
      v-if="error"
      role="alert"
      class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
    >
      <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
      <span>{{ error }}</span>
    </div>

    <!-- Selected file preview -->
    <div
      v-if="selectedFile"
      class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all"
    >
      <div class="flex items-start gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-50">
          <FileText class="h-6 w-6 text-primary-600" aria-hidden="true" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-slate-900" :title="selectedFile.name">
            {{ selectedFile.name }}
          </p>
          <div class="mt-1 flex items-center gap-2 text-xs text-slate-500">
            <span>{{ sizeLabel(selectedFile.size) }}</span>
            <span aria-hidden="true">&middot;</span>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-600">
              {{ fileExtension(selectedFile.name) }}
            </span>
          </div>
        </div>
        <button
          type="button"
          class="shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
          @click="clearSelection"
          aria-label="Remove selected file"
        >
          <span class="text-lg leading-none">&times;</span>
        </button>
      </div>
      <div class="mt-4 flex justify-end border-t border-slate-100 pt-4">
        <Button size="sm" @click="confirm">
          <Upload class="mr-1.5 h-4 w-4" aria-hidden="true" />
          Upload CV
        </Button>
      </div>
    </div>
  </div>
</template>
