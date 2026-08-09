import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'
import { extractProblemDetail } from '@/api/client'
import type { DecisionInput, PreviewData, ReviewDecisionValue } from '../types'
import {
  batchUpdateSuggestions,
  confirmIngestion,
  createIngestion,
  fetchIngestion,
  fetchOpportunities,
  fetchSuggestions,
  generatePreview,
  opportunityKeys,
  retryIngestion,
  updateSuggestion,
} from '../api'

export function useJobIngestion() {
  const router = useRouter()
  const queryClient = useQueryClient()
  const announcement = ref('')
  const activeIngestionId = ref<number | null>(null)
  const previewData = ref<PreviewData | null>(null)
  const confirmedOpportunityId = ref<number | null>(null)

  const ingestionQuery = useQuery({
    queryKey: computed(() => opportunityKeys.ingestion(activeIngestionId.value!)),
    queryFn: () => fetchIngestion(activeIngestionId.value!),
    enabled: computed(() => activeIngestionId.value !== null),
    refetchInterval: (query) => {
      const status = query.state.data?.status
      if (status === 'queued' || status === 'processing' || status === 'draft') return 3000
      return false
    },
    retry: 1,
  })

  const suggestionsQuery = useQuery({
    queryKey: computed(() => opportunityKeys.suggestions(activeIngestionId.value!)),
    queryFn: () => fetchSuggestions(activeIngestionId.value!),
    enabled: computed(() => {
      const s = ingestionQuery.data.value?.status
      return s === 'review_ready' && activeIngestionId.value !== null
    }),
  })

  const opportunitiesQuery = useQuery({
    queryKey: opportunityKeys.list(),
    queryFn: () => fetchOpportunities(),
  })

  const ingestion = computed(() => ingestionQuery.data.value ?? null)
  const suggestions = computed(() => suggestionsQuery.data.value ?? [])
  const opportunities = computed(() => opportunitiesQuery.data.value?.data ?? [])

  const isProcessing = computed(() => {
    const s = ingestion.value?.status
    return s === 'draft' || s === 'queued' || s === 'processing'
  })

  const isReviewReady = computed(() => ingestion.value?.status === 'review_ready')
  const isConfirmed = computed(() => ingestion.value?.status === 'confirmed')
  const isFailed = computed(() => ingestion.value?.status === 'failed')
  const isRetryableFailed = computed(() => {
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

  const allSuggestionsReviewed = computed(() => {
    if (suggestions.value.length === 0) return false
    return suggestions.value.every((s) => s.review_decision !== 'pending')
  })

  const hasAmbiguousSkills = computed(() => {
    return suggestions.value.some((s) => s.resolution === 'ambiguous')
  })

  const reviewProgress = computed(() => {
    const all = suggestions.value.length
    if (all === 0) return { reviewed: 0, total: 0, pct: 0 }
    const reviewed = suggestions.value.filter((s) => s.review_decision !== 'pending').length
    return { reviewed, total: all, pct: Math.round((reviewed / all) * 100) }
  })

  function setActiveIngestion(id: number): void {
    activeIngestionId.value = id
    ingestionQuery.refetch()
  }

  const createMutation = useMutation({
    mutationFn: (data: {
      source_description: string
      source_url?: string
      personal_label?: string
    }) => createIngestion(data),
    onSuccess: (doc) => {
      activeIngestionId.value = doc.id
      announcement.value = 'Job description submitted. Processing started.'
      queryClient.invalidateQueries({ queryKey: opportunityKeys.ingestions() })
      router.push(`/opportunities/ingestions/${doc.id}`)
    },
    onError: (error) => {
      const detail = extractProblemDetail(error as never)
      if (detail?.detail) announcement.value = detail.detail
      else announcement.value = 'Submission failed.'
    },
  })

  const retryMutation = useMutation({
    mutationFn: (id: number) => retryIngestion(id),
    onSuccess: () => {
      announcement.value = 'Retrying...'
      ingestionQuery.refetch()
    },
    onError: (error) => {
      const detail = extractProblemDetail(error as never)
      announcement.value = detail?.detail ?? 'Retry failed.'
    },
  })

  const saveDecisionMutation = useMutation({
    mutationFn: ({
      suggestionId,
      decision,
      version,
      edited_value,
      resolved_skill_id,
    }: {
      suggestionId: number
      decision: ReviewDecisionValue
      version: number
      edited_value?: Record<string, unknown>
      resolved_skill_id?: number | null
    }) =>
      updateSuggestion(activeIngestionId.value!, suggestionId, {
        decision,
        version,
        edited_value,
        resolved_skill_id,
      }),
    onSuccess: () => {
      previewData.value = null
      suggestionsQuery.refetch()
    },
    onError: (error) => {
      const detail = extractProblemDetail(error as never)
      announcement.value = detail?.detail ?? 'Failed to save decision.'
    },
  })

  const batchSaveMutation = useMutation({
    mutationFn: (decisions: DecisionInput[]) =>
      batchUpdateSuggestions(activeIngestionId.value!, decisions),
    onSuccess: () => {
      suggestionsQuery.refetch()
    },
    onError: (error) => {
      const detail = extractProblemDetail(error as never)
      announcement.value = detail?.detail ?? 'Failed to save decisions.'
    },
  })

  const previewMutation = useMutation({
    mutationFn: () => generatePreview(activeIngestionId.value!),
    onSuccess: (data) => {
      previewData.value = data
    },
    onError: (error) => {
      const detail = extractProblemDetail(error as never)
      announcement.value = detail?.detail ?? 'Failed to generate preview.'
    },
  })

  const confirmMutation = useMutation({
    mutationFn: (versionToken: string) => confirmIngestion(activeIngestionId.value!, versionToken),
    onSuccess: (opp) => {
      confirmedOpportunityId.value = opp.id
      announcement.value = 'Job opportunity confirmed!'
      ingestionQuery.refetch()
      queryClient.invalidateQueries({ queryKey: opportunityKeys.all })
    },
    onError: (error) => {
      const detail = extractProblemDetail(error as never)
      announcement.value = detail?.detail ?? 'Confirmation failed.'
    },
  })

  function reset(): void {
    activeIngestionId.value = null
    previewData.value = null
    confirmedOpportunityId.value = null
    announcement.value = ''
  }

  return {
    activeIngestionId,
    announcement,
    ingestion,
    suggestions,
    opportunities,
    previewData,
    confirmedOpportunityId,
    isProcessing,
    isReviewReady,
    isConfirmed,
    isFailed,
    isRetryableFailed,
    allSuggestionsReviewed,
    hasAmbiguousSkills,
    reviewProgress,
    setActiveIngestion,
    ingestionQuery,
    suggestionsQuery,
    opportunitiesQuery,
    createMutation,
    retryMutation,
    saveDecisionMutation,
    batchSaveMutation,
    previewMutation,
    confirmMutation,
    reset,
  }
}
