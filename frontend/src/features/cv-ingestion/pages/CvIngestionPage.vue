<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useRouter, useRoute, onBeforeRouteLeave } from 'vue-router'
import { ArrowLeft, Check, Upload, Cog, ListChecks, FileCheck, Sparkles } from '@lucide/vue'
import type { LucideIcon } from '@lucide/vue'
import { useCvIngestion } from '../composables/useCvIngestion'
import type { CvStage } from '../composables/useCvIngestion'
import type { ReviewDecision, CvDocument } from '../types'
import CvUploadZone from '../components/CvUploadZone.vue'
import CvDocumentList from '../components/CvDocumentList.vue'
import CvProcessingStatus from '../components/CvProcessingStatus.vue'
import CvReviewWorkspace from '../components/CvReviewWorkspace.vue'
import CvImportPreview from '../components/CvImportPreview.vue'
import CvImportResult from '../components/CvImportResult.vue'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import Toast from '@/components/ui/Toast.vue'
import { useToast } from '@/composables/useToast'

const router = useRouter()
const route = useRoute()

const {
  stage,
  activeDocumentId,
  announcement,
  document,
  suggestions,
  preview,
  reviewProgress,
  allReviewed,
  uploadProgress,
  isUploading,
  uploadError,
  duplicateCvId,
  uploadMode,
  chooseMode,
  uploadMutation,
  retryMutation,
  isRetrying,
  deleteMutation,
  importMutation,
  updateSuggestionMutation,
  batchSaveMutation,
  documentQuery,
  previewQuery,
  reviewReadonly,
  startImport,
  resumeReview,
  resumeProcessing,
  restoreFromParams,
  reset,
} = useCvIngestion()

const { toast } = useToast()

const importBusy = computed(() => importMutation.isPending.value)
const activeStep = ref(0)

const applyPending = computed(() => importMutation.isPending.value)
const batchSavePending = computed(() => batchSaveMutation.isPending.value)
const batchSaveError = computed(() => batchSaveMutation.isError.value)
const updateSuggestionPending = computed(() => updateSuggestionMutation.isPending.value)
const documentReviewable = computed(() => document.value?.status === 'ready_for_review')

interface VisibleStep {
  key: string
  label: string
  icon: LucideIcon
  stageMatch: CvStage[]
}

const visibleSteps: VisibleStep[] = [
  { key: 'mode', label: 'Mode', icon: Sparkles, stageMatch: ['choose_mode'] },
  { key: 'upload', label: 'Upload', icon: Upload, stageMatch: ['idle', 'upload'] },
  { key: 'process', label: 'Process', icon: Cog, stageMatch: ['processing'] },
  { key: 'review', label: 'Review', icon: ListChecks, stageMatch: ['review'] },
  { key: 'import', label: 'Import', icon: FileCheck, stageMatch: ['import'] },
  { key: 'done', label: 'Done', icon: Check, stageMatch: ['complete'] },
]

const currentStepIndex = computed(() => {
  const s = stage.value
  const idx = visibleSteps.findIndex((step) => step.stageMatch.includes(s))
  return idx >= 0 ? idx : 0
})

const stageHeading = computed(() => {
  const map: Record<string, { title: string; subtitle: string }> = {
    choose_mode: {
      title: 'Import CV',
      subtitle: 'Upload your CV to extract information and update your profile.',
    },
    idle: { title: 'Upload your CV', subtitle: 'Select a PDF or DOCX file to get started.' },
    upload: { title: 'Uploading...', subtitle: 'Your file is being uploaded.' },
    processing: {
      title: 'Processing your CV',
      subtitle: "We're analyzing your document to extract information.",
    },
    review: {
      title: 'Review extracted information',
      subtitle: 'Review each section and decide what to import.',
    },
    import: {
      title: 'Ready to import',
      subtitle: 'Confirm the changes before applying them to your profile.',
    },
    complete: { title: 'Import complete', subtitle: 'Your profile has been updated.' },
  }
  return map[stage.value] ?? { title: 'Import CV', subtitle: '' }
})

