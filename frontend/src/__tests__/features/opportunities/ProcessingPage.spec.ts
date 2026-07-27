import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ProcessingPage from '@/features/opportunities/pages/ProcessingPage.vue'

const mockIngestion = ref<Record<string, unknown> | undefined>(undefined)
const mockIsPending = ref(true)
const mockIsError = ref(false)
const mockRefetch = vi.fn<() => void>()
const mockPush = vi.fn<(to: string | Record<string, unknown>) => void>()
const mockReplace = vi.fn<(to: string | Record<string, unknown>) => void>()
const mockCancelMutate = vi.fn<() => void>()
const mockCancelPending = ref(false)

vi.mock('@tanstack/vue-query', () => ({
  useQuery: vi.fn<(...args: unknown[]) => unknown>(() => ({
    data: mockIngestion,
    isPending: mockIsPending,
    isError: mockIsError,
    refetch: mockRefetch,
  })),
  useMutation: vi.fn<(...args: unknown[]) => unknown>(() => ({
    mutate: mockCancelMutate,
    isPending: mockCancelPending,
  })),
  useQueryClient: vi.fn<() => unknown>(() => ({
    setQueryData: vi.fn<() => void>(),
    invalidateQueries: vi.fn<() => Promise<void>>(() => Promise.resolve()),
  })),
}))

vi.mock('@/features/opportunities/api', () => ({
  opportunityKeys: {
    all: ['opportunities'],
    ingestions: () => ['opportunities', 'ingestions'],
    ingestion: (id: number) => ['opportunities', 'ingestions', id],
    suggestions: (id: number) => ['opportunities', 'ingestions', id, 'suggestions'],
    list: () => ['opportunities', 'list'],
    detail: (id: number) => ['opportunities', 'detail', id],
  },
  fetchIngestion: vi.fn<() => void>(),
  retryIngestion: vi.fn<() => Promise<void>>(() => Promise.resolve()),
  reanalyzeIngestion: vi.fn<() => Promise<void>>(() => Promise.resolve()),
  deleteIngestion: vi.fn<() => Promise<void>>(() => Promise.resolve()),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mockPush, replace: mockReplace }),
  useRoute: () => ({ params: { id: '42' } }),
}))

describe('ProcessingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsPending.value = true
    mockIsError.value = false
    mockCancelPending.value = false
    mockIngestion.value = undefined
  })

  it('shows loading state when pending', () => {
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Loading…')
  })

  it('shows error state when fetch fails', () => {
    mockIsPending.value = false
    mockIsError.value = true
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Failed to load ingestion')
  })

  it('shows error state when ingestion is null', () => {
    mockIsPending.value = false
    mockIngestion.value = null as unknown as undefined
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Failed to load ingestion')
  })

  it('renders title', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'queued' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Processing job description')
  })

  it('shows first stage active for draft status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'draft' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Description received')
  })

  it('shows validating stage for queued status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'queued' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Validating')
  })

  it('shows analyzing stage for processing status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'processing' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Analyzing job information')
  })

  it('shows review button for review_ready status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'review_ready' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Review extracted information')
  })

  it('shows confirmed message for confirmed status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'confirmed', confirmed_opportunity_id: 5 }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Job opportunity confirmed!')
  })

  it('shows cancelled message for cancelled status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'cancelled' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Processing cancelled')
  })

  it('shows back button for cancelled status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'cancelled' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Back to opportunities')
  })

  it('shows reanalysis action for cancelled status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'cancelled' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Reanalyze job')
  })

  it('shows failure message for failed status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'failed', failure_reason: 'Something went wrong' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('We couldn’t complete the analysis')
    expect(wrapper.text()).toContain('Something went wrong')
  })

  it('keeps the failure code in expandable support details', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'failed', failure_code: 'permanent_failure' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Support details')
    expect(wrapper.text()).toContain('Error code: permanent_failure')
    expect(wrapper.find('details').exists()).toBe(true)
  })

  it('shows retry button for retryable failure', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'failed', failure_code: 'ai_provider_timeout' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Retry')
  })

  it('maps internal failure codes to candidate-friendly guidance', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'failed', failure_code: 'invalid_ai_output' }
    const wrapper = mount(ProcessingPage)

    expect(wrapper.text()).toContain('We could not reliably read this job description')
    expect(wrapper.text()).not.toContain('SQLSTATE')
  })

  it('shows cancel and import buttons for failed status', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'failed', failure_code: 'permanent_failure' }
    const wrapper = mount(ProcessingPage)
    expect(wrapper.text()).toContain('Cancel')
    expect(wrapper.text()).toContain('Import a different description')
  })

  it('hides retry button for permanent failure', () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'failed', failure_code: 'permanent_failure' }
    const wrapper = mount(ProcessingPage)
    const buttonText = wrapper.text()
    expect(buttonText).not.toContain('Retry')
  })

  it('handles back navigation from a cancelled ingestion', async () => {
    mockIsPending.value = false
    mockIngestion.value = { status: 'cancelled' }
    const wrapper = mount(ProcessingPage)
    const backButton = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Back to opportunities')
    await backButton!.trigger('click')
    expect(mockPush).toHaveBeenCalledWith({ name: 'opportunities' })
  })
})
