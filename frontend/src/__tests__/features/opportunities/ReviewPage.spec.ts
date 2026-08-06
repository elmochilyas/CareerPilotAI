import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import ReviewPage from '@/features/opportunities/pages/ReviewPage.vue'

const { addManualSuggestionMock, updateSuggestionMock, batchUpdateSuggestionsMock, useQueryMock } =
  vi.hoisted(() => ({
    addManualSuggestionMock: vi.fn<(...args: unknown[]) => Promise<unknown>>(() =>
      Promise.resolve({}),
    ),
    updateSuggestionMock: vi.fn<(...args: unknown[]) => Promise<unknown>>(() =>
      Promise.resolve({}),
    ),
    batchUpdateSuggestionsMock: vi.fn<(...args: unknown[]) => Promise<unknown>>(() =>
      Promise.resolve([]),
    ),
    useQueryMock: vi.fn<(...args: unknown[]) => unknown>(),
  }))

vi.mock('@tanstack/vue-query', () => ({
  useQuery: useQueryMock,
  useMutation: vi.fn<(...args: unknown[]) => unknown>(() => ({
    mutate: vi.fn<() => void>(),
    mutateAsync: vi.fn<() => Promise<void>>(() => Promise.resolve()),
    isPending: ref(false),
    data: ref(undefined),
  })),
  useQueryClient: vi.fn<(...args: unknown[]) => unknown>(() => ({
    invalidateQueries: vi.fn<() => void>(),
  })),
}))

vi.mock('@/features/opportunities/api', () => ({
  opportunityKeys: {
    all: ['opportunities'],
    ingestions: () => ['opportunities', 'ingestions'],
    ingestion: (id: number) => ['opportunities', 'ingestions', id],
    suggestions: (id: number) => ['opportunities', 'ingestions', id, 'suggestions'],
    preview: (id: number) => ['opportunities', 'ingestions', id, 'preview'],
    list: () => ['opportunities', 'list'],
    detail: (id: number) => ['opportunities', 'detail', id],
  },
  fetchIngestion: vi.fn<() => void>(),
  fetchSuggestions: vi.fn<() => void>(),
  addManualSuggestion: addManualSuggestionMock,
  updateSuggestion: updateSuggestionMock,
  batchUpdateSuggestions: batchUpdateSuggestionsMock,
  generatePreview: vi.fn<() => Promise<void>>(() => Promise.resolve({ version_token: 'abc' })),
  confirmIngestion: vi.fn<() => Promise<void>>(() => Promise.resolve({ id: 1 })),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn<() => void>(), back: vi.fn<() => void>() }),
  useRoute: () => ({ params: { id: '1' } }),
}))

vi.mock('@/api/client', () => ({
  extractProblemDetail: vi.fn<() => null>(() => null),
}))

function queryResult(data: unknown) {
  return {
    data: ref(data),
    isPending: ref(false),
    isError: ref(false),
    refetch: vi.fn<() => void>(),
  }
}

function mountReviewPage(
  suggestions: unknown[] | undefined = undefined,
  ingestion: unknown = { id: 1, version: 4 },
) {
  useQueryMock.mockReset()
  useQueryMock
    .mockReturnValueOnce(queryResult(ingestion))
    .mockReturnValueOnce(queryResult(suggestions))

  return mount(ReviewPage)
}

function createSuggestions(total: number, reviewed: number) {
  return Array.from({ length: total }, (_, index) => ({
    id: index + 1,
    type: 'job_title',
    review_decision: index < reviewed ? 'accepted' : 'pending',
    resolution: 'exact',
    extracted_value: { value: `Opportunity ${index + 1}` },
    edited_value: null,
    source_evidence: null,
    schema_version: '1.0.0',
    version: 7,
  }))
}