onMounted(() => {
  const q = route.query as Record<string, string | undefined>
  if (q.documentId) {
    const docId = Number(q.documentId)
    if (
      !isNaN(docId) &&
      q.stage &&
      ['review', 'import', 'complete', 'processing'].includes(q.stage)
    ) {
      restoreFromParams({
        documentId: docId,
        stage: q.stage as CvStage,
        readonly: q.readonly === '1',
      })
      if (q.step) {
        const stepIdx = Number(q.step)
        if (!isNaN(stepIdx) && stepIdx >= 0) {
          activeStep.value = stepIdx
        }
      }
    }
  }
})

watch([stage, activeDocumentId, reviewReadonly, activeStep], () => {
  const query: Record<string, string> = {}
  if (activeDocumentId.value && stage.value !== 'choose_mode') {
    query.documentId = String(activeDocumentId.value)
    query.stage = stage.value
    if (reviewReadonly.value) query.readonly = '1'
    if (activeStep.value > 0) query.step = String(activeStep.value)
  }
  router.replace({ query })
})

watch(
  () => announcement.value,
  (msg) => {
    if (!msg) return
    toast.success(msg)
  },
)

function handleFileSelected(file: File): void {
  if (uploadError.value) uploadError.value = null
  uploadMutation.mutate(file)
}

function handleDismissError(): void {
  uploadError.value = null
}

function handleViewExisting(): void {
  router.push({ name: 'profile' })
}

function handleRetry(): void {
  if (document.value && !isRetrying.value) {
    retryMutation.mutate(document.value.id)
  }
}

function handleProceedToReview(): void {
  stage.value = 'review'
  documentQuery.refetch()
}

function handleUploadNew(): void {
  deleteMutation.mutate(document.value!.id)
}

function handleSuggestionDecision(suggestionId: number, decision: ReviewDecision): void {
  if (document.value) {
    updateSuggestionMutation.mutate({
      documentId: document.value.id,
      suggestionId,
      decision,
    })
  }
}

function handleBatchSave(
  decisions: Array<{ id: number; decision: ReviewDecision['decision'] }>,
): void {
  if (document.value && decisions.length > 0) {
    batchSaveMutation.mutate({ documentId: document.value.id, decisions })
  }
}

function handleProceedToImport(): void {
  startImport()
}

function handleConfirmImport(): void {
  if (document.value) importMutation.mutate(document.value.id)
}

function handleGoToProfile(): void {
  router.push({ name: 'profile' })
}

function handleUploadAnother(): void {
  reset()
}

function handleBackToReview(): void {
  stage.value = 'review'
}

function handleReviewCv(id: number): void {
  resumeReview(id)
}

function handleViewCv(id: number): void {
  resumeReview(id, true)
}

function handleTrackCv(id: number): void {
  resumeProcessing(id)
}

function handleRetryFromList(id: number): void {
  activeDocumentId.value = id
  stage.value = 'processing'
  retryMutation.mutate(id)
}

function handleOpenDocument(doc: CvDocument): void {
  if (doc.status === 'ready_for_review') {
    handleReviewCv(doc.id)
  } else if (doc.status === 'imported') {
    handleViewCv(doc.id)
  } else {
    handleTrackCv(doc.id)
  }
}

