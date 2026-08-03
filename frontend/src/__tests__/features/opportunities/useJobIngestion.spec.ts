import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'

const mockQueryResult = ref<unknown>(undefined)
const mockIsPending = ref(true)
const mockIsError = ref(false)
const mockRefetch = vi.fn<() => void>()
const mockMutate = vi.fn<(...args: unknown[]) => unknown>()
const mockMutateAsync = vi.fn<(...args: unknown[]) => unknown>()

vi.mock('@tanstack/vue-query', () => ({
  useQuery: vi.fn<(...args: unknown[]) => unknown>(() => ({
    data: mockQueryResult,
    isPending: mockIsPending,
    isError: mockIsError,
    refetch: mockRefetch,
  })),
  useMutation: vi.fn<(...args: unknown[]) => unknown>(() => ({
    mutate: mockMutate,
    mutateAsync: mockMutateAsync,
    isPending: ref(false),
    data: ref(undefined),
  })),
  useQueryClient: vi.fn<(...args: unknown[]) => unknown>(() => ({
    invalidateQueries: vi.fn<(...args: unknown[]) => unknown>(),
  })),
}))

vi.mock('@/api/client', () => ({
  extractProblemDetail: vi.fn<(...args: unknown[]) => unknown>(() => null),
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
  fetchIngestion: vi.fn<(...args: unknown[]) => unknown>(),
  fetchSuggestions: vi.fn<(...args: unknown[]) => unknown>(),
  fetchOpportunities: vi.fn<(...args: unknown[]) => unknown>(),
  createIngestion: vi.fn<(...args: unknown[]) => unknown>(),
  retryIngestion: vi.fn<(...args: unknown[]) => unknown>(),
  updateSuggestion: vi.fn<(...args: unknown[]) => unknown>(),
  batchUpdateSuggestions: vi.fn<(...args: unknown[]) => unknown>(),
  generatePreview: vi.fn<(...args: unknown[]) => unknown>(),
  confirmIngestion: vi.fn<(...args: unknown[]) => unknown>(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn<() => void>(), back: vi.fn<() => void>() }),
  useRoute: () => ({ params: { id: '1' } }),
}))

describe('useJobIngestion', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsPending.value = true
    mockIsError.value = false
    mockQueryResult.value = undefined
  })

  it('starts with no active ingestion', async () => {
    const { useJobIngestion } = await import('@/features/opportunities/composables/useJobIngestion')
    const state = useJobIngestion()
    expect(state.activeIngestionId.value).toBeNull()
  })

  it('starts with empty announcement', async () => {
    const { useJobIngestion } = await import('@/features/opportunities/composables/useJobIngestion')
    const state = useJobIngestion()
    expect(state.announcement.value).toBe('')
  })

  it('can set active ingestion', async () => {
    const { useJobIngestion } = await import('@/features/opportunities/composables/useJobIngestion')
    const state = useJobIngestion()
    state.setActiveIngestion(42)
    expect(state.activeIngestionId.value).toBe(42)
  })

  it('returns null ingestion when no data', async () => {
    mockIsPending.value = false
    const { useJobIngestion } = await import('@/features/opportunities/composables/useJobIngestion')
    const state = useJobIngestion()
    expect(state.ingestion.value).toBeNull()
  })

  it('returns empty suggestions when no data', async () => {
    mockIsPending.value = false
    const { useJobIngestion } = await import('@/features/opportunities/composables/useJobIngestion')
    const state = useJobIngestion()
    expect(state.suggestions.value).toEqual([])
  })

  it('isProcessing is false for confirmed status', async () => {
    mockIsPending.value = false
    mockQueryResult.value = { status: 'confirmed' }
    const { useJobIngestion } = await import('@/features/opportunities/composables/useJobIngestion')
    const state = useJobIngestion()
    state.setActiveIngestion(1)
    expect(state.isProcessing.value).toBe(false)
  })

  it('isProcessing is true for draft status', async () => {
    mockIsPending.value = false
    mockQueryResult.value = { status: 'draft' }
    const { useJobIngestion } = await import('@/features/opportunities/composables/useJobIngestion')
    const state = useJobIngestion()
    state.setActiveIngestion(1)
    expect(state.isProcessing.value).toBe(true)
  })

  it('can reset state', async () => {
    const { useJobIngestion } = await import('@/features/opportunities/composables/useJobIngestion')
    const state = useJobIngestion()
    state.setActiveIngestion(42)
    state.reset()
    expect(state.activeIngestionId.value).toBeNull()
    expect(state.previewData.value).toBeNull()
    expect(state.confirmedOpportunityId.value).toBeNull()
  })

  it('isReviewReady is true for review_ready status', async () => {
    mockIsPending.value = false
    mockQueryResult.value = { status: 'review_ready' }
    const { useJobIngestion } = await import('@/features/opportunities/composables/useJobIngestion')
    const state = useJobIngestion()
    state.setActiveIngestion(1)
    expect(state.isReviewReady.value).toBe(true)
    expect(state.isConfirmed.value).toBe(false)
    expect(state.isFailed.value).toBe(false)
  })
})
