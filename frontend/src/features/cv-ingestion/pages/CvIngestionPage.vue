<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { useRouter, useRoute, onBeforeRouteLeave } from 'vue-router'
import { ArrowLeft } from '@lucide/vue'
import { useCvIngestion } from '../composables/useCvIngestion'
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

// Restore state from URL query params on mount
onMounted(() => {
  const q = route.query as Record<string, string | undefined>
  if (q.documentId) {
    const docId = Number(q.documentId)
    if (!isNaN(docId) && q.stage && ['review', 'import', 'complete'].includes(q.stage)) {
      restoreFromParams({
        documentId: docId,
        stage: q.stage,
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
  <div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Import CV</h1>
        <p class="mt-1 text-sm text-slate-500">
          Upload your CV to extract information and update your profile.
        </p>
      </div>
      <Button
        v-if="stage !== 'choose_mode' && stage !== 'idle'"
        variant="ghost"
        size="sm"
        @click="reset"
      >
        <ArrowLeft class="mr-1 h-4 w-4" aria-hidden="true" />
        Start over
      </Button>
    </div>

    <div v-if="stage === 'choose_mode'" class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">How would you like to proceed?</h2>
        <p class="mt-1 text-sm text-slate-500">
          Choose whether to create a new profile or update your existing one.
        </p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <button
            class="rounded-lg border-2 border-slate-200 p-4 text-left transition-colors hover:border-blue-400 hover:bg-blue-50"
            @click="chooseMode('create_new')"
          >
            <p class="font-medium text-slate-900">Create new profile</p>
            <p class="mt-1 text-sm text-slate-500">
              Start fresh with a new profile based on this CV.
            </p>
          </button>
          <button
            class="rounded-lg border-2 border-slate-200 p-4 text-left transition-colors hover:border-blue-400 hover:bg-blue-50"
            @click="chooseMode('update_existing')"
          >
            <p class="font-medium text-slate-900">Update existing profile</p>
            <p class="mt-1 text-sm text-slate-500">Merge CV data into your current profile.</p>
          </button>
        </div>
      </div>
    </div>

    <div v-if="stage === 'idle' || stage === 'upload'" class="space-y-4">
      <div
        v-if="uploadError"
        class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm"
        role="alert"
      >
        <div class="flex-1">
          <p class="font-medium text-amber-800">{{ uploadError.detail }}</p>
          <p v-if="uploadError.code === 'file_duplicate'" class="mt-1 text-amber-700">
            <button class="font-medium underline hover:text-amber-900" @click="handleViewExisting">
              View existing CV
            </button>
            <span class="mx-2">&middot;</span>
            <button class="font-medium underline hover:text-amber-900" @click="handleDismissError">
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
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500">
          Mode: {{ uploadMode === 'create_new' ? 'Create new profile' : 'Update existing profile' }}
          <button class="ml-2 text-xs text-blue-600 hover:text-blue-800" @click="reset()">
            Change
          </button>
        </p>
        <CvUploadZone @file-selected="handleFileSelected" />
      </div>
      <div
        v-if="isUploading"
        class="overflow-hidden rounded-full bg-slate-200"
        role="progressbar"
        :aria-valuenow="uploadProgress"
        aria-valuemin="0"
        aria-valuemax="100"
      >
        <div
          class="h-2 rounded-full bg-blue-600 transition-all duration-300"
          :style="{ width: uploadProgress + '%' }"
        />
      </div>
    </div>

    <div v-if="stage === 'processing' && document" class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <CvProcessingStatus
          :document="document"
          @retry="handleRetry"
          @upload-new="handleUploadNew"
          @proceed-to-review="handleProceedToReview"
        />
      </div>
    </div>

    <div v-if="stage === 'review'" class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
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
    </div>

    <div v-if="stage === 'import'" class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <CvImportPreview
          :preview="preview"
          :is-pending="importBusy"
          @confirm="handleConfirmImport"
          @go-back="handleBackToReview"
        />
      </div>
    </div>

    <div v-if="stage === 'complete'" class="space-y-4">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <CvImportResult
          :batch="importMutation.data.value ?? null"
          @upload-another="handleUploadAnother"
          @go-to-profile="handleGoToProfile"
        />
      </div>
    </div>

    <div class="pt-4">
      <CvDocumentList :highlight-id="duplicateCvId" @review="handleReviewCv" @view="handleViewCv" />
    </div>

    <Toast :message="announcement" :visible="toastVisible" @close="toastVisible = false" />
  </div>
</template>
