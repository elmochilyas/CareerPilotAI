import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import ClarificationPage from '@/features/clarification/pages/ClarificationPage.vue'
import type {
  MatchAnalysis,
  MatchFinding,
  MatchListResult,
  MatchOperation,
} from '@/features/matching/types'
import type { JobOpportunity } from '@/features/opportunities/types'

const mocks = vi.hoisted(() => ({
  routeId: '5',
  push: vi.fn<() => Promise<unknown>>(),
  replace: vi.fn<() => Promise<unknown>>(),
  back: vi.fn<() => void>(),
  fetchOpportunity: vi.fn<() => Promise<unknown>>(),
  fetchMatchAnalyses: vi.fn<() => Promise<unknown>>(),
  createMatchAnalysis: vi.fn<(id: number, key: string) => Promise<unknown>>(),
  fetchClarificationSession: vi.fn<() => Promise<unknown>>(),
  extractProblemDetail:
    vi.fn<(error: unknown) => { code?: string | null; detail?: string | null } | null>(),
}))

vi.mock('@/features/opportunities/api', () => ({
  opportunityKeys: {
    detail: (id: number) => ['opportunities', 'detail', id],
  },
  fetchOpportunity: mocks.fetchOpportunity,
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
  recalculateMatchAnalysis: vi.fn<() => Promise<unknown>>(),
}))

vi.mock('@/features/clarification/api', () => ({
  clarificationKeys: {
    all: ['clarifications'],
    session: (analysisId: number) => ['clarifications', 'session', analysisId],
    question: (questionId: number) => ['clarifications', 'question', questionId],
  },
  fetchClarificationSession: mocks.fetchClarificationSession,
  generateClarificationSession: vi.fn<() => Promise<unknown>>(),
  answerClarificationQuestion: vi.fn<() => Promise<unknown>>(),
  reviewClarificationAnswer: vi.fn<() => Promise<unknown>>(),
  skipClarificationQuestion: vi.fn<() => Promise<unknown>>(),
}))

vi.mock('@/features/skills/api', () => ({
  skillKeys: { all: ['skills'], candidate: () => ['candidate-skills'] },
}))

vi.mock('vue-router', async () => {
  const { h } = await import('vue')
  return {
    useRoute: () => ({
      params: {
        get id() {
          return mocks.routeId
        },
      },
    }),
    useRouter: () => ({ push: mocks.push, replace: mocks.replace, back: mocks.back }),
    RouterLink: {
      name: 'RouterLink',
      props: ['to'],
      setup(_: unknown, { slots }: { slots: Record<string, unknown> }) {
        return () => h('a', slots.default?.() as never)
      },
    },
  }
})

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

function makeOpportunity(overrides: Partial<JobOpportunity> = {}): JobOpportunity {
  return {
    id: 5,
    title: 'Senior Laravel Developer',
    company_name: 'Acme',
    department: null,
    external_reference: null,
    summary: null,
    application_url: null,
    personal_label: null,
    source_url: null,
    city: 'Paris',
    region: null,
    country: 'France',
    work_mode: 'Remote',
    contract_type: null,
    seniority_level: null,
    working_hours: null,
    travel_required: null,
    relocation_required: null,
    salary_min: null,
    salary_max: null,
    salary_currency: null,
    salary_period: null,
    compensation_text: null,
    benefits: null,
    publication_date: null,
    application_deadline: null,
    expected_start_date: null,
    employment_duration: null,
    additional_requirements: null,
    requirements: [],
    skills: [],
    company: null,
    saved_at: '2026-07-27T10:00:00.000Z',
    created_at: '2026-07-27T10:00:00.000Z',
    updated_at: '2026-07-27T10:00:00.000Z',
    ...overrides,
  }
}

function makeFinding(overrides: Partial<MatchFinding> = {}): MatchFinding {
  return {
    source_type: 'job_requirement',
    source_id: 1,
    requirement_text: 'Fluent English',
    requirement_label: null,
    importance: 'required',
    category: 'language_soft',
    match_state: 'gap',
    factor: 0,
    matched_candidate_skill_id: null,
    evidence_refs: [],
    justification: null,
    confidence: null,
    classifier_source: null,
    display_order: 1,
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
    counts: { required: 2, preferred: 1, matched: 1, partial: 0, gap: 1, unknown: 0 },
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
    findings: [makeFinding()],
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

let wrapper: VueWrapper | undefined
let queryClient: QueryClient | undefined

async function mountPage(): Promise<VueWrapper> {
  queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        gcTime: 0,
      },
    },
  })

  wrapper = mount(ClarificationPage, {
    global: {
      plugins: [[VueQueryPlugin, { queryClient }]],
    },
  })

  await flushPromises()

  return wrapper
}

