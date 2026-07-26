<script setup lang="ts">
import { computed } from 'vue'
import { FileText, Loader2, CheckCircle2, AlertCircle, RotateCw } from '@lucide/vue'
import type { CvDocument } from '../types'
import { FAILURE_MESSAGES } from '../types'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  document: CvDocument
}>()

const emit = defineEmits<{
  retry: []
  uploadNew: []
  proceedToReview: []
}>()

const steps = [
  { key: 'validating', label: 'Validating file' },
  { key: 'extracting', label: 'Extracting text' },
  { key: 'analyzing', label: 'Analyzing with AI' },
  { key: 'ready_for_review', label: 'Preparing suggestions' },
] as const

const processingStatuses = new Set(['pending', 'queued', 'validating', 'extracting', 'analyzing'])

const isProcessing = computed(() => processingStatuses.has(props.document.status))

const currentStepIndex = computed(() => {
  const status = props.document.status
  if (status === 'failed') return -1
  const map: Record<string, number> = {
    pending: 0,
    queued: 0,
    validating: 0,
    extracting: 1,
    analyzing: 2,
    ready_for_review: 3,
    importing: 3,
    imported: 3,
  }
  return map[status] ?? 0
})

const stepStatus = (index: number): 'pending' | 'active' | 'done' | 'error' => {
  if (props.document.status === 'failed') {
    return index === 0 ? 'error' : 'pending'
  }
  if (index < currentStepIndex.value) return 'done'
  if (index === currentStepIndex.value) return 'active'
  return 'pending'
}

const stepLabel = (index: number): string => {
  const status = stepStatus(index)
  if (status === 'done') return 'Complete'
  if (status === 'active') return 'In progress'
  if (status === 'error') return 'Failed'
  return 'Pending'
}

const stepMessage = computed(() => {
  const status = props.document.status
  if (status === 'pending' || status === 'queued')
    return 'Your CV is queued and will start processing shortly.'
  if (status === 'validating') return 'Checking file integrity and format...'
  if (status === 'extracting') return 'Reading text content from your document...'
  if (status === 'analyzing') return 'AI is analyzing your experience, skills, and qualifications.'
  return ''
})

const failureMessage = computed(() => {
  const code = props.document.failure_code
  if (code && code in FAILURE_MESSAGES) {
    return FAILURE_MESSAGES[code as keyof typeof FAILURE_MESSAGES]
  }
  return props.document.failure_reason ?? FAILURE_MESSAGES.unknown
})

const fileName = computed(() => props.document.original_name)

const fileSizeLabel = computed(() => {
  const bytes = props.document.size
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
})