describe('ReviewPage', () => {
  it('renders the evidence-review header and description', () => {
    const wrapper = mountReviewPage()

    expect(wrapper.text()).toContain('Review job information')
    expect(wrapper.text()).toContain(
      'Review each extracted detail before saving this job opportunity.',
    )
  })

  it('shows only the final step and no progress section with zero suggestions', () => {
    const wrapper = mountReviewPage([])

    expect(wrapper.text()).toContain('Step 1 of 1')
    expect(wrapper.text()).toContain('No extracted items to review')
    expect(wrapper.text()).toContain('Final review')
    expect(wrapper.text()).toContain('Decisions remaining')
    expect(wrapper.text()).toContain('Generate preview')
  })

  it('calculates overall progress once from real suggestions', () => {
    const wrapper = mountReviewPage(createSuggestions(22, 9))
    const progressbar = wrapper.find('[role="progressbar"]')

    expect(wrapper.text()).toContain('9 of 22 reviewed')
    expect(wrapper.text()).not.toContain('18 of 44')
    expect(progressbar.attributes('aria-valuenow')).toBe('9')
    expect(progressbar.attributes('aria-valuemax')).toBe('22')
  })

  it('renders job context from suggestion data', () => {
    const suggestions = [
      {
        id: 1,
        type: 'job_title',
        review_decision: 'pending',
        resolution: null,
        extracted_value: { value: 'Backend Developer' },
        edited_value: null,
        source_evidence: null,
        schema_version: '1.0.0',
      },
      {
        id: 2,
        type: 'company',
        review_decision: 'pending',
        resolution: null,
        extracted_value: { value: 'Tech Corp' },
        edited_value: null,
        source_evidence: null,
        schema_version: '1.0.0',
      },
    ]
    const wrapper = mountReviewPage(suggestions)

    expect(wrapper.text()).toContain('Backend Developer')
    expect(wrapper.text()).toContain('Tech Corp')
  })

  it('keeps dynamic steps visible and supports direct final-step selection', async () => {
    const wrapper = mountReviewPage(createSuggestions(2, 1))

    const rail = wrapper.find('nav[aria-label="Opportunity review sections"]')
    const buttons = rail.findAll('button')

    expect(buttons).toHaveLength(2)
    expect(buttons[0]?.text()).toContain('Overview')
    expect(buttons[1]?.text()).toContain('Final review')

    await buttons[1]?.trigger('click')

    expect(wrapper.text()).toContain('Step 2 of 2')
    expect(wrapper.text()).toContain('Decisions remaining')
  })

  it('renders the action footer with review progress', () => {
    const wrapper = mountReviewPage(createSuggestions(5, 2))

    expect(wrapper.text()).toContain('Step 1 of 2')
    expect(wrapper.text()).toContain('2 of 5 reviewed')
  })

  it('does not expose raw JSON in the page', () => {
    const wrapper = mountReviewPage(createSuggestions(3, 2))

    expect(wrapper.text()).not.toContain('{"value"')
    expect(wrapper.text()).not.toContain('[object Object]')
  })

  it('does not show (blank) fallback text', () => {
    const wrapper = mountReviewPage(createSuggestions(3, 1))

    expect(wrapper.text()).not.toContain('(blank)')
  })

  it('keeps the original value visible when a scalar suggestion is excluded', () => {
    const [suggestion] = createSuggestions(1, 1)
    const wrapper = mountReviewPage([
      {
        ...suggestion,
        review_decision: 'rejected',
        extracted_value: { value: 'Backend Developer' },
      },
    ])

    expect(wrapper.text()).toContain('Backend Developer')
    expect(wrapper.text()).toContain('Excluded from opportunity')
  })

  it('sends the current suggestion version with a decision', async () => {
    updateSuggestionMock.mockClear()
    const wrapper = mountReviewPage(createSuggestions(1, 0))
    const keepButton = wrapper
      .findAll('button')
      .find((button) => button.text().trim() === 'Include')

    await keepButton?.trigger('click')
    await flushPromises()

    expect(updateSuggestionMock).toHaveBeenCalledWith(1, 1, {
      decision: 'accepted',
      version: 7,
      edited_value: undefined,
      resolved_skill_id: undefined,
    })
  })

  it('maps a scalar edit back to the production suggestion payload shape', async () => {
    updateSuggestionMock.mockClear()
    const wrapper = mountReviewPage(createSuggestions(1, 0))
    const editButton = wrapper.findAll('button').find((button) => button.text().trim() === 'Edit')

    await editButton?.trigger('click')
    await wrapper.get('input').setValue('Principal Platform Engineer')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(updateSuggestionMock).toHaveBeenCalledWith(1, 1, {
      decision: 'edited',
      version: 7,
      edited_value: { value: 'Principal Platform Engineer' },
      resolved_skill_id: undefined,
    })
  })

  it('adds a missing responsibility with the current ingestion version', async () => {
    addManualSuggestionMock.mockClear()
    const responsibility = {
      ...createSuggestions(1, 1)[0],
      type: 'responsibility',
      extracted_value: { text: 'Maintain APIs.' },
    }
    const wrapper = mountReviewPage([responsibility], { id: 1, version: 4 })
    const addButton = wrapper
      .findAll('button')
      .find((button) => button.text().trim() === 'Add responsibility')

    await addButton?.trigger('click')
    await wrapper.get('#manual-responsibility').setValue('Document public APIs.')
    await wrapper.get('.manual-add-form').trigger('submit')
    await flushPromises()

    expect(addManualSuggestionMock).toHaveBeenCalledWith(1, {
      type: 'responsibility',
      value: 'Document public APIs.',
      ingestion_version: 4,
    })
  })

  it('accepts all pending suggestions after a two-step confirm', async () => {
    batchUpdateSuggestionsMock.mockClear()
    const wrapper = mountReviewPage(createSuggestions(3, 0))
    const acceptAllButton = wrapper
      .findAll('button')
      .find((button) => button.text().startsWith('Accept all'))

    expect(acceptAllButton?.text()).toBe('Accept all remaining (3)')

    await acceptAllButton?.trigger('click')
    expect(acceptAllButton?.text()).toBe('Accept all — click again to confirm (3)')
    expect(batchUpdateSuggestionsMock).not.toHaveBeenCalled()

    await acceptAllButton?.trigger('click')
    await flushPromises()

    expect(batchUpdateSuggestionsMock).toHaveBeenCalledWith(1, [
      { id: 1, decision: 'accepted', version: 7 },
      { id: 2, decision: 'accepted', version: 7 },
      { id: 3, decision: 'accepted', version: 7 },
    ])
  })

  it('accepts ambiguous skills as resolved unknown labels during accept all', async () => {
    batchUpdateSuggestionsMock.mockClear()
    const [scalar] = createSuggestions(1, 0)
    const wrapper = mountReviewPage([
      scalar,
      {
        ...scalar,
        id: 2,
        type: 'required_skill',
        resolution: 'ambiguous',
        extracted_value: { label: 'Spring' },
      },
    ])

    const acceptAllButton = wrapper
      .findAll('button')
      .find((button) => button.text().startsWith('Accept all'))

    await acceptAllButton?.trigger('click')
    await acceptAllButton?.trigger('click')
    await flushPromises()

    expect(batchUpdateSuggestionsMock).toHaveBeenCalledWith(1, [
      { id: 1, decision: 'accepted', version: 7 },
      { id: 2, decision: 'resolved', version: 7, resolved_skill_id: null },
    ])
  })

  it('disables accept all when no decisions remain', () => {
    const wrapper = mountReviewPage(createSuggestions(2, 2))

    const acceptAllButton = wrapper
      .findAll('button')
      .find((button) => button.text().startsWith('Accept all'))

    expect(wrapper.text()).toContain('All decisions made')
    expect(acceptAllButton?.attributes('disabled')).toBeDefined()
  })

  it('refetches and announces a stale-mutation error from accept all', async () => {
    batchUpdateSuggestionsMock.mockClear()
    batchUpdateSuggestionsMock.mockRejectedValueOnce({
      response: {
        data: { code: 'stale_mutation', detail: 'Suggestion has been modified. Please refresh.' },
      },
    })

    const wrapper = mountReviewPage(createSuggestions(2, 0))
    const acceptAllButton = wrapper
      .findAll('button')
      .find((button) => button.text().startsWith('Accept all'))

    await acceptAllButton?.trigger('click')
    await acceptAllButton?.trigger('click')
    await flushPromises()

    expect(batchUpdateSuggestionsMock).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Suggestion has been modified. Please refresh.')
  })
})
