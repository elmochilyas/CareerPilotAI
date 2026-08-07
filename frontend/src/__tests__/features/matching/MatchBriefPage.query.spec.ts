import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import MatchBriefPage from '@/features/matching/pages/MatchBriefPage.vue'
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
  fetchOpportunity: vi.fn<() => Promise<unknown>>(),
  fetchMatchAnalyses: vi.fn<() => Promise<unknown>>(),
  createMatchAnalysis: vi.fn<(id: number, key: string) => Promise<unknown>>(),
  recalculateMatchAnalysis: vi.fn<(id: number) => Promise<unknown>>(),
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
  recalculateMatchAnalysis: mocks.recalculateMatchAnalysis,
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
    useRouter: () => ({ push: mocks.push }),
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
    score_components: [
      {
        category: 'required_skills',
        weight: 0.5,
        score: 80,
        achieved_points: 80,
        total_points: 100,
        has_candidate_data: true,
      },
      {
        category: 'preferred_skills',
        weight: 0.2,
        score: 60,
        achieved_points: 60,
        total_points: 100,
        has_candidate_data: true,
      },
      {
        category: 'evidence',
        weight: 0.15,
        score: 70,
        achieved_points: 70,
        total_points: 100,
        has_candidate_data: true,
      },
      {
        category: 'experience_education',
        weight: 0.1,
        score: 50,
        achieved_points: 50,
        total_points: 100,
        has_candidate_data: true,
      },
      {
        category: 'language_soft',
        weight: 0.05,
        score: 100,
        achieved_points: 100,
        total_points: 100,
        has_candidate_data: true,
      },
    ],
    findings: [
      makeFinding({
        source_id: 1,
        requirement_text: 'API design',
        match_state: 'gap',
        factor: 0,
        evidence_refs: [],
      }),
      makeFinding({
        source_id: 2,
        requirement_text: 'Laravel',
        match_state: 'matched',
        factor: 1,
        evidence_refs: [{ type: 'candidate_skill', id: 7, label: 'Laravel — Intermediate' }],
      }),
      makeFinding({
        source_id: 3,
        requirement_text: 'Redis',
        importance: 'preferred',
        match_state: 'matched',
        factor: 1,
        evidence_refs: [{ type: 'candidate_skill', id: 8, label: 'Redis — Basic' }],
      }),
    ],
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

  wrapper = mount(MatchBriefPage, {
    global: {
      plugins: [[VueQueryPlugin, { queryClient }]],
    },
  })

  await flushPromises()

  return wrapper
}

