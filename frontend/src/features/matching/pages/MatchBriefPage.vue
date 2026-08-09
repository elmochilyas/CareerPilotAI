<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ChevronDown, Loader2 } from '@lucide/vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { extractProblemDetail } from '@/api/client'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { fetchOpportunity, opportunityKeys } from '@/features/opportunities/api'
import ClarificationEntryCard from '@/features/clarification/components/ClarificationEntryCard.vue'
import { useClarificationSession } from '@/features/clarification/composables/useClarificationSession'
import { useMatchAnalysis } from '../composables/useMatchAnalysis'
import GapReviewSection from '../components/GapReviewSection.vue'
import InsufficientProfileGate from '../components/InsufficientProfileGate.vue'
import MatchAtAGlance from '../components/MatchAtAGlance.vue'
import MatchBriefHeader from '../components/MatchBriefHeader.vue'
import MatchErrorState from '../components/MatchErrorState.vue'
import MatchFilterBar from '../components/MatchFilterBar.vue'
import MatchScoreDetails from '../components/MatchScoreDetails.vue'
import MatchStatusPanel from '../components/MatchStatusPanel.vue'
import MatchSummaryHero from '../components/MatchSummaryHero.vue'
import RequirementResultRow from '../components/RequirementResultRow.vue'
import StaleNotice from '../components/StaleNotice.vue'
import type { MatchFinding, MatchImportance } from '../types'
import { findingKey } from '../utils/matchPresentation'

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
  isRecalculating,
  recalculateMutation,
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
const failedAnalysisTitle = computed(() => 'The match analysis failed')

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

function retryLoad(): void {
  if (opportunityId.value !== null) {
    void opportunityQuery.refetch()
    void listQuery.refetch()
  }
}

function recalculate(): void {
  const target = completedAnalysis.value
  if (target !== null && !isRecalculating.value) {
    recalculateMutation.mutate(target.id)
  }
}

const classifierUnavailable = computed(
  () => completedAnalysis.value?.classifier.status === 'unavailable',
)

const isGapReviewMode = computed(
  () => route.query.view === 'analysis' && route.query.filter === 'gaps',
)

const fullAnalysisOpen = ref(route.query.view === 'analysis')

const clarificationAnalysisId = computed(() =>
  isGapReviewMode.value ? (completedAnalysis.value?.id ?? null) : null,
)

const { questions } = useClarificationSession(clarificationAnalysisId)

function onFullAnalysisToggle(event: Event): void {
  fullAnalysisOpen.value = (event.target as HTMLDetailsElement).open
}

function openClarifications(): void {
  const id = opportunityId.value
  if (id === null) return
  void router.push({ name: 'opportunities-match-clarifications', params: { id } })
}

function viewGaps(): void {
  matchFilter.value = 'gap'
  fullAnalysisOpen.value = true
  if (opportunityId.value !== null) {
    void router.replace({ query: { ...route.query, view: 'analysis', filter: 'gaps' } })
  }
}

function viewAll(): void {
  matchFilter.value = 'all'
  fullAnalysisOpen.value = true
  if (opportunityId.value !== null) {
    const query = Object.fromEntries(
      Object.entries(route.query).filter(([key]) => key !== 'filter'),
    )
    void router.replace({ query: { ...query, view: 'analysis' } })
  }
}

function viewUnknown(): void {
  matchFilter.value = 'all'
  includeUnknown.value = true
  fullAnalysisOpen.value = true
  if (opportunityId.value !== null) {
    const query = Object.fromEntries(
      Object.entries(route.query).filter(([key]) => key !== 'filter'),
    )
    void router.replace({ query: { ...query, view: 'analysis' } })
  }
}

const matchFilter = ref<'all' | 'matched' | 'gap'>(route.query.filter === 'gaps' ? 'gap' : 'all')
const importanceFilter = ref<MatchImportance[]>([])
const includeUnknown = ref(false)

const filteredFindings = computed(() => {
  const findings = completedAnalysis.value?.findings ?? []
  return findings.filter((finding: MatchFinding) => {
    if (matchFilter.value !== 'all' && finding.match_state !== matchFilter.value) {
      return false
    }
    if (matchFilter.value === 'all' && finding.match_state === 'unknown' && !includeUnknown.value) {
      return false
    }
    if (importanceFilter.value.length > 0 && !importanceFilter.value.includes(finding.importance)) {
      return false
    }
    return true
  })
})

watch(
  () => route.query,
  (query) => {
    fullAnalysisOpen.value = query.view === 'analysis'
    matchFilter.value = query.filter === 'gaps' ? 'gap' : 'all'
  },
)

watch(matchFilter, (newFilter) => {
  if (opportunityId.value === null) return

  if (newFilter === 'gap') {
    void router.replace({ query: { ...route.query, view: 'analysis', filter: 'gaps' } })
  } else {
    const query = Object.fromEntries(
      Object.entries(route.query).filter(([key]) => key !== 'filter'),
    )
    void router.replace({ query: { ...query, view: 'analysis' } })
  }
})
</script>

