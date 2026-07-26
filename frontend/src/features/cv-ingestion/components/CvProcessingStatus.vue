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

const stepIcon = (status: ReturnType<typeof stepStatus>) => {
  if (status === 'done') return CheckCircle2
  if (status === 'error') return AlertCircle
  if (status === 'active') return Loader2
  return undefined
}

const stepDotClass = (status: ReturnType<typeof stepStatus>) => {
  if (status === 'done') return 'bg-green-500'
  if (status === 'error') return 'bg-red-500'
  if (status === 'active') return 'bg-blue-500 ring-4 ring-blue-100'
  return 'bg-slate-300'
}

const stepLabelClass = (status: ReturnType<typeof stepStatus>) => {
  if (status === 'done') return 'text-green-700'
  if (status === 'error') return 'text-red-700'
  if (status === 'active') return 'text-blue-700 font-medium'
  return 'text-slate-400'
}

const connectorClass = (index: number) => {
  if (props.document.status === 'failed') {
    return index === 0 ? 'bg-red-300' : 'bg-slate-200'
  }
  if (index < currentStepIndex.value) return 'bg-green-400'
  return 'bg-slate-200'
}

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
      <h2 class="text-lg font-semibold text-slate-900">
        <template v-if="isProcessing">Processing CV</template>
        <template v-else-if="document.status === 'failed'">Processing failed</template>
        <template v-else>CV ready for review</template>
      </h2>

      <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-start gap-3">
          <FileText class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" aria-hidden="true" />
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

      <nav aria-label="Processing steps" class="space-y-0">
        <div
          v-for="(step, i) in steps"
          :key="step.key"
          class="relative flex items-start gap-4 pb-8 last:pb-0"
        >
          <div
            v-if="i < steps.length - 1"
            class="absolute left-[11px] top-6 h-full w-0.5"
            :class="connectorClass(i)"
            aria-hidden="true"
          />
          <div
            class="z-10 flex h-6 w-6 shrink-0 items-center justify-center rounded-full"
            :class="stepDotClass(stepStatus(i))"
            aria-hidden="true"
          >
            <component
              :is="stepIcon(stepStatus(i))"
              v-if="stepIcon(stepStatus(i))"
              :class="['h-3.5 w-3.5 text-white', stepStatus(i) === 'active' ? 'animate-spin' : '']"
            />
          </div>
          <div class="flex min-h-6 items-center pt-px">
            <span :class="['text-sm', stepLabelClass(stepStatus(i))]">
              {{ step.label }}
            </span>
          </div>
        </div>
      </nav>

      <div v-if="isProcessing" class="flex items-start gap-2 rounded-md bg-blue-50 p-3">
        <Loader2 class="mt-0.5 h-4 w-4 shrink-0 animate-spin text-blue-600" aria-hidden="true" />
        <p class="text-sm text-blue-800">
          Analysing your CV content. This usually takes a few seconds.
        </p>
      </div>

      <div
        v-else-if="document.status === 'failed'"
        class="rounded-lg border border-red-200 bg-red-50 p-4"
        role="alert"
      >
        <div class="flex items-start gap-3">
          <AlertCircle class="mt-0.5 h-5 w-5 shrink-0 text-red-600" aria-hidden="true" />
          <div class="flex-1">
            <p class="text-sm font-medium text-red-800">Something went wrong</p>
            <p class="mt-1 text-sm text-red-700">
              {{ failureMessage }}
            </p>
          </div>
        </div>
      </div>

      <div v-else class="flex items-start gap-2 rounded-md bg-green-50 p-3">
        <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-green-600" aria-hidden="true" />
        <p class="text-sm text-green-800">
          Your CV has been processed successfully. Review the suggested changes before importing.
        </p>
      </div>

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
          @click="emit('proceedToReview')"
        >
          <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
          Review suggestions
        </Button>
      </div>
    </div>
  </div>
</template>
