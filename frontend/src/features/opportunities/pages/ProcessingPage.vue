<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import {
  AlertCircle,
  ArrowLeft,
  Check,
  CheckCircle2,
  Loader2,
  RefreshCw,
  X,
  XCircle,
} from '@lucide/vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import Button from '@/components/ui/Button.vue'
import { extractProblemDetail } from '@/api/client'
import {
  deleteIngestion,
  fetchIngestion,
  opportunityKeys,
  reanalyzeIngestion,
  retryIngestion,
} from '../api'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const isCancelDialogOpen = shallowRef(false)
const cancellationError = shallowRef('')
const isReanalyzeDialogOpen = shallowRef(false)
const reanalysisError = shallowRef('')

const ingestionId = computed<number | null>(() => {
  const routeId = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id
  const parsedId = Number(routeId)
  return Number.isInteger(parsedId) && parsedId > 0 ? parsedId : null
})

const {
  data: ingestion,
  isPending,
  isError,
} = useQuery({
  queryKey: computed(() => opportunityKeys.ingestion(ingestionId.value ?? 0)),
  queryFn: () => fetchIngestion(ingestionId.value!),
  enabled: computed(() => ingestionId.value !== null),
  refetchInterval: (query) => {
    const status = query.state.data?.status
    if (status === 'draft' || status === 'queued' || status === 'processing') return 3000
    return false
  },
})

const stages = [
  { key: 'received', label: 'Description received', icon: Check },
  { key: 'validating', label: 'Validating', icon: Loader2 },
  { key: 'analyzing', label: 'Analyzing job information', icon: Loader2 },
  { key: 'preparing', label: 'Preparing review', icon: Loader2 },
]

const currentStage = computed(() => {
  const s = ingestion.value?.status
  if (!s || s === 'draft') return 0
  if (s === 'queued') return 1
  if (s === 'processing') return 2
  if (s === 'failed') return 2
  if (s === 'review_ready' || s === 'confirmed') return 4
  if (s === 'cancelled') return -2
  return -1
})

type StageStatus = 'complete' | 'active' | 'failed' | 'pending'

const stageItems = computed(() => {
  return stages.map((stage, i) => {
    let status: StageStatus
    if (currentStage.value > i) {
      status = 'complete'
    } else if (currentStage.value === i) {
      status = isFailed.value ? 'failed' : 'active'
    } else {
      status = 'pending'
    }
    return { ...stage, status }
  })
})

const isFailed = computed(() => ingestion.value?.status === 'failed')
const failureMessage = computed(() => {
  const code = ingestion.value?.failure_code
  const messages: Record<string, string> = {
    ai_provider_not_configured: 'Job analysis is temporarily unavailable. Please try again later.',
    ai_provider_authentication_failed:
      'Job analysis is temporarily unavailable. Please try again later.',
    ai_provider_rate_limited:
      'The analysis service is busy right now. Wait a moment, then try again.',
    ai_provider_timeout: 'The analysis took too long. Please try again.',
    ai_provider_unavailable:
      'The analysis service is temporarily unavailable. Please try again shortly.',
    invalid_ai_output:
      'We could not reliably read this job description. No extracted information was saved.',
    suggestion_persistence_failed:
      'The analysis finished, but we could not save it safely. Please try again.',
    unexpected_processing_failure:
      'We could not complete this analysis safely. No unreviewed information was added.',
  }
  return (
    (code && messages[code]) ||
    ingestion.value?.failure_reason ||
    'The analysis could not be completed.'
  )
})

const isRetryable = computed(() => {
  if (!isFailed.value) return false
  const code = ingestion.value?.failure_code
  const retryableCodes = [
    'provider_unavailable',
    'provider_timeout',
    'provider_rate_limited',
    'ai_provider_timeout',
    'ai_provider_rate_limited',
    'ai_provider_unavailable',
    'suggestion_persistence_failed',
    'transient_queue_error',
    'network_error',
  ]
  return code ? retryableCodes.includes(code) : true
})

const isCancelled = computed(() => ingestion.value?.status === 'cancelled')
const isConfirmed = computed(() => ingestion.value?.status === 'confirmed')
const confirmedOpportunityId = computed(() => ingestion.value?.confirmed_opportunity_id ?? null)

const cancelMutation = useMutation({
  mutationFn: async () => {
    if (ingestionId.value === null) throw new Error('Invalid ingestion identifier.')
    return deleteIngestion(ingestionId.value)
  },
  onSuccess: async (cancelledIngestion) => {
    queryClient.setQueryData(opportunityKeys.ingestion(cancelledIngestion.id), cancelledIngestion)
    await queryClient.invalidateQueries({ queryKey: opportunityKeys.ingestions() })
    isCancelDialogOpen.value = false
    await router.replace({ name: 'opportunities' })
  },
  onError: (error) => {
    const detail = extractProblemDetail(error as never)
    cancellationError.value =
      detail?.detail ?? 'Cancellation failed. Please try again or return to opportunities.'
    isCancelDialogOpen.value = false
  },
})