describe('MatchBriefPage with Vue Query', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('crypto', {
      randomUUID: () => '00000000-0000-4000-8000-000000000000',
    })
    mocks.routeId = '5'
    mocks.fetchOpportunity.mockResolvedValue(makeOpportunity())
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith(makeAnalysis()))
    mocks.createMatchAnalysis.mockResolvedValue(makeOperation())
    mocks.recalculateMatchAnalysis.mockResolvedValue(makeOperation({ id: 11 }))
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

  it('shows the insufficient-profile gate without a numeric score', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith())
    mocks.createMatchAnalysis.mockRejectedValue(new Error('blocked'))
    mocks.extractProblemDetail.mockReturnValue({
      code: 'insufficient_profile',
      detail: 'Add more trusted skills.',
    })

    const page = await mountPage()

    expect(page.text()).toContain('Profile needs more trusted data')
    expect(page.text()).toContain('50% completion')
    expect(page.text()).not.toMatch(/\b\d+\s*\/\s*100\b/)
  })

  it('shows an accessible processing state while an analysis is queued', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith(makeAnalysis({ status: 'queued' })))

    const page = await mountPage()

    expect(page.text()).toContain('Match analysis queued')
    expect(page.find('[role="status"]').exists()).toBe(true)
  })

  it('renders the brief with context, score summary, and a collapsed full analysis', async () => {
    const page = await mountPage()

    expect(page.text()).toContain('Senior Laravel Developer')
    expect(page.text()).toContain('Acme')
    expect(page.text()).toContain('Paris, France')
    expect(page.text()).toContain('84%')
    expect(page.text()).toContain('Match')
    expect(page.text()).toContain('Good match')
    expect(page.text()).toContain('You match 1 of 2 important requirements.')
    expect(page.text()).toContain('1 important gap needs attention.')
    expect(page.text()).toContain('Your match at a glance')
    expect(page.find('[aria-label="Strengths"]').text()).toContain('Laravel')
    expect(page.find('[aria-label="Needs attention"]').text()).toContain('API design')

    expect(page.find('#full-analysis').attributes('open')).toBeUndefined()
    expect(page.find('#full-analysis summary').text()).toContain('Full analysis')
    expect(page.find('#score-details').attributes('open')).toBeUndefined()
    expect(page.text()).not.toContain('Requirement results')
  })

  it('composes the match-state pills and importance checkboxes', async () => {
    const page = await mountPage()

    await page.find('#full-analysis summary').trigger('click')
    expect(page.find('#full-analysis').attributes('open')).toBeDefined()

    const pills = page.find('[aria-label="Filter by result"]').findAll('button')
    const needsAttention = pills.find((button) => button.text().startsWith('Needs attention'))!
    const allPill = pills.find((button) => button.text() === 'All')!

    await needsAttention.trigger('click')
    let workspace = page.find('[aria-label="Match requirements"]')
    expect(workspace.text()).toContain('API design')
    expect(workspace.text()).not.toContain('Laravel')
    expect(workspace.text()).not.toContain('Redis')

    await allPill.trigger('click')
    workspace = page.find('[aria-label="Match requirements"]')
    expect(workspace.text()).toContain('Laravel')
    expect(workspace.text()).toContain('Redis')

    await page.get('#filter-required').setValue(true)
    workspace = page.find('[aria-label="Match requirements"]')
    expect(workspace.text()).toContain('API design')
    expect(workspace.text()).toContain('Laravel')
    expect(workspace.text()).not.toContain('Redis')
  })

  it('shows an empty workspace when the analysis has no findings', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith(makeAnalysis({ findings: [] })))

    const page = await mountPage()

    await page.find('#full-analysis summary').trigger('click')

    expect(page.text()).toContain('No requirement results')
  })

  it('shows the stale notice and recalculates the completed analysis', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(listWith(makeAnalysis({ stale: true })))

    const page = await mountPage()
    const recalculateButton = page
      .findAll('button')
      .find((button) => button.text() === 'Recalculate')

    expect(page.text()).toContain('Profile or opportunity changed since this analysis')
    expect(page.text()).toContain('84%')

    await recalculateButton!.trigger('click')
    await flushPromises()

    expect(mocks.recalculateMatchAnalysis).toHaveBeenCalledWith(9)
  })

  it('explains in human language when the classifier was unavailable, without hiding the score', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(
      listWith(
        makeAnalysis({
          classifier: {
            provider: null,
            model: null,
            prompt_version: null,
            latency_ms: null,
            tokens_prompt: null,
            tokens_completion: null,
            response_id: null,
            status: 'unavailable',
          },
          findings: [
            makeFinding({
              source_id: 1,
              requirement_text: 'Lead backend microservices',
              match_state: 'unknown',
              factor: 0,
              evidence_refs: [],
            }),
          ],
          counts: { required: 2, preferred: 1, matched: 1, partial: 0, gap: 0, unknown: 1 },
        }),
      ),
    )

    const page = await mountPage()

    const classifierNotice = page.find('header details')
    expect(classifierNotice.attributes('open')).toBeUndefined()
    expect(classifierNotice.text()).toContain(
      "Some requirements couldn't be verified automatically. Your score is based on the information we could confirm.",
    )
    expect(classifierNotice.find('summary').text()).toContain('Learn more')
    expect(page.text()).toContain('84%')
    expect(page.text()).toContain('1 requirement could not be fully evaluated.')
    expect(page.find('[aria-label="Needs attention"]').text()).not.toContain(
      'Lead backend microservices',
    )

    await classifierNotice.find('summary').trigger('click')
    expect(classifierNotice.attributes('open')).toBeDefined()
    expect(classifierNotice.text()).toContain('semantic comparison was unavailable')

    await page
      .find('[aria-labelledby="at-a-glance"]')
      .findAll('button')
      .find((button) => button.text().includes('Review them'))!
      .trigger('click')

    expect(page.find('#full-analysis').attributes('open')).toBeDefined()
    expect(page.find('[aria-label="Match requirements"]').text()).toContain(
      'Lead backend microservices',
    )
  })

  it('keeps unknown requirements out of the strengths and gaps groups', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(
      listWith(
        makeAnalysis({
          findings: [
            makeFinding({
              source_id: 1,
              requirement_text: 'German C1',
              category: 'language_soft',
              match_state: 'gap',
              evidence_refs: [],
            }),
            makeFinding({
              source_id: 2,
              requirement_text: 'Lead backend microservices',
              match_state: 'unknown',
              evidence_refs: [],
            }),
            makeFinding({
              source_id: 3,
              requirement_text: 'Laravel',
              match_state: 'matched',
              evidence_refs: [{ type: 'candidate_skill', id: 7, label: 'Laravel — Intermediate' }],
            }),
          ],
        }),
      ),
    )

    const page = await mountPage()

    const strengths = page.find('[aria-label="Strengths"]').text()
    const needsAttention = page.find('[aria-label="Needs attention"]').text()
    expect(strengths).toContain('Laravel')
    expect(needsAttention).toContain('German C1')
    expect(strengths).not.toContain('Lead backend microservices')
    expect(needsAttention).not.toContain('Lead backend microservices')
    expect(page.text()).toContain('1 requirement could not be fully evaluated.')

    const reviewThem = page
      .find('[aria-labelledby="at-a-glance"]')
      .findAll('button')
      .find((button) => button.text().includes('Review them'))!
    await reviewThem.trigger('click')

    expect(page.find('#full-analysis').attributes('open')).toBeDefined()
    const workspace = page.find('[aria-label="Match requirements"]')
    expect(workspace.text()).toContain('Lead backend microservices')
    expect(workspace.text()).toContain('German C1')
  })

  it('renders each gap exactly once in the at-a-glance summary', async () => {
    mocks.fetchMatchAnalyses.mockResolvedValue(
      listWith(
        makeAnalysis({
          findings: [
            makeFinding({
              source_id: 1,
              requirement_text: 'German C1',
              category: 'language_soft',
              match_state: 'gap',
              evidence_refs: [],
            }),
            makeFinding({
              source_id: 2,
              requirement_text: 'Laravel',
              match_state: 'matched',
              evidence_refs: [{ type: 'candidate_skill', id: 7, label: 'Laravel — Intermediate' }],
            }),
          ],
        }),
      ),
    )

    const page = await mountPage()
    const atAGlance = page.find('[aria-labelledby="at-a-glance"]')

    const occurrences = atAGlance.text().split('German C1').length - 1
    expect(occurrences).toBe(1)
  })

  it('reveals requirement evidence and reason by expanding a row', async () => {
    const page = await mountPage()

    await page.find('#full-analysis summary').trigger('click')

    const gapRow = page
      .findAll('#full-analysis details')
      .find((row) => row.text().includes('API design'))!
    expect(gapRow.attributes('open')).toBeUndefined()
    await gapRow.find('summary').trigger('click')
    expect(gapRow.attributes('open')).toBeDefined()
    expect(gapRow.text()).toContain('Job requires')
    expect(gapRow.text()).toContain('No matching profile evidence found.')

    const matchedRow = page
      .findAll('#full-analysis details')
      .find((row) => row.text().includes('Laravel'))!
    await matchedRow.find('summary').trigger('click')
    expect(matchedRow.text()).toContain('Laravel — Intermediate')
    expect(matchedRow.text()).toContain('Why')
    expect(matchedRow.text()).toContain('This matches your profile.')
  })

  it('hides the score breakdown by default and reveals it on demand', async () => {
    const page = await mountPage()

    const scoreDetails = page.find('#score-details')
    expect(scoreDetails.attributes('open')).toBeUndefined()

    await scoreDetails.find('summary').trigger('click')

    expect(scoreDetails.attributes('open')).toBeDefined()
    expect(scoreDetails.text()).toContain('Required skills')
    expect(scoreDetails.text()).toContain('Experience & education')
    expect(scoreDetails.text()).toContain('80%')
    expect(scoreDetails.text()).toContain('Evidence coverage')
    expect(scoreDetails.text()).toContain('not measured')
    expect(scoreDetails.text()).toContain('Analysis updated')
  })

  it('shows an error state with a manual retry instead of an infinite loop', async () => {
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
      const retryButton = page.find('button')
      await retryButton.trigger('click')
      await flushPromises()

      expect(mocks.fetchMatchAnalyses.mock.calls.length).toBe(callsAfterFailure + 1)
      await vi.advanceTimersByTimeAsync(10000)
      await flushPromises()
      expect(mocks.fetchMatchAnalyses.mock.calls.length).toBeLessThanOrEqual(callsAfterFailure + 2)
    } finally {
      vi.useRealTimers()
    }
  })

  it('navigates to the clarification route from the entry card', async () => {
    mocks.fetchClarificationSession.mockResolvedValue({
      analysis_id: 9,
      questions: [
        {
          id: 1,
          question_no: 1,
          question_type: 'yes_no',
          prompt: 'Do you have professional Laravel experience?',
          detail: null,
          template_key: 'skill_evidence_confirm',
          options: null,
          unit: null,
          status: 'pending',
          requirement: { text: 'Laravel (required)', label: 'Required skill' },
          answer: null,
        },
      ],
      progress: { answered: 0, total: 1 },
      generable_count: 0,
    })

    const page = await mountPage()

    await page
      .findAll('button')
      .find((button) => button.text() === 'Answer questions')!
      .trigger('click')
    await flushPromises()

    expect(mocks.push).toHaveBeenCalledWith({
      name: 'opportunities-match-clarifications',
      params: { id: 5 },
    })
  })
})
