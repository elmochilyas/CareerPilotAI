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
import BackButton from '@/components/ui/BackButton.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Card from '@/components/ui/Card.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
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
const showLeaveConfirm = ref(false)
let pendingLeaveResolve: ((v: boolean) => void) | null = null
const choosing = ref<'create_new' | 'update_existing' | null>(null)

const isSyncingFromRoute = ref(false)
const prevStage = ref<CvStage>(stage.value)
const prevMode = ref(uploadMode.value)
const prevDocId = ref<number | null>(activeDocumentId.value)

function isWizardStage(s: CvStage): boolean {
  return ['choose_mode', 'idle', 'upload', 'processing', 'review', 'import', 'complete'].includes(s)
}

function buildWizardQuery(): Record<string, string> {
  const query: Record<string, string> = {}
  if (stage.value === 'choose_mode') {
    return query
  }
  if (!activeDocumentId.value) {
    query.stage = stage.value
    if (stage.value === 'idle' || stage.value === 'upload') {
      query.mode = uploadMode.value
    }
    return query
  }
  query.documentId = String(activeDocumentId.value)
  query.stage = stage.value
  if (stage.value === 'idle' || stage.value === 'upload') {
    query.mode = uploadMode.value
  }
  if (reviewReadonly.value) query.readonly = '1'
  if (activeStep.value > 0) query.step = String(activeStep.value)
  return query
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
function pushWizardQuery(): void {
  const query = buildWizardQuery()
  const stageChanged = prevStage.value !== stage.value
  const modeChanged = prevMode.value !== uploadMode.value
  const docChanged = prevDocId.value !== activeDocumentId.value
  if (stageChanged || modeChanged || docChanged) {
    router.push({ query })
  } else {
    router.replace({ query })
  }
  prevStage.value = stage.value
  prevMode.value = uploadMode.value
  prevDocId.value = activeDocumentId.value
}

// eslint-disable-next-line @typescript-eslint/no-unused-vars
function navigateStage(nextStage: CvStage, opts?: { mode?: string }): void {
  if (opts?.mode && (opts.mode === 'create_new' || opts.mode === 'update_existing')) {
    uploadMode.value = opts.mode as 'create_new' | 'update_existing'
  }
  stage.value = nextStage
}

function syncStageFromRoute(): void {
  const q = route.query as Record<string, string | undefined>
  const hasStage = typeof q.stage === 'string' && q.stage.length > 0
  const hasDoc = typeof q.documentId === 'string' && q.documentId.length > 0
  const hasMode = q.mode === 'create_new' || q.mode === 'update_existing'

  // choose_mode: empty query → reset
  if (!hasStage && !hasDoc && !hasMode) {
    if (stage.value !== 'choose_mode') {
      isSyncingFromRoute.value = true
      reset()
      activeStep.value = 0
      prevStage.value = 'choose_mode'
      prevMode.value = uploadMode.value
      prevDocId.value = null
    }
    return
  }

  // idle with mode (no documentId required) — handles choose_mode → idle Back/Forward and direct URL
  if (q.stage === 'idle' && hasMode) {
    const mode = q.mode as 'create_new' | 'update_existing'
    const docId = q.documentId ? Number(q.documentId) : null
    const docValid = docId !== null && !isNaN(docId)
    if (
      stage.value !== 'idle' ||
      uploadMode.value !== mode ||
      activeDocumentId.value !== (docValid ? docId! : null)
    ) {
      isSyncingFromRoute.value = true
      uploadMode.value = mode
      stage.value = 'idle'
      activeDocumentId.value = docValid ? docId! : null
      activeStep.value = 0
      prevStage.value = 'idle'
      prevMode.value = mode
      prevDocId.value = activeDocumentId.value
    }
    return
  }

  if (hasDoc) {
    const docId = Number(q.documentId)
    if (!isNaN(docId) && q.stage && isWizardStage(q.stage as CvStage)) {
      const readonly = q.readonly === '1'
      const stepIdx = q.step ? Number(q.step) : 0
      const nextStep = !isNaN(stepIdx) && stepIdx >= 0 ? stepIdx : 0
      const needsSync =
        activeDocumentId.value !== docId ||
        stage.value !== (q.stage as CvStage) ||
        reviewReadonly.value !== readonly ||
        activeStep.value !== nextStep
      if (needsSync) {
        isSyncingFromRoute.value = true
        restoreFromParams({
          documentId: docId,
          stage: q.stage as CvStage,
          readonly,
        })
        activeStep.value = nextStep
        prevStage.value = stage.value
        prevMode.value = uploadMode.value
        prevDocId.value = activeDocumentId.value
      }
    }
  }
}

function handleWizardBack(): void {
  if (stage.value === 'review' && activeStep.value > 0) {
    activeStep.value--
    return
  }
  if (window.history.length > 1) {
    router.back()
  } else {
    reset()
  }
}

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
  if (q.stage === 'idle' && (q.mode === 'create_new' || q.mode === 'update_existing')) {
    uploadMode.value = q.mode as 'create_new' | 'update_existing'
    stage.value = 'idle'
    if (q.documentId) {
      const docId = Number(q.documentId)
      if (!isNaN(docId)) activeDocumentId.value = docId
    }
    prevStage.value = 'idle'
    prevMode.value = q.mode as 'create_new' | 'update_existing'
    prevDocId.value = activeDocumentId.value
    return
  }
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
      prevStage.value = stage.value
      prevMode.value = uploadMode.value
      prevDocId.value = activeDocumentId.value
    }
  }
})