const reanalyzeMutation = useMutation({
  mutationFn: async () => {
    if (ingestionId.value === null) throw new Error('Invalid ingestion identifier.')
    return reanalyzeIngestion(ingestionId.value)
  },
  onSuccess: async (requeuedIngestion) => {
    queryClient.setQueryData(opportunityKeys.ingestion(requeuedIngestion.id), requeuedIngestion)
    await queryClient.invalidateQueries({ queryKey: opportunityKeys.ingestions(), exact: true })
    reanalysisError.value = ''
    isReanalyzeDialogOpen.value = false
  },
  onError: (error) => {
    const detail = extractProblemDetail(error as never)
    reanalysisError.value =
      detail && detail.status < 500
        ? detail.detail
        : "We couldn't restart the analysis. Please try again in a moment."
    isReanalyzeDialogOpen.value = false
  },
})

watch(
  [() => ingestion.value?.status, ingestionId],
  ([status, id]) => {
    if (status !== 'review_ready' || id === null) return
    void router.push({ name: 'opportunities-review', params: { id } })
  },
  { immediate: true },
)

async function handleRetry(): Promise<void> {
  if (ingestionId.value === null) return
  await retryIngestion(ingestionId.value)
}

function requestCancellation(): void {
  if (ingestionId.value === null || cancelMutation.isPending.value) return
  cancellationError.value = ''
  isCancelDialogOpen.value = true
}

function dismissCancellation(): void {
  if (cancelMutation.isPending.value) return
  isCancelDialogOpen.value = false
}

function confirmCancellation(): void {
  if (cancelMutation.isPending.value) return
  cancelMutation.mutate()
}

function requestReanalysis(): void {
  if (ingestionId.value === null || reanalyzeMutation.isPending.value) return
  reanalysisError.value = ''
  isReanalyzeDialogOpen.value = true
}

function dismissReanalysis(): void {
  if (reanalyzeMutation.isPending.value) return
  isReanalyzeDialogOpen.value = false
}

function confirmReanalysis(): void {
  if (reanalyzeMutation.isPending.value) return
  reanalyzeMutation.mutate()
}

async function viewConfirmedOpportunity(): Promise<void> {
  if (confirmedOpportunityId.value === null) return
  await router.push({ name: 'opportunities-detail', params: { id: confirmedOpportunityId.value } })
}
</script>

