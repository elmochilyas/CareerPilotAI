import { computed, ref, toValue, watch } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { extractProblemDetail } from '@/api/client'
import type { MatchAnalysis, MatchOperation } from '../types'
import {
  createMatchAnalysis,
  fetchMatchAnalyses,
  matchKeys,
  recalculateMatchAnalysis,
} from '../api'

const IDEMPOTENCY_PREFIX = 'careerpilot.match.idempotency'
const IDEMPOTENCY_KEY_PATTERN = /^[A-Za-z0-9._:-]{8,128}$/

function storageKeyFor(opportunityId: number): string {
  return `${IDEMPOTENCY_PREFIX}.${opportunityId}`
}

function readIdempotencyKey(opportunityId: number): string | null {
  const stored = window.localStorage.getItem(storageKeyFor(opportunityId))
  return stored !== null && IDEMPOTENCY_KEY_PATTERN.test(stored) ? stored : null
}

function clearIdempotencyKey(opportunityId: number): void {
  window.localStorage.removeItem(storageKeyFor(opportunityId))
}

function nextIdempotencyKey(opportunityId: number): string {
  const existing = readIdempotencyKey(opportunityId)
  if (existing !== null) return existing
  const fresh = `match_${crypto.randomUUID()}`
  window.localStorage.setItem(storageKeyFor(opportunityId), fresh)
  return fresh
}

function problemCode(error: unknown): string | null {
  return extractProblemDetail(error as never)?.code ?? null
}

export function useMatchAnalysis(opportunityId: MaybeRefOrGetter<number | null>) {
  const queryClient = useQueryClient()
  const opportunityIdValue = computed(() => toValue(opportunityId))
  const announcement = ref('')
  let autoCreateAttempted = false

  const listQuery = useQuery({
    queryKey: computed(() => matchKeys.list(opportunityIdValue.value ?? 0)),
    queryFn: () => fetchMatchAnalyses(opportunityIdValue.value!),
    enabled: computed(() => opportunityIdValue.value !== null),
    refetchInterval: (query) => {
      const status = query.state.data?.data?.[0]?.status
      return status === 'queued' || status === 'processing' ? 3000 : false
    },
    retry: 1,
  })

  const analyses = computed(() => listQuery.data.value?.data ?? [])
  const activeAnalysis = computed<MatchAnalysis | null>(() => analyses.value[0] ?? null)
  const completedAnalysis = computed<MatchAnalysis | null>(
    () => analyses.value.find((analysis) => analysis.status === 'completed') ?? null,
  )

  const createMutation = useMutation({
    mutationFn: (id: number) => createMatchAnalysis(id, nextIdempotencyKey(id)),
    onSuccess: (operation: MatchOperation) => {
      announcement.value = 'Match analysis started.'
      clearIdempotencyKey(operation.job_opportunity_id)
      void queryClient.invalidateQueries({
        queryKey: matchKeys.list(operation.job_opportunity_id),
      })
    },
    onError: (error) => {
      if (problemCode(error) === 'insufficient_profile') {
        clearIdempotencyKey(opportunityIdValue.value ?? -1)
      }
      announcement.value =
        extractProblemDetail(error as never)?.detail ?? 'Could not start the match analysis.'
    },
  })

  const recalculateMutation = useMutation({
    mutationFn: (matchId: number) => recalculateMatchAnalysis(matchId),
    onSuccess: (operation: MatchOperation) => {
      announcement.value = 'Recalculating the match.'
      void queryClient.invalidateQueries({
        queryKey: matchKeys.list(operation.job_opportunity_id),
      })
    },
    onError: (error) => {
      if (problemCode(error) === 'active_match_analysis') {
        announcement.value = 'A match analysis is already running.'
        if (opportunityIdValue.value !== null) void listQuery.refetch()
        return
      }
      announcement.value = extractProblemDetail(error as never)?.detail ?? 'Recalculation failed.'
    },
  })

  const createProblemCode = computed(() => problemCode(createMutation.error.value))
  const recalculateProblemCode = computed(() => problemCode(recalculateMutation.error.value))
  const isStarting = computed(() => createMutation.isPending.value)
  const isRecalculating = computed(() => recalculateMutation.isPending.value)

  function startAnalysis(): void {
    const id = opportunityIdValue.value
    if (id === null || isStarting.value) return
    autoCreateAttempted = true
    createMutation.mutate(id)
  }

  watch(
    [() => listQuery.data.value, () => listQuery.isSuccess.value],
    ([data, success]) => {
      if (!success || autoCreateAttempted) return
      if (data === undefined || data.data.length === 0) {
        startAnalysis()
      }
    },
    { immediate: true },
  )

  return {
    announcement,
    analyses,
    activeAnalysis,
    completedAnalysis,
    listQuery,
    createMutation,
    recalculateMutation,
    createProblemCode,
    recalculateProblemCode,
    isStarting,
    isRecalculating,
    startAnalysis,
  }
}
