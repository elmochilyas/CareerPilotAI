import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'
import ClarificationFlow from '@/features/clarification/components/ClarificationFlow.vue'
import type {
  ClarificationAnswer,
  ClarificationAnswerInput,
  ClarificationProposal,
  ClarificationQuestion,
  ClarificationReviewInput,
  ClarificationSession,
  ClarificationSkipped,
} from '@/features/clarification/types'

const mocks = vi.hoisted(() => ({
  fetchSession: vi.fn<() => Promise<unknown>>(),
  generateSession: vi.fn<() => Promise<unknown>>(),
  answerQuestion: vi.fn<(id: number, input: ClarificationAnswerInput) => Promise<unknown>>(),
  reviewAnswer: vi.fn<(id: number, input: ClarificationReviewInput) => Promise<unknown>>(),
  skipQuestion: vi.fn<(id: number) => Promise<unknown>>(),
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
  generateClarificationSession: mocks.generateSession,
  answerClarificationQuestion: mocks.answerQuestion,
  reviewClarificationAnswer: mocks.reviewAnswer,
  skipClarificationQuestion: mocks.skipQuestion,
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

function makeProposal(overrides: Partial<ClarificationProposal> = {}): ClarificationProposal {
  return {
    id: 21,
    answer_id: 11,
    target: { type: 'candidate_skill', id: 7 },
    field: 'evidence',
    before_value: [],
    after_value: { url: 'https://example.com/cert' },
    status: 'proposed',
    ...overrides,
  }
}

function makeAnswer(overrides: Partial<ClarificationAnswer> = {}): ClarificationAnswer {
  return {
    id: 11,
    question_id: 1,
    answer_type: 'yes',
    value: 'https://example.com/cert',
    acknowledged_no_evidence: false,
    status: 'pending',
    proposal: null,
    ...overrides,
  }
}

function makeQuestion(overrides: Partial<ClarificationQuestion> = {}): ClarificationQuestion {
  return {
    id: 1,
    question_no: 1,
    question_type: 'yes_no_with_details',
    prompt: 'Can you confirm your Laravel experience?',
    detail: 'We ask this to verify a required skill.',
    template_key: 'skill_evidence_confirm',
    options: null,
    unit: null,
    status: 'pending',
    requirement: { text: 'Laravel (required)', label: 'Required skill' },
    answer: null,
    ...overrides,
  }
}

function makeSession(questions: ClarificationQuestion[]): ClarificationSession {
  const pending = questions.filter((question) => question.status === 'pending').length
  return {
    analysis_id: 9,
    questions,
    progress: { answered: questions.length - pending, total: questions.length },
    generable_count: 0,
  }
}

const state: { questions: ClarificationQuestion[] } = { questions: [] }

function replaceQuestion(index: number, updated: ClarificationQuestion): void {
  const next = state.questions.slice()
  next[index] = updated
  state.questions = next
}

function installSession(questions: ClarificationQuestion[]): void {
  state.questions = questions
}

function makeFlowSession(): ClarificationSession {
  return makeSession(state.questions)
}

async function clickButton(page: VueWrapper, label: string): Promise<void> {
  const button = page.findAll('button').find((item) => item.text().trim() === label)
  expect(button, `expected a button labelled "${label}"`).toBeDefined()
  await button!.trigger('click')
}

let wrapper: VueWrapper | undefined
let queryClient: QueryClient | undefined

async function mountFlow(): Promise<VueWrapper> {
  queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity } },
  })
  queryClient.setQueryData(['matches'], { seeded: true })
  queryClient.setQueryData(['skills'], { seeded: true })
  queryClient.setQueryData(['candidate-skills'], { seeded: true })

  wrapper = mount(ClarificationFlow, {
    props: { analysisId: 9 },
    global: { plugins: [[VueQueryPlugin, { queryClient }]] },
  })

  await flushPromises()

  return wrapper
}