<template>
  <div class="mx-auto max-w-xl space-y-6">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Processing job description</h1>
        <p class="mt-1 text-sm text-slate-500">AI is analyzing your job description.</p>
      </div>
    </div>

    <!-- Invalid ID -->
    <div
      v-if="ingestionId === null"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
      role="alert"
    >
      <p>Invalid ingestion identifier.</p>
      <Button
        variant="outline"
        class="mt-2"
        size="sm"
        @click="router.push({ name: 'opportunities' })"
      >
        <ArrowLeft class="size-3.5" aria-hidden="true" />
        Back to opportunities
      </Button>
    </div>

    <!-- Loading -->
    <div v-else-if="isPending" class="space-y-3" role="status" aria-live="polite">
      <span class="sr-only">Loading processing status</span>
      <div
        v-for="i in 4"
        :key="i"
        class="h-14 animate-pulse rounded-lg border border-slate-200 bg-white"
        aria-hidden="true"
      />
    </div>

    <!-- Error -->
    <div
      v-else-if="isError || !ingestion"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
    >
      Failed to load ingestion.
    </div>

    <template v-else>
      <!-- Confirmed banner -->
      <div
        v-if="isConfirmed"
        class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm"
      >
        <div class="flex items-center gap-2 text-emerald-700">
          <CheckCircle2 class="size-5" aria-hidden="true" />
          <p class="font-semibold">Job opportunity confirmed!</p>
        </div>
        <button
          v-if="confirmedOpportunityId !== null"
          class="mt-3 inline-flex items-center gap-1.5 font-medium text-primary-600 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
          @click="viewConfirmedOpportunity"
        >
          View opportunity
        </button>
        <div v-else class="mt-2 text-emerald-600">
          <p>The confirmed opportunity link is temporarily unavailable.</p>
        </div>
      </div>

      <!-- Stage progress -->
      <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Progress</h2>
        <div class="space-y-3">
          <div v-for="(stage, i) in stageItems" :key="stage.key" class="flex items-center gap-3">
            <div
              class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition-colors"
              :class="{
                'bg-emerald-500 text-white': stage.status === 'complete',
                'bg-primary-500 text-white': stage.status === 'active',
                'bg-red-500 text-white': stage.status === 'failed',
                'border-2 border-slate-200 bg-white text-slate-400': stage.status === 'pending',
              }"
              :aria-label="`Stage ${i + 1}: ${stage.label} - ${stage.status}`"
            >
              <Check v-if="stage.status === 'complete'" class="size-4" aria-hidden="true" />
              <Loader2
                v-else-if="stage.status === 'active'"
                class="size-4 animate-spin"
                aria-hidden="true"
              />
              <XCircle v-else-if="stage.status === 'failed'" class="size-4" aria-hidden="true" />
              <span v-else>{{ i + 1 }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <p
                class="text-sm font-medium"
                :class="{
                  'text-emerald-700': stage.status === 'complete',
                  'text-primary-700': stage.status === 'active',
                  'text-red-700': stage.status === 'failed',
                  'text-slate-400': stage.status === 'pending',
                }"
              >
                {{ stage.label }}
              </p>
            </div>
            <span v-if="stage.status === 'active'" class="text-xs font-medium text-primary-600">
              In progress
            </span>
            <span
              v-else-if="stage.status === 'complete'"
              class="text-xs font-medium text-emerald-600"
            >
              Done
            </span>
          </div>
        </div>
      </div>

      <!-- Failed state -->
      <div
        v-if="isFailed"
        class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm"
        role="alert"
        aria-live="assertive"
      >
        <div class="flex items-center gap-2 text-red-700">
          <AlertCircle class="size-5" aria-hidden="true" />
          <p class="font-semibold">We couldn't complete the analysis</p>
        </div>
        <p class="mt-2 break-words text-red-600">{{ failureMessage }}</p>

        <div class="mt-4 flex flex-wrap gap-2">
          <Button v-if="isRetryable" size="sm" :disabled="isPending" @click="handleRetry">
            <RefreshCw class="size-3.5" aria-hidden="true" />
            Retry
          </Button>
          <Button
            variant="outline"
            size="sm"
            :disabled="cancelMutation.isPending.value"
            @click="requestCancellation"
          >
            <X class="size-3.5" aria-hidden="true" />
            {{ cancelMutation.isPending.value ? 'Cancelling...' : 'Cancel ingestion' }}
          </Button>
          <Button variant="ghost" size="sm" @click="router.push('/opportunities/import')">
            Import a different description
          </Button>
        </div>
        <details v-if="ingestion.failure_code" class="mt-3 text-xs text-red-600">
          <summary
            class="cursor-pointer font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
          >
            Support details
          </summary>
          <p class="mt-1 font-mono break-all">Error code: {{ ingestion.failure_code }}</p>
        </details>
        <p
          v-if="cancellationError"
          class="mt-3 break-words font-medium text-red-700"
          role="alert"
          aria-live="assertive"
        >
          {{ cancellationError }}
        </p>
      </div>

      <!-- Cancelled state -->
      <div v-if="isCancelled" class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm">
        <div class="flex items-center gap-2 text-amber-700">
          <XCircle class="size-5" aria-hidden="true" />
          <p class="font-semibold">Processing cancelled</p>
        </div>
        <p class="mt-1 text-amber-600">This job description ingestion was cancelled.</p>
        <div class="mt-4 flex flex-wrap gap-2">
          <Button
            size="sm"
            :disabled="reanalyzeMutation.isPending.value"
            @click="requestReanalysis"
          >
            <RefreshCw class="size-3.5" aria-hidden="true" />
            {{ reanalyzeMutation.isPending.value ? 'Starting analysis...' : 'Reanalyze job' }}
          </Button>
          <Button variant="outline" size="sm" @click="router.push({ name: 'opportunities' })">
            Back to opportunities
          </Button>
        </div>
        <p
          v-if="reanalysisError"
          class="mt-3 break-words font-medium text-red-700"
          role="alert"
          aria-live="assertive"
        >
          {{ reanalysisError }}
        </p>
      </div>

      <!-- Review ready -->
      <div v-if="ingestion.status === 'review_ready'" class="text-center">
        <Button @click="router.push({ name: 'opportunities-review', params: { id: ingestionId } })">
          Review extracted information
        </Button>
      </div>
    </template>

    <ConfirmDialog
      :open="isCancelDialogOpen"
      :busy="cancelMutation.isPending.value"
      title="Cancel this ingestion?"
      description="This stops the current ingestion and removes it from active processing. You can import the description again later."
      confirm-label="Cancel ingestion"
      busy-label="Cancelling..."
      @confirm="confirmCancellation"
      @cancel="dismissCancellation"
    />

    <ConfirmDialog
      :open="isReanalyzeDialogOpen"
      :busy="reanalyzeMutation.isPending.value"
      title="Reanalyze this job?"
      description="This starts a fresh analysis using the same job description. Previous extracted suggestions will be replaced, and no duplicate opportunity will be created."
      confirm-label="Reanalyze job"
      busy-label="Starting analysis..."
      @confirm="confirmReanalysis"
      @cancel="dismissReanalysis"
    />
  </div>
</template>
