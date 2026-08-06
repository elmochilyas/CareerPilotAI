import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { useMatchAnalysis } from '@/features/matching/composables/useMatchAnalysis'
import type { MatchAnalysis, MatchListResult, MatchOperation } from '@/features/matching/types'

const mocks = vi.hoisted(() => ({
  fetchMatchAnalyses: vi.fn<() => Promise<unknown>>(),
  createMatchAnalysis: vi.fn<(id: number, key: string) => Promise<unknown>>(),
  recalculateMatchAnalysis: vi.fn<(id: number) => Promise<unknown>>(),
  extractProblemDetail:
    vi.fn<(error: unknown) => { code?: string | null; detail?: string | null } | null>(),
}))

vi.mock('@/features/matching/api', () => ({
  matchKeys: {
    all: ['matches'],
    list: (id: number) => ['matches', 'list', id],
    detail: (id: number) => ['matches', 'detail', id],
  },
  createMatchAnalysis: mocks.createMatchAnalysis,
  fetchMatchAnalyses: mocks.fetchMatchAnalyses,
  fetchMatchAnalysis: vi.fn<() => Promise<unknown>>(),
  recalculateMatchAnalysis: mocks.recalculateMatchAnalysis,
}))

vi.mock('@/api/client', () => ({
  extractProblemDetail: mocks.extractProblemDetail,
}))

function makeOperation(overrides: Partial<MatchOperation> = {}): MatchOperation {
  return {
    id: 9,
    status: 'queued',
    candidate_profile_id: 10,
    job_opportunity_id: 5,
    request_id: null,
    failure_code: null,
    failure_reason: null,
    queued_at: null,
    processing_started_at: null,
    completed_at: null,
    failed_at: null,
    ...overrides,
  }
}

function makeAnalysis(overrides: Partial<MatchAnalysis> = {}): MatchAnalysis {
  return {
    id: 9,
    candidate_profile_id: 10,
    job_opportunity_id: 5,
    status: 'completed',
    overall_score: 84,
    evidence_coverage_score: null,
    counts: { required: 1, preferred: 0, matched: 1, partial: 0, gap: 0, unknown: 0 },
    versions: { algorithm: '1.0', scoring: '1.0', classifier_schema: '1.0' },
    fingerprints: { profile: 'p-1', opportunity: 'o-1' },
    stale: false,
    latest: true,
    warnings: [],
    failure: { code: null, reason: null },
    classifier: {
      provider: null,
      model: null,
      prompt_version: null,
      latency_ms: null,
      tokens_prompt: null,
      tokens_completion: null,
      response_id: null,
      status: null,
    },
    request_id: null,
    score_components: [],
    findings: [],
    timestamps: {
      queued_at: '2026-07-27T10:00:00.000Z',
      processing_started_at: '2026-07-27T10:00:01.000Z',
      completed_at: '2026-07-27T10:00:02.000Z',
      failed_at: null,
      created_at: '2026-07-27T10:00:00.000Z',
      updated_at: '2026-07-27T10:00:02.000Z',
    },
    ...overrides,
  }
}

function listWith(...analyses: MatchAnalysis[]): MatchListResult {
  return {
    data: analyses,
    meta: { path: '', per_page: 20, next_cursor: null, prev_cursor: null },
  }
}

let apiRef: ReturnType<typeof useMatchAnalysis> | undefined
let recalcTrigger: (() => void) | undefined

const TestHost = defineComponent({
  setup() {
    apiRef = useMatchAnalysis(5)
    recalcTrigger = () => {
      const target = apiRef?.completedAnalysis.value
      if (target) apiRef?.recalculateMutation.mutate(target.id)
    }

    return () =>
      h(
        'div',
        JSON.stringify({
          count: apiRef?.analyses.value.length ?? -1,
          firstStatus: apiRef?.analyses.value[0]?.status ?? null,
          code: apiRef?.createProblemCode.value ?? null,
          announcement: apiRef?.announcement.value ?? '',
        }),
      )
  },
})

let wrapper: VueWrapper | undefined
let queryClient: QueryClient | undefined

afterEach(() => {
  wrapper?.unmount()
  queryClient?.clear()
  wrapper = undefined
  queryClient = undefined
  apiRef = undefined
  recalcTrigger = undefined
  window.localStorage.clear()
  vi.unstubAllGlobals()
  vi.clearAllMocks()
})