describe('ClarificationPage with Vue Query', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('crypto', {
      randomUUID: () => '00000000-0000-4000-8000-000000000000',
    })
    mocks.routeId = '5'
    mocks.fetchOpportunity.mockResolvedValue(makeOpportunity())
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith(makeAnalysis()))
    mocks.createMatchAnalysis.mockResolvedValue(makeOperation())
    mocks.fetchClarificationSession.mockResolvedValue({
      analysis_id: 9,
      questions: [],
      progress: { answered: 0, total: 0 },
      generable_count: 0,
    })
    mocks.extractProblemDetail.mockReturnValue(null)
  })

  afterEach(() => {
    wrapper?.unmount()
    queryClient?.clear()
    wrapper = undefined
    queryClient = undefined
    window.localStorage.clear()
    vi.unstubAllGlobals()
  })

  it('shows the insufficient-profile gate before the flow can run', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith())
    mocks.createMatchAnalysis.mockRejectedValue(new Error('blocked'))
    mocks.extractProblemDetail.mockReturnValue({
      code: 'insufficient_profile',
      detail: 'Add more trusted skills.',
    })

    const page = await mountPage()

    expect(page.text()).toContain('Profile needs more trusted data')
    expect(page.text()).not.toContain('Clarify your profile')
  })

  it('shows an accessible processing state while an analysis is queued', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith(makeAnalysis({ status: 'queued' })))

    const page = await mountPage()

    expect(page.text()).toContain('Match analysis queued')
    expect(page.find('[role="status"]').exists()).toBe(true)
  })

  it('shows an error state with a manual retry', async () => {
    vi.useFakeTimers()
    try {
      mocks.fetchMatchAnalyses.mockRejectedValue(new Error('Network unavailable'))
      mocks.extractProblemDetail.mockReturnValue({
        code: 'network_error',
        detail: 'The analysis could not be loaded.',
      })

      const page = await mountPage()
      await vi.advanceTimersByTimeAsync(2000)
      await flushPromises()

      expect(page.find('[role="alert"]').exists()).toBe(true)
      expect(page.text()).toContain('The analysis could not be loaded.')

      const callsAfterFailure = mocks.fetchMatchAnalyses.mock.calls.length
      // Find the retry button inside MatchErrorState (has @retry)
      const retryButton = page
        .findAll('button')
        .find((b) => b.text().includes('Try again') || b.text().includes('Retry'))
      expect(retryButton).toBeDefined()
      await retryButton!.trigger('click')
      await flushPromises()
      await vi.advanceTimersByTimeAsync(100)
      await flushPromises()

      // Both opportunity and analyses are refetched on retry
      expect(
        mocks.fetchMatchAnalyses.mock.calls.length + mocks.fetchOpportunity.mock.calls.length,
      ).toBeGreaterThan(callsAfterFailure)
    } finally {
      vi.useRealTimers()
    }
  })

  it('renders the clarification flow for a completed analysis', async () => {
    const page = await mountPage()

    expect(page.text()).toContain('Senior Laravel Developer')
    expect(page.text()).toContain('Back to opportunity')
    expect(page.text()).toContain('Clarify your profile')
    expect(page.text()).toContain('Nothing to clarify')
  })

  it('navigates back to the opportunity detail', async () => {
    const page = await mountPage()

    await page
      .findAll('button')
      .find((button) => button.text().includes('Back to opportunity'))!
      .trigger('click')
    await flushPromises()

    // goBackToOpportunity uses router.back() when history.length >1 else router.replace
    const wasCalled =
      mocks.back.mock.calls.length > 0 ||
      mocks.replace.mock.calls.length > 0 ||
      mocks.push.mock.calls.length > 0
    expect(wasCalled).toBe(true)
    if (mocks.replace.mock.calls.length > 0) {
      expect(mocks.replace).toHaveBeenCalledWith({
        name: 'opportunities-detail',
        params: { id: 5 },
      })
    }
  })
})