onBeforeRouteLeave(() => {
  if (stage.value === 'review' && reviewProgress.value.reviewed > 0 && !allReviewed.value) {
    return window.confirm('You have unreviewed suggestions. Leave this page?')
  }
  return true
})
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6 py-8">
    <!-- Horizontal step indicator -->
    <nav aria-label="Import progress" class="px-2">
      <ol class="flex items-center gap-0">
        <li
          v-for="(step, i) in visibleSteps"
          :key="step.key"
          class="flex items-center"
          :class="i < visibleSteps.length - 1 ? 'flex-1' : ''"
        >
          <div class="flex w-full items-center">
            <div
              :class="[
                'relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-300',
                i < currentStepIndex
                  ? 'border-[var(--color-primary-600)] bg-[var(--color-primary-600)] text-white shadow-sm'
                  : i === currentStepIndex
                    ? 'border-[var(--color-primary-600)] bg-white text-[var(--color-primary-600)] shadow-sm ring-4 ring-[var(--color-primary-50)]'
                    : 'border-[var(--color-neutral-200)] bg-white text-[var(--text-muted)]',
              ]"
            >
              <Check
                v-if="i < currentStepIndex"
                class="size-4"
                stroke-width="3"
                aria-hidden="true"
              />
              <component
                :is="step.icon"
                v-else
                :class="[
                  'size-4',
                  i === currentStepIndex && stage === 'processing' ? 'animate-spin' : '',
                ]"
                aria-hidden="true"
              />
            </div>
            <div
              v-if="i < visibleSteps.length - 1"
              :class="[
                'mx-2 h-0.5 flex-1 rounded-full transition-colors duration-300',
                i < currentStepIndex
                  ? 'bg-[var(--color-primary-600)]'
                  : 'bg-[var(--color-neutral-200)]',
              ]"
              aria-hidden="true"
            />
          </div>
          <span
            :class="[
              'absolute -bottom-5 left-1/2 -translate-x-1/2 whitespace-nowrap text-[11px] font-medium',
              i === currentStepIndex
                ? 'text-[var(--color-primary-700)]'
                : 'text-[var(--text-muted)]',
            ]"
          >
            {{ step.label }}
          </span>
        </li>
      </ol>
    </nav>

    <!-- Header -->
    <div class="flex items-start justify-between pt-4">
      <div>
        <h1 class="text-[var(--text-2xl)] font-semibold tracking-tight text-[var(--text-primary)]">
          {{ stageHeading.title }}
        </h1>
        <p class="mt-1 text-sm text-[var(--text-secondary)]">
          {{ stageHeading.subtitle }}
        </p>
      </div>
      <Button
        v-if="stage !== 'choose_mode' && stage !== 'idle'"
        variant="ghost"
        size="sm"
        class="shrink-0"
        @click="reset"
      >
        <ArrowLeft class="mr-1 size-4" aria-hidden="true" />
        Start over
      </Button>
    </div>

    <!-- Stage content with transitions -->
    <Transition name="stage" mode="out-in">
      <div :key="stage" class="space-y-5">
        <!-- Choose mode -->
        <div v-if="stage === 'choose_mode'" class="space-y-5">
          <Card padding="lg">
            <div class="space-y-2">
              <h2 class="text-base font-semibold text-slate-900">How would you like to proceed?</h2>
              <p class="text-sm text-slate-500">
                Choose whether to create a new profile or update your existing one.
              </p>
            </div>
            <div class="mt-6 grid gap-3 sm:grid-cols-2">
              <button
                class="group flex items-start gap-3 rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-4 text-left transition-all duration-200 hover:border-[var(--color-primary-300)] hover:bg-[var(--color-primary-50)]/30 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/40"
                @click="chooseMode('create_new')"
              >
                <div
                  class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary-50)] text-[var(--color-primary-600)] transition-colors duration-200 group-hover:bg-[var(--color-primary-100)]"
                >
                  <Sparkles class="size-4.5" aria-hidden="true" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-[var(--text-primary)]">Create new profile</p>
                  <p class="mt-0.5 text-xs leading-relaxed text-[var(--text-secondary)]">
                    Start fresh with a new profile built from your CV.
                  </p>
                </div>
              </button>
              <button
                class="group flex items-start gap-3 rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-4 text-left transition-all duration-200 hover:border-[var(--color-primary-300)] hover:bg-[var(--color-primary-50)]/30 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/40"
                @click="chooseMode('update_existing')"
              >
                <div
                  class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary-50)] text-[var(--color-primary-600)] transition-colors duration-200 group-hover:bg-[var(--color-primary-100)]"
                >
                  <Upload class="size-4.5" aria-hidden="true" />
                </div>
                <div>
                  <p class="text-sm font-semibold text-[var(--text-primary)]">
                    Update existing profile
                  </p>
                  <p class="mt-0.5 text-xs leading-relaxed text-[var(--text-secondary)]">
                    Merge CV data into your current profile.
                  </p>
                </div>
              </button>
            </div>
          </Card>
          <CvDocumentList
            :highlight-id="duplicateCvId"
            @review="handleReviewCv"
            @view="handleViewCv"
            @open="handleOpenDocument"
            @track="handleTrackCv"
            @retry="handleRetryFromList"
          />
        </div>

        <!-- Upload -->
        <div v-if="stage === 'idle' || stage === 'upload'" class="space-y-4">
          <div
            v-if="uploadError"
            class="flex items-start gap-3 rounded-[var(--radius-xl)] border border-[var(--color-warning-100)] bg-[var(--color-warning-50)] p-4 text-sm"
            role="alert"
          >
            <div class="flex-1">
              <p class="font-medium text-[var(--color-warning-800)]">{{ uploadError.detail }}</p>
              <p
                v-if="uploadError.code === 'file_duplicate'"
                class="mt-1.5 text-[var(--color-warning-700)]"
              >
                <button
                  class="font-medium underline hover:text-amber-900"
                  @click="handleViewExisting"
                >
                  View existing CV
                </button>
                <span class="mx-2">&middot;</span>
                <button
                  class="font-medium underline hover:text-amber-900"
                  @click="handleDismissError"
                >
                  Try a different file
                </button>
              </p>
            </div>
            <button
              class="shrink-0 text-[var(--color-warning-500)] hover:text-[var(--color-warning-700)]"
              aria-label="Dismiss error"
              @click="handleDismissError"
            >
              &times;
            </button>
          </div>
          <Card padding="lg">
            <div
              class="mb-4 flex items-center gap-2 rounded-lg bg-[var(--surface-secondary)] px-3 py-2"
            >
              <span
                class="text-xs font-medium uppercase tracking-wider text-[var(--text-secondary)]"
              >
                {{ uploadMode === 'create_new' ? 'Create new profile' : 'Update existing profile' }}
              </span>
              <button
                class="text-xs font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-800)]"
                @click="reset()"
              >
                Change
              </button>
            </div>
            <CvUploadZone @file-selected="handleFileSelected" />
          </Card>
          <div
            v-if="isUploading"
            class="overflow-hidden rounded-full bg-[var(--color-neutral-100)]"
            role="progressbar"
            :aria-valuenow="uploadProgress"
            aria-valuemin="0"
            aria-valuemax="100"
          >
            <div
              class="h-1.5 rounded-full bg-[var(--color-primary-600)] transition-[transform] duration-500 ease-out origin-left"
              :style="{ transform: `scaleX(${uploadProgress / 100})` }"
            />
          </div>
        </div>

        <!-- Processing -->
        <div v-if="stage === 'processing' && document">
          <Card padding="lg">
            <CvProcessingStatus
              :document="document"
              @retry="handleRetry"
              @upload-new="handleUploadNew"
              @proceed-to-review="handleProceedToReview"
            />
          </Card>
        </div>

        <!-- Review -->
        <div v-if="stage === 'review'">
          <CvReviewWorkspace
            :suggestions="suggestions"
            :preview="preview"
            :all-reviewed="allReviewed"
            :readonly="reviewReadonly"
            :initial-step="activeStep"
            :apply-pending="applyPending"
            :update-suggestion-pending="updateSuggestionPending"
            :batch-save-pending="batchSavePending"
            :batch-save-error="batchSaveError"
            :document-reviewable="documentReviewable"
            @decision="handleSuggestionDecision"
            @batch-save="handleBatchSave"
            @proceed-to-import="handleProceedToImport"
            @upload-new="handleUploadNew"
            @step-change="activeStep = $event"
          />
        </div>

        <!-- Import preview -->
        <div v-if="stage === 'import'">
          <Card padding="lg">
            <CvImportPreview
              :preview="preview"
              :is-pending="previewQuery.isPending.value"
              :is-applying="importBusy"
              @confirm="handleConfirmImport"
              @go-back="handleBackToReview"
            />
          </Card>
        </div>

        <!-- Complete -->
        <div v-if="stage === 'complete'">
          <Card padding="lg">
            <CvImportResult
              :batch="importMutation.data.value ?? null"
              @upload-another="handleUploadAnother"
              @go-to-profile="handleGoToProfile"
            />
          </Card>
        </div>
      </div>
    </Transition>

    <Toast />
  </div>
</template>

<style scoped>
.stage-enter-active {
  transition:
    opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1),
    transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.stage-leave-active {
  transition:
    opacity 0.15s cubic-bezier(0.55, 0, 1, 0.45),
    transform 0.15s cubic-bezier(0.55, 0, 1, 0.45);
}
.stage-enter-from {
  opacity: 0;
  transform: translateY(12px);
}
.stage-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