describe('ClarificationFlow with Vue Query', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    state.questions = []
    mocks.fetchSession.mockImplementation(async () => makeFlowSession())
    mocks.generateSession.mockImplementation(async () => makeFlowSession())
    mocks.answerQuestion.mockImplementation(
      async (questionId: number, input: ClarificationAnswerInput) => {
        const index = state.questions.findIndex((question) => question.id === questionId)
        const answer = makeAnswer({
          id: questionId + 10,
          question_id: questionId,
          answer_type: input.answer_type,
          value: input.value,
          acknowledged_no_evidence: input.acknowledged_no_evidence,
        })
        replaceQuestion(index, { ...state.questions[index], status: 'answered', answer })
        return answer
      },
    )
    mocks.reviewAnswer.mockImplementation(
      async (questionId: number, input: ClarificationReviewInput) => {
        const index = state.questions.findIndex((question) => question.id === questionId)
        const current = state.questions[index]
        const base = makeProposal({
          id: questionId + 20,
          answer_id: questionId + 10,
          target: { type: 'candidate_skill', id: 7 },
          field: current.question_type === 'number' ? 'years_experience' : 'evidence',
          after_value:
            current.question_type === 'number'
              ? Number(current.answer?.value ?? 0)
              : { url: current.answer?.value ?? 'https://example.com/cert' },
        })
        let proposal = base
        if (input.decision === 'accept') {
          proposal = { ...base, status: 'accepted' }
        } else if (input.decision === 'reject') {
          proposal = { ...base, status: 'rejected' }
        } else if (input.decision === 'skip') {
          proposal = { ...base, status: 'skipped' }
        } else if (input.decision === 'edit') {
          proposal = { ...base, after_value: input.edited_value ?? '', status: 'accepted' }
        }
        const answer = makeAnswer({
          id: questionId + 10,
          question_id: questionId,
          answer_type: current.answer?.answer_type ?? 'yes',
          value: current.answer?.value ?? '',
          acknowledged_no_evidence: current.answer?.acknowledged_no_evidence ?? false,
          proposal,
        })
        replaceQuestion(index, { ...current, status: 'answered', answer })
        return answer
      },
    )
    mocks.skipQuestion.mockImplementation(
      async (questionId: number): Promise<ClarificationSkipped> => {
        const index = state.questions.findIndex((question) => question.id === questionId)
        replaceQuestion(index, { ...state.questions[index], status: 'skipped', answer: null })
        return { id: questionId, status: 'skipped' }
      },
    )
    mocks.extractProblemDetail.mockImplementation((error) => {
      const err = error as { code?: string | null; detail?: string | null } | null
      if (err !== null && typeof err?.code === 'string') {
        return { code: err.code, detail: err.detail ?? null }
      }
      return null
    })
  })

  afterEach(() => {
    wrapper?.unmount()
    queryClient?.clear()
    wrapper = undefined
    queryClient = undefined
  })

  it('shows one question at a time with progress', async () => {
    installSession([
      makeQuestion(),
      makeQuestion({
        id: 2,
        question_no: 2,
        question_type: 'number',
        prompt: 'How many years of Laravel experience?',
        unit: 'years',
      }),
    ])
    const page = await mountFlow()

    expect(page.text()).toContain('Question 1 of 2')
    expect(page.text()).toContain('Can you confirm your Laravel experience?')
    expect(page.text()).not.toContain('How many years of Laravel experience?')
    expect(page.text()).toContain('Submit answer')
  })

  it('answers a question, previews the proposal, and accepts it, invalidating match and skills', async () => {
    installSession([
      makeQuestion(),
      makeQuestion({
        id: 2,
        question_no: 2,
        question_type: 'number',
        prompt: 'How many years of Laravel experience?',
        unit: 'years',
      }),
    ])
    const page = await mountFlow()

    await page.find('input[value="yes"]').setValue(true)
    await page.find('input[name="evidence-url"]').setValue('https://example.com/cert')
    await clickButton(page, 'Submit answer')
    await flushPromises()

    expect(mocks.answerQuestion).toHaveBeenCalledWith(1, {
      answer_type: 'yes',
      value: 'https://example.com/cert',
      acknowledged_no_evidence: false,
    })
    expect(mocks.reviewAnswer).toHaveBeenCalledWith(1, { decision: null })
    expect(page.text()).toContain('Review the proposed change')
    expect(page.text()).toContain('Proposed value')

    await clickButton(page, 'Accept change')
    await flushPromises()

    expect(mocks.reviewAnswer).toHaveBeenCalledWith(1, { decision: 'accept' })
    expect(page.text()).toContain('Question 2 of 2')
    expect(page.text()).not.toContain('Can you confirm your Laravel experience?')
    expect(queryClient!.getQueryState(['matches'])?.isInvalidated).toBe(true)
    expect(queryClient!.getQueryState(['skills'])?.isInvalidated).toBe(true)
    expect(queryClient!.getQueryState(['candidate-skills'])?.isInvalidated).toBe(true)
  })

  it('edits the proposed value before accepting', async () => {
    installSession([
      makeQuestion(),
      makeQuestion({
        id: 2,
        question_no: 2,
        question_type: 'number',
        prompt: 'How many years of Laravel experience?',
        unit: 'years',
      }),
    ])
    const page = await mountFlow()

    await page.find('input[value="yes"]').setValue(true)
    await page.find('input[name="evidence-url"]').setValue('https://example.com/cert')
    await clickButton(page, 'Submit answer')
    await flushPromises()

    await clickButton(page, 'Edit value')
    await page.find('input[name="edited-value"]').setValue('https://example.com/project')
    await clickButton(page, 'Save edit')
    await flushPromises()

    expect(mocks.reviewAnswer).toHaveBeenCalledWith(1, {
      decision: 'edit',
      edited_value: 'https://example.com/project',
    })
    expect(page.text()).toContain('Question 2 of 2')
  })

  it('skips a question from the question step and advances', async () => {
    installSession([
      makeQuestion(),
      makeQuestion({
        id: 2,
        question_no: 2,
        question_type: 'number',
        prompt: 'How many years of Laravel experience?',
        unit: 'years',
      }),
    ])
    const page = await mountFlow()

    await clickButton(page, 'Skip question')
    await flushPromises()

    expect(mocks.skipQuestion).toHaveBeenCalledWith(1)
    expect(page.text()).toContain('Question 2 of 2')
  })

  it('skips a proposal review and advances', async () => {
    installSession([
      makeQuestion(),
      makeQuestion({
        id: 2,
        question_no: 2,
        question_type: 'number',
        prompt: 'How many years of Laravel experience?',
        unit: 'years',
      }),
    ])
    const page = await mountFlow()

    await page.find('input[value="yes"]').setValue(true)
    await page.find('input[name="evidence-url"]').setValue('https://example.com/cert')
    await clickButton(page, 'Submit answer')
    await flushPromises()

    await clickButton(page, 'Skip')
    await flushPromises()

    expect(mocks.reviewAnswer).toHaveBeenCalledWith(1, { decision: 'skip' })
    expect(page.text()).toContain('Question 2 of 2')
  })

  it('completes every question and shows the success confirmation', async () => {
    installSession([
      makeQuestion(),
      makeQuestion({
        id: 2,
        question_no: 2,
        question_type: 'number',
        prompt: 'How many years of Laravel experience?',
        unit: 'years',
      }),
    ])
    const page = await mountFlow()

    await page.find('input[value="yes"]').setValue(true)
    await page.find('input[name="evidence-url"]').setValue('https://example.com/cert')
    await clickButton(page, 'Submit answer')
    await flushPromises()
    await clickButton(page, 'Accept change')
    await flushPromises()

    expect(page.text()).toContain('Question 2 of 2')
    await page.find('input[name="clarification-number"]').setValue('7')
    await clickButton(page, 'Submit answer')
    await flushPromises()
    await clickButton(page, 'Accept change')
    await flushPromises()

    expect(mocks.reviewAnswer).toHaveBeenCalledWith(2, { decision: 'accept' })
    expect(page.text()).toContain("You're all caught up")
    expect(page.text()).toContain('The match is now stale and can be recalculated.')
  })

  it('shows the empty state when the session has no questions and closes', async () => {
    mocks.fetchSession.mockResolvedValue(makeSession([]))
    const page = await mountFlow()

    expect(page.text()).toContain('Nothing to clarify')

    await clickButton(page, 'Done')
    expect(wrapper!.emitted('close')).toHaveLength(1)
  })

  it('generates questions from the empty state and shows them', async () => {
    state.questions = []
    const page = await mountFlow()

    expect(page.text()).toContain('Nothing to clarify')
    expect(page.text()).toContain('Generate questions')

    state.questions = [makeQuestion()]
    await clickButton(page, 'Generate questions')
    await flushPromises()

    expect(mocks.generateSession).toHaveBeenCalledWith(9)
    expect(page.text()).toContain('Can you confirm your Laravel experience?')
    expect(page.text()).not.toContain('Nothing to clarify')
  })

  it('announces when generating is rate limited', async () => {
    state.questions = []
    const page = await mountFlow()

    mocks.generateSession.mockRejectedValue(
      Object.assign(new Error('limited'), {
        code: 'too_many_requests',
        detail: 'Too many requests.',
      }),
    )

    await clickButton(page, 'Generate questions')
    await flushPromises()

    expect(page.find('[aria-live="polite"]').text()).toContain(
      'Please wait a moment before generating again.',
    )
  })

  it('offers a fresh session when the answer endpoint reports an expired session', async () => {
    installSession([makeQuestion()])
    const page = await mountFlow()

    mocks.answerQuestion.mockRejectedValue(
      Object.assign(new Error('expired'), {
        code: 'clarification_session_expired',
        detail: 'This session is no longer active.',
      }),
    )

    await page.find('input[value="yes"]').setValue(true)
    await page.find('input[name="evidence-url"]').setValue('https://example.com/cert')
    await clickButton(page, 'Submit answer')
    await flushPromises()

    expect(page.text()).toContain('This session expired')
    expect(page.text()).toContain('Start a fresh session')

    const callsBefore = mocks.fetchSession.mock.calls.length
    await clickButton(page, 'Start a fresh session')
    await flushPromises()

    expect(mocks.fetchSession.mock.calls.length).toBeGreaterThan(callsBefore)
    expect(page.text()).not.toContain('This session expired')
  })

  it('announces a duplicate answer and refreshes the session', async () => {
    installSession([makeQuestion()])
    const page = await mountFlow()

    const callsBefore = mocks.fetchSession.mock.calls.length
    mocks.answerQuestion.mockRejectedValue(
      Object.assign(new Error('duplicate'), {
        code: 'answer_already_exists',
        detail: 'This question was already answered.',
      }),
    )

    await page.find('input[value="yes"]').setValue(true)
    await page.find('input[name="evidence-url"]').setValue('https://example.com/cert')
    await clickButton(page, 'Submit answer')
    await flushPromises()

    expect(page.find('[aria-live="polite"]').text()).toContain(
      'This question was already answered. Refreshing…',
    )
    expect(mocks.fetchSession.mock.calls.length).toBeGreaterThan(callsBefore)
  })

  it('advances when the review preview reports the answer cannot produce a proposal', async () => {
    installSession([
      makeQuestion({ question_type: 'select', options: ['Full professional'] }),
      makeQuestion({
        id: 2,
        question_no: 2,
        question_type: 'number',
        prompt: 'How many years of Laravel experience?',
        unit: 'years',
      }),
    ])
    const page = await mountFlow()

    mocks.reviewAnswer.mockRejectedValue(
      Object.assign(new Error('unsupported'), {
        code: 'proposal_not_supported',
        detail: 'This answer type does not produce a supported profile change.',
      }),
    )

    await page.find('input[value="Full professional"]').setValue(true)
    await clickButton(page, 'Submit answer')
    await flushPromises()

    expect(mocks.reviewAnswer).toHaveBeenCalledWith(1, { decision: null })
    expect(page.text()).toContain('Question 2 of 2')
    expect(page.text()).not.toContain('Something went wrong')
  })

  it('advances when the review preview returns no proposal', async () => {
    installSession([
      makeQuestion(),
      makeQuestion({
        id: 2,
        question_no: 2,
        question_type: 'number',
        prompt: 'How many years of Laravel experience?',
        unit: 'years',
      }),
    ])
    const page = await mountFlow()

    mocks.reviewAnswer.mockResolvedValue(
      makeAnswer({
        id: 11,
        question_id: 1,
        answer_type: 'yes',
        value: 'https://example.com/cert',
        proposal: null,
      }),
    )

    await page.find('input[value="yes"]').setValue(true)
    await page.find('input[name="evidence-url"]').setValue('https://example.com/cert')
    await clickButton(page, 'Submit answer')
    await flushPromises()

    expect(page.text()).toContain('Question 2 of 2')
    expect(page.text()).not.toContain('Review the proposed change')
  })

  it('shows an error state with a manual retry instead of an infinite loop', async () => {
    vi.useFakeTimers()
    try {
      mocks.fetchSession.mockRejectedValue(new Error('Network unavailable'))

      const page = await mountFlow()
      await vi.advanceTimersByTimeAsync(2000)
      await flushPromises()

      expect(page.find('[role="alert"]').exists()).toBe(true)
      expect(page.text()).toContain('The clarification questions could not be loaded.')

      const callsAfterFailure = mocks.fetchSession.mock.calls.length
      await clickButton(page, 'Retry')
      await flushPromises()

      expect(mocks.fetchSession.mock.calls.length).toBe(callsAfterFailure + 1)
      await vi.advanceTimersByTimeAsync(10000)
      await flushPromises()
      expect(mocks.fetchSession.mock.calls.length).toBeLessThanOrEqual(callsAfterFailure + 2)
    } finally {
      vi.useRealTimers()
    }
  })

  it('closes the flow from the header', async () => {
    installSession([makeQuestion()])
    const page = await mountFlow()

    await page.find('[aria-label="Close clarification flow"]').trigger('click')
    expect(wrapper!.emitted('close')).toHaveLength(1)
  })
})

describe('ClarificationFlow static checks', () => {
  function listVueFiles(dir: string): string[] {
    const files: string[] = []
    for (const entry of readdirSync(dir)) {
      const full = join(dir, entry)
      if (statSync(full).isDirectory()) {
        files.push(...listVueFiles(full))
      } else if (full.endsWith('.vue')) {
        files.push(full)
      }
    }
    return files
  }

  it('never renders untrusted HTML with v-html in the clarification feature', () => {
    const featureRoot = join(process.cwd(), 'src', 'features', 'clarification')
    const files = listVueFiles(featureRoot)

    expect(files.length).toBeGreaterThan(0)
    for (const file of files) {
      expect(readFileSync(file, 'utf8')).not.toMatch(/\bv-html\b/)
    }
  })
})