watch([stage, activeDocumentId, reviewReadonly, activeStep, uploadMode], () => {
  if (isSyncingFromRoute.value) {
    isSyncingFromRoute.value = false
    prevStage.value = stage.value
    prevMode.value = uploadMode.value
    prevDocId.value = activeDocumentId.value
    return
  }
  const query = buildWizardQuery()
  // avoid redundant navigation if query already matches route
  const routeQuery = route.query as Record<string, string | undefined>
  const qKeys = Object.keys(routeQuery)
    .filter((k) => routeQuery[k] !== undefined)
    .sort()
  const cKeys = Object.keys(query).sort()
  const same =
    qKeys.length === cKeys.length &&
    qKeys.every((k, i) => k === cKeys[i] && routeQuery[k] === query[k])
  if (same) {
    prevStage.value = stage.value
    prevMode.value = uploadMode.value
    prevDocId.value = activeDocumentId.value
    return
  }
  const stageChanged = prevStage.value !== stage.value
  const modeChanged = prevMode.value !== uploadMode.value
  const docChanged = prevDocId.value !== activeDocumentId.value
  if (stageChanged || modeChanged || docChanged) {
    router.push({ query })
  } else {
    router.replace({ query })
  }
  prevStage.value = stage.value
  prevMode.value = uploadMode.value
  prevDocId.value = activeDocumentId.value
})

watch(
  () => route.query,
  () => {
    const currentQuery = buildWizardQuery()
    const q = route.query as Record<string, string | undefined>
    const qKeys = Object.keys(q)
      .filter((k) => q[k] !== undefined)
      .sort()
      .join(',')
    const cKeys = Object.keys(currentQuery).sort().join(',')
    let isSame = qKeys === cKeys
    if (isSame) {
      for (const k of Object.keys(currentQuery)) {
        if (currentQuery[k] !== q[k]) {
          isSame = false
          break
        }
      }
    }
    if (isSame) return
    syncStageFromRoute()
  },
)

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

function onChoose(mode: 'create_new' | 'update_existing', event: MouseEvent): void {
  if (choosing.value) return
  choosing.value = mode
  const el = event.currentTarget as HTMLElement
  el.classList.add('scale-[0.98]', 'ring-4', 'ring-[var(--color-primary-50)]')
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const delay = prefersReduced ? 0 : 220
  window.setTimeout(() => {
    choosing.value = null
    el.classList.remove('scale-[0.98]', 'ring-4', 'ring-[var(--color-primary-50)]')
    chooseMode(mode)
  }, delay)
}

onBeforeRouteLeave(() => {
  if (stage.value === 'review' && reviewProgress.value.reviewed > 0 && !allReviewed.value) {
    if (showLeaveConfirm.value) return false
    showLeaveConfirm.value = true
    return new Promise<boolean>((resolve) => {
      pendingLeaveResolve = resolve
    })
  }
  return true
})

function confirmLeave(): void {
  showLeaveConfirm.value = false
  if (pendingLeaveResolve) {
    const r = pendingLeaveResolve
    pendingLeaveResolve = null
    r(true)
  }
}

