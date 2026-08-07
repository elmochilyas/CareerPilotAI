import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import ClarificationEntryCard from '@/features/clarification/components/ClarificationEntryCard.vue'
import type { ClarificationQuestion, ClarificationSession } from '@/features/clarification/types'

const mocks = vi.hoisted(() => ({
  fetchSession: vi.fn<() => Promise<unknown>>(),
  extractProblemDetail:
    vi.fn<(error: unknown) => { code?: string | null; detail?: string | null } | null>(),
}))

vi.mock('@/features/clarification/api', () => ({
  clarificationKeys: {
    all: ['clarifications'],
    session: (analysisId: number) => ['clarifications', 'session', analysisId],
    question: (questionId: number) => ['clarifications', 'question', questionId],
  },
  fetchClarificationSession: mocks.fetchSession,
  answerClarificationQuestion: vi.fn<() => Promise<unknown>>(),
  reviewClarificationAnswer: vi.fn<() => Promise<unknown>>(),
  skipClarificationQuestion: vi.fn<() => Promise<unknown>>(),
}))

vi.mock('@/api/client', () => ({
  extractProblemDetail: mocks.extractProblemDetail,
}))

vi.mock('@/features/matching/api', () => ({
  matchKeys: { all: ['matches'] },
}))

vi.mock('@/features/skills/api', () => ({
  skillKeys: { all: ['skills'], candidate: () => ['candidate-skills'] },
}))

function makeQuestion(overrides: Partial<ClarificationQuestion> = {}): ClarificationQuestion {
  return {
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
    ...overrides,
  }
}

function makeSession(
  questions: ClarificationQuestion[],
  overrides: Partial<ClarificationSession> = {},
): ClarificationSession {
  const pending = questions.filter((question) => question.status === 'pending').length
  return {
    analysis_id: 9,
    questions,
    progress: { answered: questions.length - pending, total: questions.length },
    generable_count: 0,
    ...overrides,
  }
}

let wrapper: VueWrapper | undefined
let queryClient: QueryClient | undefined

async function mountCard(): Promise<VueWrapper> {
  queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: 0 } },
  })

  wrapper = mount(ClarificationEntryCard, {
    props: { analysisId: 9 },
    global: { plugins: [[VueQueryPlugin, { queryClient }]] },
  })

  await flushPromises()

  return wrapper
}

describe('ClarificationEntryCard with Vue Query', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocks.extractProblemDetail.mockReturnValue(null)
  })

  afterEach(() => {
    wrapper?.unmount()
    queryClient?.clear()
    wrapper = undefined
    queryClient = undefined
  })

  it('shows a skeleton while the session loads', async () => {
    mocks.fetchSession.mockReturnValue(new Promise(() => {}))

    const page = await mountCard()

    expect(page.find('.animate-shimmer').exists()).toBe(true)
  })

  it('shows an error state with a manual retry', async () => {
    vi.useFakeTimers()
    try {
      mocks.fetchSession.mockRejectedValue(new Error('Network unavailable'))

      const page = await mountCard()
      await vi.advanceTimersByTimeAsync(2000)
      await flushPromises()

      expect(page.text()).toContain('Clarifications unavailable')
      const retry = page.findAll('button').find((button) => button.text() === 'Retry')!
      await retry.trigger('click')
      await flushPromises()

      expect(mocks.fetchSession.mock.calls.length).toBeGreaterThanOrEqual(2)
    } finally {
      vi.useRealTimers()
    }
  })

  it('prompts the candidate with the open-question count and emits open', async () => {
    mocks.fetchSession.mockResolvedValue(makeSession([makeQuestion()]))

    const page = await mountCard()

    expect(page.text()).toContain('Clarify your profile')
    expect(page.text()).toContain('Answer 1 quick question to improve this match.')

    await page
      .findAll('button')
      .find((button) => button.text() === 'Answer questions')!
      .trigger('click')

    expect(wrapper!.emitted('open')).toHaveLength(1)
  })

  it('uses the plural form when several questions are open', async () => {
    mocks.fetchSession.mockResolvedValue(
      makeSession([
        makeQuestion({ id: 1, question_no: 1 }),
        makeQuestion({ id: 2, question_no: 2 }),
        makeQuestion({ id: 3, question_no: 3 }),
      ]),
    )

    const page = await mountCard()

    expect(page.text()).toContain('Answer 3 quick questions to improve this match.')
  })

  it('renders nothing when there is nothing actionable and nothing to generate', async () => {
    mocks.fetchSession.mockResolvedValue(makeSession([], { generable_count: 0 }))

    const page = await mountCard()

    expect(page.find('div').exists()).toBe(false)
    expect(page.text()).toBe('')
  })

  it('prompts to continue the review when answers await a decision', async () => {
    mocks.fetchSession.mockResolvedValue(
      makeSession([makeQuestion({ id: 1, question_no: 1, status: 'answered', answer: null })]),
    )

    const page = await mountCard()

    expect(page.text()).toContain('Review a profile change')
    expect(page.text()).toContain('You have a pending profile change to review.')

    await page
      .findAll('button')
      .find((button) => button.text() === 'Continue review')!
      .trigger('click')

    expect(wrapper!.emitted('open')).toHaveLength(1)
  })

  it('offers to generate questions when the analysis can produce more', async () => {
    mocks.fetchSession.mockResolvedValue(makeSession([], { generable_count: 3 }))

    const page = await mountCard()

    expect(page.text()).toContain('Clarify your profile')
    expect(page.text()).toContain('Generate a few quick questions to improve this match.')

    await page
      .findAll('button')
      .find((button) => button.text() === 'Generate questions')!
      .trigger('click')

    expect(wrapper!.emitted('open')).toHaveLength(1)
  })
})
