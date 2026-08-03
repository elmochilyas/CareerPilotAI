<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
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
  { key: 'received', label: 'Description received', status: 'complete' as const },
  { key: 'validating', label: 'Validating', status: 'pending' as const },
  { key: 'analyzing', label: 'Analyzing job information', status: 'pending' as const },
  { key: 'preparing', label: 'Preparing review', status: 'pending' as const },
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
    if (ingestionId.value === null) {
      throw new Error('Invalid ingestion identifier.')
    }

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
    if (ingestionId.value === null) {
      throw new Error('Invalid ingestion identifier.')
    }

    return reanalyzeIngestion(ingestionId.value)
  },
  onSuccess: async (requeuedIngestion) => {
    queryClient.setQueryData(opportunityKeys.ingestion(requeuedIngestion.id), requeuedIngestion)
    await queryClient.invalidateQueries({
      queryKey: opportunityKeys.ingestions(),
      exact: true,
    })
    reanalysisError.value = ''
    isReanalyzeDialogOpen.value = false
  },
  onError: (error) => {
    const detail = extractProblemDetail(error as never)
    reanalysisError.value =
      detail && detail.status < 500
        ? detail.detail
        : 'We couldn’t restart the analysis. Please try again in a moment.'
    isReanalyzeDialogOpen.value = false
  },
})

watch(
  [() => ingestion.value?.status, ingestionId],
  ([status, id]) => {
    if (status !== 'review_ready' || id === null) return

    void router.push({
      name: 'opportunities-review',
      params: { id },
    })
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

  await router.push({
    name: 'opportunities-detail',
    params: { id: confirmedOpportunityId.value },
  })
}
</script>

<template>
  <div class="mx-auto max-w-xl space-y-6">
    <h1 class="text-2xl font-semibold">Processing job description</h1>

    <div
      v-if="ingestionId === null"
      class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
      role="alert"
    >
      <p>Invalid ingestion identifier.</p>
      <button
        class="mt-2 font-medium text-blue-600 hover:text-blue-800 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none"
        @click="router.push({ name: 'opportunities' })"
      >
        Back to opportunities
      </button>
    </div>

    <div
      v-else-if="isPending"
      class="py-12 text-center text-gray-500"
      role="status"
      aria-live="polite"
    >
      Loading…
    </div>

    <div
      v-else-if="isError || !ingestion"
      class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
    >
      Failed to load ingestion.
    </div>

    <div v-else class="space-y-6">
      <div
        v-if="isConfirmed"
        class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700"
      >
        <p class="font-medium">Job opportunity confirmed!</p>
        <button
          v-if="confirmedOpportunityId !== null"
          class="mt-2 font-medium text-blue-600 hover:text-blue-800 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none"
          @click="viewConfirmedOpportunity"
        >
          View opportunity
        </button>
        <div v-else class="mt-2">
          <p>The confirmed opportunity link is temporarily unavailable.</p>
          <button
            class="mt-2 font-medium text-blue-600 hover:text-blue-800 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none"
            @click="router.push({ name: 'opportunities' })"
          >
            Back to opportunities
          </button>
        </div>
      </div>

      <div class="space-y-3">
        <div
          v-for="(stage, i) in stageItems"
          :key="stage.key"
          class="flex items-center gap-3 rounded-lg border p-3"
          :class="{
            'border-blue-200 bg-blue-50': stage.status === 'active',
            'border-green-200 bg-green-50': stage.status === 'complete',
            'border-red-200 bg-red-50': stage.status === 'failed',
            'border-gray-200 bg-white': stage.status === 'pending',
          }"
        >
          <div
            class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-medium"
            :class="{
              'bg-blue-600 text-white': stage.status === 'active',
              'bg-green-600 text-white': stage.status === 'complete',
              'bg-red-600 text-white': stage.status === 'failed',
              'border border-gray-300 text-gray-400': stage.status === 'pending',
            }"
          >
            <span v-if="stage.status === 'complete'">&check;</span>
            <span v-else-if="stage.status === 'active'">…</span>
            <span v-else-if="stage.status === 'failed'">&#10007;</span>
            <span v-else>{{ i + 1 }}</span>
          </div>
          <span
            class="text-sm"
            :class="{
              'font-medium text-blue-700': stage.status === 'active',
              'text-green-700': stage.status === 'complete',
              'font-medium text-red-700': stage.status === 'failed',
              'text-gray-500': stage.status === 'pending',
            }"
          >
            {{ stage.label }}
          </span>
        </div>
      </div>

      <div
        v-if="isFailed"
        class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        role="alert"
        aria-live="assertive"
      >
        <p class="font-medium">We couldn’t complete the analysis</p>
        <p class="mt-1 break-words">
          {{ failureMessage }}
        </p>

        <div class="mt-3 flex flex-wrap gap-3">
          <button
            v-if="isRetryable"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="isPending"
            @click="handleRetry"
          >
            Retry
          </button>
          <button
            type="button"
            class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-700 hover:bg-red-100 focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="cancelMutation.isPending.value"
            @click="requestCancellation"
          >
            {{ cancelMutation.isPending.value ? 'Cancelling…' : 'Cancel ingestion' }}
          </button>
          <button
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none"
            @click="router.push('/opportunities/import')"
          >
            Import a different description
          </button>
        </div>
        <details v-if="ingestion.failure_code" class="mt-3 text-xs text-red-600">
          <summary
            class="cursor-pointer rounded-sm font-medium focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 focus-visible:outline-none"
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

      <div
        v-if="isCancelled"
        class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800"
      >
        <p class="font-medium">Processing cancelled</p>
        <p class="mt-1">This job description ingestion was cancelled.</p>
        <div class="mt-3 flex flex-wrap gap-3">
          <button
            type="button"
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="reanalyzeMutation.isPending.value"
            @click="requestReanalysis"
          >
            {{ reanalyzeMutation.isPending.value ? 'Starting analysis…' : 'Reanalyze job' }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="router.push({ name: 'opportunities' })"
          >
            Back to opportunities
          </button>
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

      <div v-if="ingestion.status === 'review_ready'" class="text-center">
        <button
          class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
          @click="
            router.push({
              name: 'opportunities-review',
              params: { id: ingestionId },
            })
          "
        >
          Review extracted information
        </button>
      </div>
    </div>

    <ConfirmDialog
      :open="isCancelDialogOpen"
      :busy="cancelMutation.isPending.value"
      title="Cancel this ingestion?"
      description="This stops the current ingestion and removes it from active processing. You can import the description again later."
      confirm-label="Cancel ingestion"
      busy-label="Cancelling…"
      @confirm="confirmCancellation"
      @cancel="dismissCancellation"
    />

    <ConfirmDialog
      :open="isReanalyzeDialogOpen"
      :busy="reanalyzeMutation.isPending.value"
      title="Reanalyze this job?"
      description="This starts a fresh analysis using the same job description. Previous extracted suggestions will be replaced, and no duplicate opportunity will be created."
      confirm-label="Reanalyze job"
      busy-label="Starting analysis…"
      @confirm="confirmReanalysis"
      @cancel="dismissReanalysis"
    />
  </div>
</template>