function mountHost(): VueWrapper {
  vi.stubGlobal('crypto', {
    randomUUID: () => '00000000-0000-4000-8000-000000000000',
  })

  queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        gcTime: 0,
      },
    },
  })

  return mount(TestHost, {
    global: {
      plugins: [[VueQueryPlugin, { queryClient }]],
    },
  })
}

describe('useMatchAnalysis with Vue Query', () => {
  it('auto-creates an analysis exactly once when the list is empty', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith())
    mocks.createMatchAnalysis.mockResolvedValue(makeOperation())

    wrapper = mountHost()
    await flushPromises()
    await flushPromises()

    expect(mocks.createMatchAnalysis).toHaveBeenCalledTimes(1)
    expect(mocks.createMatchAnalysis).toHaveBeenCalledWith(5, expect.any(String))
    expect(wrapper.text()).toContain('"announcement":"Match analysis started."')
  })

  it('does not auto-create when an analysis already exists', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith(makeAnalysis({ status: 'queued' })))

    wrapper = mountHost()
    await flushPromises()

    expect(mocks.createMatchAnalysis).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('"firstStatus":"queued"')
  })

  it('surfaces insufficient_profile and clears the stored idempotency key', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith())
    mocks.createMatchAnalysis.mockRejectedValue(new Error('blocked'))
    mocks.extractProblemDetail.mockReturnValue({
      code: 'insufficient_profile',
      detail: 'Add more trusted skills.',
    })

    wrapper = mountHost()
    await flushPromises()

    expect(wrapper.text()).toContain('"code":"insufficient_profile"')
    expect(window.localStorage.getItem('careerpilot.match.idempotency.5')).toBeNull()
  })

  it('polls while queued and stops polling once the analysis completes', async () => {
    vi.useFakeTimers()
    try {
      mocks.fetchMatchAnalyses
        .mockResolvedValueOnce(listWith(makeAnalysis({ status: 'queued' })))
        .mockResolvedValueOnce(listWith(makeAnalysis({ status: 'completed' })))

      wrapper = mountHost()
      await flushPromises()

      expect(wrapper.text()).toContain('"firstStatus":"queued"')
      const callsAfterFirstFetch = mocks.fetchMatchAnalyses.mock.calls.length

      await vi.advanceTimersByTimeAsync(3000)
      expect(mocks.fetchMatchAnalyses.mock.calls.length).toBe(callsAfterFirstFetch + 1)
      expect(wrapper.text()).toContain('"firstStatus":"completed"')

      const callsAfterCompletion = mocks.fetchMatchAnalyses.mock.calls.length
      await vi.advanceTimersByTimeAsync(9000)
      expect(mocks.fetchMatchAnalyses.mock.calls.length).toBe(callsAfterCompletion)
    } finally {
      vi.useRealTimers()
    }
  })

  it('recalculates the completed analysis', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith(makeAnalysis()))
    mocks.recalculateMatchAnalysis.mockResolvedValue(makeOperation({ id: 11 }))

    wrapper = mountHost()
    await flushPromises()
    expect(mocks.recalculateMatchAnalysis).not.toHaveBeenCalled()

    recalcTrigger?.()
    await flushPromises()

    expect(mocks.recalculateMatchAnalysis).toHaveBeenCalledWith(9)
    expect(wrapper.text()).toContain('"announcement":"Recalculating the match."')
  })

  it('refreshes the list when recalculate is blocked by an active analysis', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith(makeAnalysis()))
    mocks.recalculateMatchAnalysis.mockRejectedValue(new Error('active'))
    mocks.extractProblemDetail.mockReturnValue({
      code: 'active_match_analysis',
      detail: 'An analysis is already running.',
    })

    wrapper = mountHost()
    await flushPromises()
    const callsBefore = mocks.fetchMatchAnalyses.mock.calls.length

    recalcTrigger?.()
    await flushPromises()

    expect(mocks.fetchMatchAnalyses.mock.calls.length).toBeGreaterThan(callsBefore)
    expect(wrapper.text()).toContain('"announcement":"A match analysis is already running."')
  })
})
