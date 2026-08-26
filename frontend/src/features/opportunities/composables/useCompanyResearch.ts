import { computed, toValue } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { extractProblemDetail } from '@/api/client'
import {
  fetchCompanyResearch,
  opportunityKeys,
  refreshCompanyResearch,
  startCompanyResearch,
} from '../api'

export function useCompanyResearch(opportunityId: MaybeRefOrGetter<number | null>) {
  const queryClient = useQueryClient()
  const id = computed(() => toValue(opportunityId))

  const query = useQuery({
    queryKey: computed(() => opportunityKeys.companyResearch(id.value ?? 0)),
    queryFn: () => fetchCompanyResearch(id.value!),
    enabled: computed(() => id.value !== null && id.value > 0),
    refetchInterval: (q) => {
      const status = (q.state.data as unknown as { status?: string } | undefined)?.status
      return status === 'processing' ? 3000 : false
    },
    retry: 1,
  })

  const data = computed(() => query.data.value ?? null)
  const status = computed(() => data.value?.status ?? 'not_researched')
  const isProcessing = computed(() => status.value === 'processing')
  const isFailed = computed(() => status.value === 'failed')
  const isCompleted = computed(() => status.value === 'completed' || status.value === 'limited')
  const isNotResearched = computed(() => status.value === 'not_researched')

  function invalidate(): void {
    if (id.value !== null) {
      void queryClient.invalidateQueries({ queryKey: opportunityKeys.companyResearch(id.value) })
    }
  }

  const startMutation = useMutation({
    mutationFn: (payload?: { company_website?: string; pasted_content?: string }) =>
      startCompanyResearch(id.value!, payload),
    onSuccess: () => invalidate(),
  })

  const refreshMutation = useMutation({
    mutationFn: (payload?: { company_website?: string; pasted_content?: string }) =>
      refreshCompanyResearch(id.value!, payload),
    onSuccess: () => invalidate(),
  })

  const isStarting = computed(() => startMutation.isPending.value)
  const isRefreshing = computed(() => refreshMutation.isPending.value)
  const isBusy = computed(() => isStarting.value || isRefreshing.value || isProcessing.value)

  function start(payload?: { company_website?: string; pasted_content?: string }): void {
    if (id.value === null || isBusy.value) return
    startMutation.mutate(payload)
  }

  function refresh(payload?: { company_website?: string; pasted_content?: string }): void {
    if (id.value === null || isProcessing.value) return
    refreshMutation.mutate(payload)
  }

  const errorCode = computed(() => {
    const err = (startMutation.error.value ??
      refreshMutation.error.value ??
      query.error.value) as unknown
    return extractProblemDetail(err as never)?.code ?? null
  })

  const errorDetail = computed(() => {
    const err = (startMutation.error.value ??
      refreshMutation.error.value ??
      query.error.value) as unknown
    return extractProblemDetail(err as never)?.detail ?? null
  })

  return {
    query,
    data,
    status,
    isProcessing,
    isFailed,
    isCompleted,
    isNotResearched,
    isStarting,
    isRefreshing,
    isBusy,
    start,
    refresh,
    startMutation,
    refreshMutation,
    errorCode,
    errorDetail,
  }
}
