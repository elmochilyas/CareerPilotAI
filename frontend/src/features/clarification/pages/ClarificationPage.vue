<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft } from '@lucide/vue'
import { useQuery } from '@tanstack/vue-query'
import { extractProblemDetail } from '@/api/client'
import { fetchOpportunity, opportunityKeys } from '@/features/opportunities/api'
import ClarificationFlow from '../components/ClarificationFlow.vue'
import { useMatchAnalysis } from '@/features/matching/composables/useMatchAnalysis'
import InsufficientProfileGate from '@/features/matching/components/InsufficientProfileGate.vue'
import MatchBriefHeader from '@/features/matching/components/MatchBriefHeader.vue'
import MatchErrorState from '@/features/matching/components/MatchErrorState.vue'
import MatchStatusPanel from '@/features/matching/components/MatchStatusPanel.vue'

const route = useRoute()
const router = useRouter()

const opportunityId = computed(() => {
  const id = Number(route.params.id)
  return Number.isInteger(id) && id > 0 ? id : null
})

const {
  announcement,
  analyses,
  completedAnalysis,
  listQuery,
  createProblemCode,
  isStarting,
  startAnalysis,
} = useMatchAnalysis(opportunityId)

const opportunityQuery = useQuery({
  queryKey: computed(() => opportunityKeys.detail(opportunityId.value ?? 0)),
  queryFn: () => fetchOpportunity(opportunityId.value!),
  enabled: computed(() => opportunityId.value !== null),
  retry: 1,
})

const opportunity = opportunityQuery.data

const newest = computed(() => analyses.value[0] ?? null)
const processing = computed(
  () =>
    newest.value?.status === 'queued' || newest.value?.status === 'processing' || isStarting.value,
)
const failedNewest = computed(() => (newest.value?.status === 'failed' ? newest.value : null))
const noAnalyses = computed(() => analyses.value.length === 0)
const invalidId = computed(() => opportunityId.value === null)
const loading = computed(() => opportunityQuery.isLoading.value || listQuery.isLoading.value)
const loadError = computed(() => opportunityQuery.isError.value || listQuery.isError.value)
const insufficientProfile = computed(
  () => noAnalyses.value && createProblemCode.value === 'insufficient_profile',
)

const loadErrorDetail = computed(() => {
  const error = (opportunityQuery.error.value ?? listQuery.error.value) as
    | unknown
    | null
    | undefined
  const detail = extractProblemDetail(error as never)
  if (!detail) return null
  return detail.status >= 500
    ? 'An unexpected error occurred. Please try again.'
    : (detail.detail ?? null)
})

const classifierUnavailable = computed(
  () => completedAnalysis.value?.classifier.status === 'unavailable',
)

function retryLoad(): void {
  if (opportunityId.value !== null) {
    void opportunityQuery.refetch()
    void listQuery.refetch()
  }
}

function goBackToOpportunity(): void {
  const id = opportunityId.value
  if (id === null) return
  void router.push({ name: 'opportunities-detail', params: { id } })
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <MatchBriefHeader
      :opportunity="opportunity ?? null"
      :classifier-unavailable="classifierUnavailable"
    />

    <div class="sr-only" aria-live="polite">{{ announcement }}</div>

    <div v-if="invalidId" class="mt-8">
      <MatchErrorState title="Invalid link" detail="This match link is not valid." />
    </div>

    <div v-else-if="loading" class="mt-10" role="status">
      <div class="mx-auto max-w-md space-y-4">
        <div class="h-6 w-40 animate-pulse rounded-lg bg-[var(--color-neutral-100)]" />
        <div
          class="h-28 animate-pulse rounded-[var(--radius-xl)] bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised)]"
        />
        <div
          class="h-40 animate-pulse rounded-[var(--radius-xl)] bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised)]"
        />
      </div>
      <span class="sr-only">Loading the clarification questions…</span>
    </div>

    <MatchErrorState
      v-else-if="loadError"
      class="mt-8"
      :detail="loadErrorDetail"
      :busy="false"
      @retry="retryLoad"
    />

    <InsufficientProfileGate v-else-if="insufficientProfile" class="mt-8" />

    <MatchStatusPanel
      v-else-if="processing && !completedAnalysis"
      class="mt-8"
      :status="newest?.status === 'processing' ? 'processing' : 'queued'"
    />

    <MatchErrorState
      v-else-if="failedNewest && !completedAnalysis"
      class="mt-8"
      :title="'The match analysis failed'"
      :detail="failedNewest.failure.reason"
      :busy="isStarting"
      @retry="startAnalysis"
    />

    <template v-else-if="completedAnalysis">
      <button
        type="button"
        class="mb-4 inline-flex items-center gap-1.5 rounded-[var(--radius-md)] px-3 py-1.5 text-sm font-medium text-[var(--color-primary-700)] shadow-[var(--shadow-neo-raised-sm)] hover:text-[var(--color-primary-800)] hover:shadow-[var(--shadow-neo-button)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
        @click="goBackToOpportunity"
      >
        <ArrowLeft class="size-4" aria-hidden="true" />
        Back to opportunity
      </button>

      <ClarificationFlow
        class="mt-2"
        :analysis-id="completedAnalysis.id"
        @close="goBackToOpportunity"
      />
    </template>
  </div>
</template>