function cancelLeave(): void {
  showLeaveConfirm.value = false
  if (pendingLeaveResolve) {
    const r = pendingLeaveResolve
    pendingLeaveResolve = null
    r(false)
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-4 py-8">
    <Transition name="back-fade">
      <div v-if="stage !== 'choose_mode'">
        <BackButton label="Back" @click="handleWizardBack" />
      </div>
    </Transition>
    <!-- Premium steps track — standalone for stages that don't own it (unified hero / upload card own theirs) -->
    <nav
      v-if="stage !== 'choose_mode' && stage !== 'idle' && stage !== 'upload'"
      aria-label="Import progress"
      class="rounded-[var(--radius-2xl)] border border-slate-200/70 bg-white p-3 shadow-[var(--shadow-neo-raised-sm)] sm:p-4"
    >
      <ol
        class="flex items-start gap-1 sm:gap-2"
        role="progressbar"
        :aria-valuenow="currentStepIndex + 1"
        :aria-valuemin="1"
        :aria-valuemax="visibleSteps.length"
      >
        <li
          v-for="(step, i) in visibleSteps"
          :key="step.key"
          :class="[
            'flex flex-col items-center text-center',
            i < visibleSteps.length - 1 ? 'flex-1' : '',
          ]"
          :aria-current="i === currentStepIndex ? 'step' : undefined"
        >
          <div class="flex w-full items-center justify-center">
            <div
              :class="[
                'relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-300 sm:size-10',
                i < currentStepIndex
                  ? 'border-[3px] border-[var(--color-primary-600)] bg-[var(--color-primary-600)] text-white shadow-sm'
                  : i === currentStepIndex
                    ? 'border-[3px] border-[var(--color-primary-600)] bg-white text-[var(--color-primary-600)] shadow-sm ring-4 ring-[var(--color-primary-50)] scale-[1.04]'
                    : 'border-2 border-[var(--color-neutral-200)] bg-slate-50 text-[var(--text-muted)]',
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
              class="relative mx-1.5 h-2 flex-1 overflow-hidden rounded-full border border-black/[0.03] bg-[var(--color-neutral-100)] sm:mx-2"
              aria-hidden="true"
            >
              <div
                class="h-full rounded-full bg-[var(--color-primary-600)] transition-[width] duration-500 ease-out-expo"
                :style="{ width: i < currentStepIndex ? '100%' : '0%' }"
              />
            </div>
          </div>
          <span
            :class="[
              'mt-2 whitespace-nowrap text-xs font-semibold tracking-wide sm:max-w-none',
              i === currentStepIndex
                ? 'text-[var(--color-primary-700)]'
                : i < currentStepIndex
                  ? 'text-[var(--color-primary-600)]'
                  : 'text-[var(--text-muted)]',
            ]"
          >
            {{ step.label }}
            <span v-if="i < currentStepIndex" class="sr-only"> completed</span>
            <span v-else-if="i === currentStepIndex" class="sr-only"> current step</span>
          </span>
        </li>
      </ol>
    </nav>

    <!-- Stage content with transitions -->
    <Transition name="stage" mode="out-in">
      <div :key="stage" class="space-y-5">
        <!-- Choose mode — premium split decision -->
        <div v-if="stage === 'choose_mode'" class="space-y-6">
          <section
            aria-labelledby="choose-heading"
            class="overflow-hidden rounded-[var(--radius-2xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised)]"
          >
            <div class="border-b border-slate-100 bg-white p-3 sm:p-4">
              <nav aria-label="Import progress">
                <ol
                  class="flex items-start gap-1 sm:gap-2"
                  role="progressbar"
                  :aria-valuenow="currentStepIndex + 1"
                  :aria-valuemin="1"
                  :aria-valuemax="visibleSteps.length"
                >
                  <li
                    v-for="(step, i) in visibleSteps"
                    :key="step.key"
                    :class="[
                      'flex flex-col items-center text-center',
                      i < visibleSteps.length - 1 ? 'flex-1' : '',
                    ]"
                    :aria-current="i === currentStepIndex ? 'step' : undefined"
                  >
                    <div class="flex w-full items-center justify-center">
                      <div
                        :class="[
                          'relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-300 sm:size-10',
                          i < currentStepIndex
                            ? 'border-[3px] border-[var(--color-primary-600)] bg-[var(--color-primary-600)] text-white shadow-sm'
                            : i === currentStepIndex
                              ? 'border-[3px] border-[var(--color-primary-600)] bg-white text-[var(--color-primary-600)] shadow-sm ring-4 ring-[var(--color-primary-50)] scale-[1.04]'
                              : 'border-2 border-[var(--color-neutral-200)] bg-slate-50 text-[var(--text-muted)]',
                        ]"
                      >
                        <Check
                          v-if="i < currentStepIndex"
                          class="size-4"
                          stroke-width="3"
                          aria-hidden="true"
                        />
                        <component :is="step.icon" v-else class="size-4" aria-hidden="true" />
                      </div>
                      <div
                        v-if="i < visibleSteps.length - 1"
                        class="relative mx-1.5 h-2 flex-1 overflow-hidden rounded-full border border-black/[0.03] bg-[var(--color-neutral-100)] sm:mx-2"
                        aria-hidden="true"
                      >
                        <div
                          class="h-full rounded-full bg-[var(--color-primary-600)] transition-[width] duration-500 ease-out-expo"
                          :style="{ width: i < currentStepIndex ? '100%' : '0%' }"
                        />
                      </div>
                    </div>
                    <span
                      :class="[
                        'mt-2 whitespace-nowrap text-xs font-semibold tracking-wide sm:max-w-none',
                        i === currentStepIndex
                          ? 'text-[var(--color-primary-700)]'
                          : i < currentStepIndex
                            ? 'text-[var(--color-primary-600)]'
                            : 'text-[var(--text-muted)]',
                      ]"
                    >
                      {{ step.label }}
                      <span v-if="i < currentStepIndex" class="sr-only"> completed</span>
                      <span v-else-if="i === currentStepIndex" class="sr-only"> current step</span>
                    </span>
                  </li>
                </ol>
              </nav>
            </div>
            <div
              class="bg-gradient-to-b from-white to-[var(--surface-secondary)]/40 px-6 py-6 sm:px-8 sm:py-7"
            >
              <p
                class="text-[11px] font-semibold uppercase tracking-[0.08em] text-[var(--color-primary-600)]"
              >
                Step 01 — Choose your path
              </p>
              <h2
                id="choose-heading"
                class="mt-1 text-[1.625rem] font-semibold leading-tight tracking-[-0.04em] text-[var(--text-primary)] sm:text-[1.875rem]"
              >
                How would you like to proceed?
              </h2>
              <p class="mt-2 max-w-[52ch] text-sm leading-relaxed text-[var(--text-secondary)]">
                Start fresh or merge new CV data into your verified profile. You can change this
                later.
              </p>
            </div>

            <div class="grid gap-4 p-6 pt-2 sm:grid-cols-2 sm:p-8 sm:pt-2">
              <!-- Create new — quiet -->
              <button
                :disabled="!!choosing"
                class="group relative flex min-h-[11.5rem] flex-col gap-3 rounded-[var(--radius-xl)] border border-slate-200 bg-white p-5 text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50/60 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)]/40 disabled:cursor-wait disabled:opacity-60"
                :aria-busy="choosing === 'create_new'"
                @click="onChoose('create_new', $event as MouseEvent)"
              >
                <div
                  class="flex size-11 shrink-0 items-center justify-center rounded-2xl border border-slate-100 bg-slate-50 text-slate-600 transition-colors duration-200 group-hover:border-slate-200 group-hover:bg-white group-hover:text-slate-700"
                >
                  <Sparkles class="size-5" aria-hidden="true" />
                </div>
                <div>
                  <p class="text-[15px] font-semibold tracking-tight text-[var(--text-primary)]">
                    Create new profile
                  </p>
                  <p class="mt-1 text-xs leading-relaxed text-[var(--text-secondary)]">
                    Start fresh with a new profile built from your CV.
                  </p>
                </div>
                <ul
                  class="mt-auto hidden space-y-1.5 pt-2 text-xs leading-relaxed text-slate-500 group-hover:block sm:block"
                >
                  <li class="flex items-center gap-1.5">
                    <Check class="size-3.5 shrink-0 text-slate-400" aria-hidden="true" /> Fresh
                    completeness score
                  </li>
                  <li class="flex items-center gap-1.5">
                    <Check class="size-3.5 shrink-0 text-slate-400" aria-hidden="true" /> Clean
                    audit trail
                  </li>
                </ul>
                <span
                  class="pointer-events-none absolute bottom-3 right-3 flex size-7 items-center justify-center rounded-full bg-white text-slate-400 opacity-0 shadow-sm ring-1 ring-slate-200 transition-all duration-200 group-hover:opacity-100 group-hover:translate-x-0"
                  aria-hidden="true"
                  >→</span
                >
              </button>

              <!-- Update existing — hero -->
              <button
                :disabled="!!choosing"
                class="group relative flex min-h-[11.5rem] flex-col gap-3 rounded-[var(--radius-xl)] border-2 border-[var(--color-primary-200)] bg-gradient-to-br from-white to-[var(--color-primary-25)] p-5 text-left shadow-[var(--shadow-neo-raised-lg)] transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--color-primary-300)] hover:shadow-[var(--shadow-neo-raised-lg)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)]/40 disabled:cursor-wait disabled:opacity-60"
                :aria-busy="choosing === 'update_existing'"
                @click="onChoose('update_existing', $event as MouseEvent)"
              >
                <div
                  class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-[var(--color-primary-600)] text-white shadow-sm transition-colors duration-200 group-hover:bg-[var(--color-primary-700)]"
                >
                  <Upload class="size-5" aria-hidden="true" />
                </div>
                <div class="flex-1">
                  <p
                    class="flex flex-wrap items-center gap-2 text-[15px] font-semibold tracking-tight text-[var(--text-primary)]"
                  >
                    Update existing profile
                    <Badge variant="primary" size="sm">Recommended</Badge>
                  </p>
                  <p class="mt-1 text-xs leading-relaxed text-[var(--text-secondary)]">
                    Merge CV data into your current profile.
                  </p>
                  <ul class="mt-3 space-y-1.5 text-xs leading-relaxed text-[var(--text-secondary)]">
                    <li class="flex items-center gap-1.5">
                      <Check
                        class="size-3.5 shrink-0 text-[var(--color-primary-600)]"
                        aria-hidden="true"
                      />
                      Keeps your verified skills
                    </li>
                    <li class="flex items-center gap-1.5">
                      <Check
                        class="size-3.5 shrink-0 text-[var(--color-primary-600)]"
                        aria-hidden="true"
                      />
                      Merges only new items
                    </li>
                    <li class="flex items-center gap-1.5">
                      <Check
                        class="size-3.5 shrink-0 text-[var(--color-primary-600)]"
                        aria-hidden="true"
                      />
                      Preserves profile completion
                    </li>
                  </ul>
                </div>
                <span
                  class="pointer-events-none absolute bottom-3 right-3 flex size-7 items-center justify-center rounded-full bg-[var(--color-primary-600)] text-white opacity-90 shadow-sm transition-all duration-200 group-hover:translate-x-0.5 group-hover:bg-[var(--color-primary-700)]"
                  aria-hidden="true"
                  >→</span
                >
              </button>
            </div>
            <div
              class="flex items-center justify-center gap-2 border-t border-slate-100 bg-[var(--surface-secondary)]/60 px-6 py-3 text-xs text-[var(--text-muted)] sm:px-8"
            >
              <span>You can switch modes anytime via “Start over”.</span>
            </div>
          </section>

          <div class="h-px bg-slate-200/70" aria-hidden="true" />

          <div
            class="rounded-[var(--radius-xl)] bg-[var(--surface-secondary)] p-4 ring-1 ring-black/[0.03] sm:p-5"
          >
            <CvDocumentList
              :highlight-id="duplicateCvId"
              @review="handleReviewCv"
              @view="handleViewCv"
              @open="handleOpenDocument"
              @track="handleTrackCv"
              @retry="handleRetryFromList"
            />
          </div>
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
          <Card padding="lg" class="overflow-hidden">
            <div
              class="-mx-6 -mt-6 mb-6 border-b border-slate-100 bg-white p-3 sm:-mx-8 sm:-mt-8 sm:p-4"
            >
              <nav aria-label="Import progress">
                <ol
                  class="flex items-start gap-1 sm:gap-2"
                  role="progressbar"
                  :aria-valuenow="currentStepIndex + 1"
                  :aria-valuemin="1"
                  :aria-valuemax="visibleSteps.length"
                >
                  <li
                    v-for="(step, i) in visibleSteps"
                    :key="step.key"
                    :class="[
                      'flex flex-col items-center text-center',
                      i < visibleSteps.length - 1 ? 'flex-1' : '',
                    ]"
                    :aria-current="i === currentStepIndex ? 'step' : undefined"
                  >
                    <div class="flex w-full items-center justify-center">
                      <div
                        :class="[
                          'relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-300 sm:size-10',
                          i < currentStepIndex
                            ? 'border-[3px] border-[var(--color-primary-600)] bg-[var(--color-primary-600)] text-white shadow-sm'
                            : i === currentStepIndex
                              ? 'border-[3px] border-[var(--color-primary-600)] bg-white text-[var(--color-primary-600)] shadow-sm ring-4 ring-[var(--color-primary-50)] scale-[1.04]'
                              : 'border-2 border-[var(--color-neutral-200)] bg-slate-50 text-[var(--text-muted)]',
                        ]"
                      >
                        <Check
                          v-if="i < currentStepIndex"
                          class="size-4"
                          stroke-width="3"
                          aria-hidden="true"
                        />
                        <component :is="step.icon" v-else class="size-4" aria-hidden="true" />
                      </div>
                      <div
                        v-if="i < visibleSteps.length - 1"
                        class="relative mx-1.5 h-2 flex-1 overflow-hidden rounded-full border border-black/[0.03] bg-[var(--color-neutral-100)] sm:mx-2"
                        aria-hidden="true"
                      >
                        <div
                          class="h-full rounded-full bg-[var(--color-primary-600)] transition-[width] duration-500 ease-out-expo"
                          :style="{ width: i < currentStepIndex ? '100%' : '0%' }"
                        />
                      </div>
                    </div>
                    <span
                      :class="[
                        'mt-2 whitespace-nowrap text-xs font-semibold tracking-wide sm:max-w-none',
                        i === currentStepIndex
                          ? 'text-[var(--color-primary-700)]'
                          : i < currentStepIndex
                            ? 'text-[var(--color-primary-600)]'
                            : 'text-[var(--text-muted)]',
                      ]"
                    >
                      {{ step.label }}
                      <span v-if="i < currentStepIndex" class="sr-only"> completed</span>
                      <span v-else-if="i === currentStepIndex" class="sr-only"> current step</span>
                    </span>
                  </li>
                </ol>
              </nav>
            </div>
            <div class="mb-6 flex items-start justify-between gap-4">
              <div>
                <h2 class="text-lg font-semibold tracking-tight text-[var(--text-primary)]">
                  {{ stageHeading.title }}
                </h2>
                <p class="mt-1 text-sm text-[var(--text-secondary)]">
                  {{ stageHeading.subtitle }}
                </p>
              </div>
              <Button
                v-if="stage === 'upload'"
                variant="ghost"
                size="sm"
                class="shrink-0"
                @click="reset"
              >
                <ArrowLeft class="mr-1 size-4" aria-hidden="true" />
                Start over
              </Button>
            </div>
            <div
              class="mb-4 flex items-center gap-2 rounded-lg bg-[var(--surface-secondary)] px-3 py-2"
            >
              <span
                class="text-xs font-medium uppercase tracking-wider text-[var(--text-secondary)]"
              >
                {{ uploadMode === 'create_new' ? 'Create new profile' : 'Update existing profile' }}
              </span>
              <button
                v-if="stage === 'idle'"
                class="text-xs font-medium text-[var(--color-primary-600)] underline decoration-[var(--color-primary-200)] underline-offset-2 hover:text-[var(--color-primary-800)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)]/40"
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
    <ConfirmDialog
      :open="showLeaveConfirm"
      title="Leave without finishing?"
      description="You have unreviewed suggestions. If you leave now, your progress on this CV will be lost."
      confirm-label="Leave"
      cancel-label="Stay"
      variant="warning"
      @confirm="confirmLeave"
      @cancel="cancelLeave"
    />
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
.back-fade-enter-active,
.back-fade-leave-active {
  transition:
    opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1),
    transform 0.25s cubic-bezier(0.16, 1, 0.3, 1),
    max-height 0.25s cubic-bezier(0.16, 1, 0.3, 1),
    margin-bottom 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  overflow: hidden;
}
.back-fade-enter-from,
.back-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
  max-height: 0;
  margin-bottom: 0;
}
.back-fade-enter-to,
.back-fade-leave-from {
  opacity: 1;
  transform: translateY(0);
  max-height: 40px;
  margin-bottom: 1rem;
}
@media (prefers-reduced-motion: reduce) {
  .stage-enter-active,
  .stage-leave-active,
  .back-fade-enter-active,
  .back-fade-leave-active {
    transition: none;
  }
}
</style>