<template>
  <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
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
      <span class="sr-only">Loading the Career Intelligence Brief…</span>
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
      :title="failedAnalysisTitle"
      :detail="failedNewest.failure.reason"
      :busy="isStarting"
      @retry="startAnalysis"
    />

    <div v-else-if="noAnalyses" class="mt-8">
      <EmptyState
        title="No match analysis yet"
        description="Start an analysis to see how this opportunity matches your profile."
      >
        <Button :loading="isStarting" @click="startAnalysis">
          {{ isStarting ? 'Starting…' : 'Start analysis' }}
        </Button>
      </EmptyState>
    </div>

    <template v-else-if="completedAnalysis">
      <div
        v-if="processing"
        role="status"
        aria-live="polite"
        class="mt-8 flex items-center gap-2 rounded-[var(--radius-xl)] bg-[var(--color-info-50)] px-4 py-3 text-sm text-[var(--color-info-700)] shadow-[var(--shadow-neo-raised-sm)]"
      >
        <Loader2
          class="size-4 shrink-0 animate-spin text-[var(--color-info-500)]"
          aria-hidden="true"
        />
        A new analysis is running. The previous result stays visible below.
      </div>
      <div
        v-else-if="failedNewest"
        role="alert"
        class="mt-8 flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-xl)] bg-[var(--color-error-50)] px-4 py-3 text-sm text-[var(--color-error-700)] shadow-[var(--shadow-neo-raised-sm)]"
      >
        <span>The latest analysis failed. The previous result is shown below.</span>
        <button
          type="button"
          :disabled="isStarting"
          class="rounded-[var(--radius-md)] bg-[var(--color-error-600)] px-3.5 py-1.5 text-sm font-medium text-white shadow-[var(--shadow-neo-button)] hover:bg-[var(--color-error-700)] focus-visible:ring-2 focus-visible:ring-[var(--color-error-500)] focus-visible:ring-offset-1 focus-visible:outline-none disabled:opacity-50"
          @click="startAnalysis"
        >
          Retry
        </button>
      </div>

      <StaleNotice
        v-if="completedAnalysis.stale"
        class="mt-8"
        :busy="isRecalculating"
        @recalculate="recalculate"
      />

      <template v-if="isGapReviewMode">
        <GapReviewSection
          class="mt-6"
          :findings="completedAnalysis.findings"
          :questions="questions"
          :opportunity-id="opportunityId!"
        />
      </template>

      <template v-else>
        <MatchSummaryHero :analysis="completedAnalysis" class="mt-6" />

        <MatchAtAGlance
          :findings="completedAnalysis.findings"
          class="mt-6"
          @view-gaps="viewGaps"
          @expand-all="viewAll"
          @expand-unknown="viewUnknown"
        />

        <ClarificationEntryCard
          v-if="completedAnalysis"
          class="mt-6"
          :analysis-id="completedAnalysis.id"
          @open="openClarifications"
        />

        <details
          id="full-analysis"
          class="group mt-6 overflow-hidden rounded-[var(--radius-xl)] bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised)]"
          :open="fullAnalysisOpen"
          @toggle="onFullAnalysisToggle"
        >
          <summary
            class="flex cursor-pointer list-none items-center justify-between gap-3 p-6 focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none sm:p-8"
          >
            <span>
              <span class="text-base font-semibold text-[var(--text-primary)]">Full analysis</span>
              <span class="text-[var(--text-muted)]">
                ({{ completedAnalysis.findings.length }} requirement{{
                  completedAnalysis.findings.length === 1 ? '' : 's'
                }})
              </span>
            </span>
            <span class="flex items-center gap-2 text-sm font-medium text-[var(--text-secondary)]">
              <span>{{ fullAnalysisOpen ? 'Hide details' : 'View full analysis' }}</span>
              <ChevronDown
                class="size-5 text-slate-400 transition-transform group-open:rotate-180"
                aria-hidden="true"
              />
            </span>
          </summary>

          <div class="border-t border-[var(--border-subtle)] p-6 sm:p-8">
            <MatchFilterBar
              v-model:match-filter="matchFilter"
              v-model:importance="importanceFilter"
              v-model:include-unknown="includeUnknown"
              :findings="completedAnalysis.findings"
            />

            <ul
              v-if="filteredFindings.length"
              class="mt-5 grid gap-4"
              aria-label="Match requirements"
            >
              <li v-for="finding in filteredFindings" :key="findingKey(finding)">
                <RequirementResultRow :finding="finding" />
              </li>
            </ul>
            <EmptyState
              v-else
              :title="
                completedAnalysis.findings.length === 0
                  ? 'No requirement results'
                  : 'No requirements match these filters'
              "
              :description="
                completedAnalysis.findings.length === 0
                  ? 'This analysis has no per-requirement results to show.'
                  : 'Adjust the filters to see more requirement results.'
              "
            />
          </div>
        </details>

        <MatchScoreDetails :analysis="completedAnalysis" class="mt-6" />
      </template>
    </template>
  </div>
</template>