const fileTypeLabel = computed(() => {
  const mime = props.document.mime_type
  if (mime === 'application/pdf') return 'PDF'
  if (mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
    return 'DOCX'
  return mime
})
</script>

<template>
  <div role="status" aria-live="polite">
    <div class="space-y-6">
      <!-- Status header -->
      <div class="text-center">
        <div
          v-if="isProcessing"
          class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50"
        >
          <Loader2 class="h-7 w-7 animate-spin text-primary-600" aria-hidden="true" />
        </div>
        <h2 class="text-lg font-semibold text-slate-900">
          <template v-if="isProcessing">Processing your CV</template>
          <template v-else-if="document.status === 'failed'">Processing failed</template>
          <template v-else>CV ready for review</template>
        </h2>
        <p v-if="isProcessing" class="mt-1 text-sm text-slate-500">
          {{ stepMessage }}
        </p>
      </div>

      <!-- File info -->
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white shadow-sm"
          >
            <FileText class="h-5 w-5 text-slate-500" aria-hidden="true" />
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-slate-900" :title="fileName">
              {{ fileName }}
            </p>
            <p class="text-xs text-slate-500">
              {{ fileSizeLabel }}
              <span class="mx-1">&middot;</span>
              {{ fileTypeLabel }}
            </p>
          </div>
        </div>
      </div>

      <!-- Processing steps -->
      <nav aria-label="Processing steps" class="space-y-0">
        <div
          v-for="(step, i) in steps"
          :key="step.key"
          class="relative flex items-start gap-4 pb-8 last:pb-0"
        >
          <div
            v-if="i < steps.length - 1"
            class="absolute left-[15px] top-8 h-full w-0.5"
            :class="[stepStatus(i) === 'done' ? 'bg-emerald-300' : 'bg-slate-200']"
            aria-hidden="true"
          />
          <div
            class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full transition-all duration-500"
            :class="{
              'bg-emerald-500 shadow-sm shadow-emerald-200': stepStatus(i) === 'done',
              'bg-primary-500 shadow-lg shadow-primary-200/50': stepStatus(i) === 'active',
              'bg-red-500': stepStatus(i) === 'error',
              'bg-slate-200': stepStatus(i) === 'pending',
            }"
            aria-hidden="true"
          >
            <CheckCircle2 v-if="stepStatus(i) === 'done'" class="h-4 w-4 text-white" />
            <div
              v-else-if="stepStatus(i) === 'active'"
              class="h-3 w-3 rounded-full bg-white animate-pulse"
            />
            <AlertCircle v-else-if="stepStatus(i) === 'error'" class="h-4 w-4 text-white" />
            <div v-else class="h-2.5 w-2.5 rounded-full bg-slate-400" />
          </div>
          <div class="flex flex-col justify-center pt-1">
            <span
              class="text-sm font-medium"
              :class="{
                'text-emerald-700': stepStatus(i) === 'done',
                'text-slate-900': stepStatus(i) === 'active',
                'text-red-700': stepStatus(i) === 'error',
                'text-slate-400': stepStatus(i) === 'pending',
              }"
            >
              {{ step.label }}
            </span>
            <span class="text-xs text-slate-400">
              {{ stepLabel(i) }}
            </span>
          </div>
        </div>
      </nav>

      <!-- Processing info -->
      <div v-if="isProcessing" class="rounded-lg border border-primary-100 bg-primary-50/50 p-4">
        <div class="flex items-start gap-3">
          <Loader2
            class="mt-0.5 h-4 w-4 shrink-0 animate-spin text-primary-600"
            aria-hidden="true"
          />
          <div>
            <p class="text-sm font-medium text-primary-800">Analysing your CV content</p>
            <p class="mt-0.5 text-sm text-primary-600">
              This usually takes a few seconds. We'll notify you when it's ready.
            </p>
          </div>
        </div>
      </div>

      <!-- Failure -->
      <div
        v-else-if="document.status === 'failed'"
        class="rounded-xl border border-red-200 bg-red-50 p-5"
        role="alert"
      >
        <div class="flex items-start gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100">
            <AlertCircle class="h-5 w-5 text-red-600" aria-hidden="true" />
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold text-red-800">Something went wrong</p>
            <p class="mt-1 text-sm leading-relaxed text-red-700">
              {{ failureMessage }}
            </p>
          </div>
        </div>
      </div>

      <!-- Ready -->
      <div
        v-else-if="document.status === 'ready_for_review'"
        class="rounded-xl border border-emerald-200 bg-emerald-50 p-5"
      >
        <div class="flex items-start gap-3">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100"
          >
            <CheckCircle2 class="h-5 w-5 text-emerald-600" aria-hidden="true" />
          </div>
          <div class="flex-1">
            <p class="text-sm font-semibold text-emerald-800">Extraction complete</p>
            <p class="mt-0.5 text-sm leading-relaxed text-emerald-700">
              Your CV has been processed successfully. Review the suggested changes before
              importing.
            </p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap gap-3">
        <Button
          v-if="document.status === 'failed'"
          variant="primary"
          size="sm"
          @click="emit('retry')"
        >
          <RotateCw class="h-4 w-4" aria-hidden="true" />
          Try again
        </Button>
        <Button
          v-if="document.status === 'failed'"
          variant="outline"
          size="sm"
          @click="emit('uploadNew')"
        >
          Upload new file
        </Button>
        <Button
          v-if="document.status === 'ready_for_review'"
          variant="primary"
          size="sm"
          :class="[
            'relative overflow-hidden',
            'after:absolute after:inset-0 after:rounded-lg after:bg-white/20 after:opacity-0 hover:after:opacity-100 after:transition-opacity',
          ]"
          @click="emit('proceedToReview')"
        >
          <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
          Review suggestions
        </Button>
      </div>
    </div>
  </div>
</template>
