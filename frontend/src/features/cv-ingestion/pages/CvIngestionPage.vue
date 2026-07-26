<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useRouter, useRoute, onBeforeRouteLeave } from 'vue-router'
import { ArrowLeft, Check, Upload, Cog, ListChecks, FileCheck, Sparkles } from '@lucide/vue'
import type { LucideIcon } from '@lucide/vue'
import { useCvIngestion } from '../composables/useCvIngestion'
import type { CvStage } from '../composables/useCvIngestion'
import type { ReviewDecision } from '../types'
import CvUploadZone from '../components/CvUploadZone.vue'
import CvDocumentList from '../components/CvDocumentList.vue'
import CvProcessingStatus from '../components/CvProcessingStatus.vue'
import CvReviewWorkspace from '../components/CvReviewWorkspace.vue'
import CvImportPreview from '../components/CvImportPreview.vue'
import CvImportResult from '../components/CvImportResult.vue'
import Button from '@/components/ui/Button.vue'
import Toast from '@/components/ui/Toast.vue'

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
  reviewReadonly,
  startImport,
  resumeReview,
  restoreFromParams,
  reset,
} = useCvIngestion()

const importBusy = computed(() => importMutation.isPending.value)
const toastVisible = ref(false)
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

// Restore state from URL query params on mount
onMounted(() => {
  const q = route.query as Record<string, string | undefined>
  if (q.documentId) {
    const docId = Number(q.documentId)
    if (!isNaN(docId) && q.stage && ['review', 'import', 'complete'].includes(q.stage)) {
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

// Sync state to URL query params
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
    toastVisible.value = true
    setTimeout(() => {
      toastVisible.value = false
    }, 3500)
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

onBeforeRouteLeave(() => {
  if (stage.value === 'review' && reviewProgress.value.reviewed > 0 && !allReviewed.value) {
    return window.confirm('You have unreviewed suggestions. Leave this page?')
  }
  return true
})
</script>

<template>
  <div class="mx-auto max-w-3xl">
    <!-- Stage progress indicator -->
    <nav aria-label="Import progress" class="mb-10 mt-2">
      <ol class="flex items-center justify-between">
        <li
          v-for="(step, i) in visibleSteps"
          :key="step.key"
          class="flex flex-col items-center"
          :class="i < visibleSteps.length - 1 ? 'flex-1' : ''"
        >
          <div class="flex w-full items-center">
            <div
              :class="[
                'relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-500',
                i < currentStepIndex
                  ? 'border-emerald-500 bg-emerald-50'
                  : i === currentStepIndex
                    ? 'border-primary-500 bg-primary-50 shadow-lg shadow-primary-200/50'
                    : 'border-slate-200 bg-white',
              ]"
            >
              <Check
                v-if="i < currentStepIndex"
                class="h-5 w-5 text-emerald-600"
                aria-hidden="true"
              />
              <component
                :is="step.icon"
                v-else
                :class="[
                  'h-5 w-5',
                  i === currentStepIndex ? 'text-primary-600' : 'text-slate-300',
                  i === currentStepIndex && stage === 'processing' ? 'animate-spin' : '',
                ]"
                aria-hidden="true"
              />
            </div>
            <div
              v-if="i < visibleSteps.length - 1"
              :class="[
                'mx-2 h-0.5 flex-1 rounded-full transition-colors duration-500',
                i < currentStepIndex ? 'bg-emerald-400' : 'bg-slate-100',
              ]"
              aria-hidden="true"
            />
          </div>
          <span
            :class="[
              'mt-2 text-xs font-medium transition-colors duration-300',
              i === currentStepIndex
                ? 'text-primary-700'
                : i < currentStepIndex
                  ? 'text-emerald-700'
                  : 'text-slate-400',
            ]"
          >
            {{ step.label }}
          </span>
        </li>
      </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8 flex items-start justify-between">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
          {{ stageHeading.title }}
        </h1>
        <p class="mt-1.5 text-sm text-slate-500">
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
        <ArrowLeft class="mr-1 h-4 w-4" aria-hidden="true" />
        Start over
      </Button>
    </div>

    <!-- Stage content with transitions -->
    <Transition name="stage" mode="out-in">
      <div :key="stage" class="space-y-6">
        <!-- Choose mode -->
        <div v-if="stage === 'choose_mode'" class="space-y-4">
          <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">How would you like to proceed?</h2>
            <p class="mt-1.5 text-sm text-slate-500">
              Choose whether to create a new profile or update your existing one.
            </p>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
              <button
                class="group rounded-xl border-2 border-slate-200 bg-white p-5 text-left transition-all hover:border-primary-300 hover:bg-primary-50/50 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                @click="chooseMode('create_new')"
              >
                <div
                  class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-600 transition-colors group-hover:bg-primary-100"
                >
                  <Sparkles class="h-5 w-5" aria-hidden="true" />
                </div>
                <p class="font-semibold text-slate-900">Create new profile</p>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500">
                  Start fresh with a new profile built from your CV.
                </p>
              </button>
              <button
                class="group rounded-xl border-2 border-slate-200 bg-white p-5 text-left transition-all hover:border-primary-300 hover:bg-primary-50/50 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                @click="chooseMode('update_existing')"
              >
                <div
                  class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-600 transition-colors group-hover:bg-primary-100"
                >
                  <Upload class="h-5 w-5" aria-hidden="true" />
                </div>
                <p class="font-semibold text-slate-900">Update existing profile</p>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-500">
                  Merge CV data into your current profile.
                </p>
              </button>
            </div>
          </div>
          <CvDocumentList
            :highlight-id="duplicateCvId"
            @review="handleReviewCv"
            @view="handleViewCv"
          />
        </div>

        <!-- Upload -->
        <div v-if="stage === 'idle' || stage === 'upload'" class="space-y-4">
          <div
            v-if="uploadError"
            class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm shadow-sm"
            role="alert"
          >
            <div class="flex-1">
              <p class="font-medium text-amber-800">{{ uploadError.detail }}</p>
              <p v-if="uploadError.code === 'file_duplicate'" class="mt-1.5 text-amber-700">
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
              class="shrink-0 text-amber-500 hover:text-amber-700"
              aria-label="Dismiss error"
              @click="handleDismissError"
            >
              &times;
            </button>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2">
              <span class="text-xs font-medium uppercase tracking-wider text-slate-500">
                {{ uploadMode === 'create_new' ? 'Create new profile' : 'Update existing profile' }}
              </span>
              <button
                class="text-xs font-medium text-primary-600 hover:text-primary-800"
                @click="reset()"
              >
                Change
              </button>
            </div>
            <CvUploadZone @file-selected="handleFileSelected" />
          </div>
          <div
            v-if="isUploading"
            class="overflow-hidden rounded-full bg-slate-100"
            role="progressbar"
            :aria-valuenow="uploadProgress"
            aria-valuemin="0"
            aria-valuemax="100"
          >
            <div
              class="h-2 rounded-full bg-gradient-to-r from-primary-500 to-primary-400 transition-all duration-500 ease-out"
              :style="{ width: uploadProgress + '%' }"
            />
          </div>
        </div>

        <!-- Processing -->
        <div v-if="stage === 'processing' && document">
          <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <CvProcessingStatus
              :document="document"
              @retry="handleRetry"
              @upload-new="handleUploadNew"
              @proceed-to-review="handleProceedToReview"
            />
          </div>
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
          <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <CvImportPreview
              :preview="preview"
              :is-pending="importBusy"
              @confirm="handleConfirmImport"
              @go-back="handleBackToReview"
            />
          </div>
        </div>

        <!-- Complete -->
        <div v-if="stage === 'complete'">
          <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <CvImportResult
              :batch="importMutation.data.value ?? null"
              @upload-another="handleUploadAnother"
              @go-to-profile="handleGoToProfile"
            />
          </div>
        </div>
      </div>
    </Transition>

    <Toast :message="announcement" :visible="toastVisible" @close="toastVisible = false" />
  </div>
</template>

<style scoped>
.stage-enter-active {
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.stage-leave-active {
  transition: all 0.2s cubic-bezier(0.55, 0, 1, 0.45);
}
.stage-enter-from {
  opacity: 0;
  transform: translateY(16px);
}
.stage-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
