import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'

const mockQueryResult = ref<unknown>(undefined)
const mockIsPending = ref(true)
const mockIsError = ref(false)
const mockRefetch = vi.fn<() => void>()
const mockMutate = vi.fn<(...args: unknown[]) => unknown>()

vi.mock('@tanstack/vue-query', () => ({
  useQuery: vi.fn<(...args: unknown[]) => unknown>(() => ({
    data: mockQueryResult,
    isPending: mockIsPending,
    isError: mockIsError,
    refetch: mockRefetch,
  })),
  useMutation: vi.fn<(...args: unknown[]) => unknown>(() => ({
    mutate: mockMutate,
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

vi.mock('@/features/cv-ingestion/api', () => ({
  cvKeys: {
    all: ['cv'],
    list: () => ['cv', 'list'],
    detail: (id: number) => ['cv', 'detail', id],
    suggestions: (id: number) => ['cv', id, 'suggestions'],
    importPreview: (id: number) => ['cv', id, 'import', 'preview'],
    importResult: (docId: number, batchId: number) => ['cv', docId, 'import', batchId],
  },
  fetchCvDocument: vi.fn<(...args: unknown[]) => unknown>(),
  fetchSuggestions: vi.fn<(...args: unknown[]) => unknown>(),
  fetchImportPreview: vi.fn<(...args: unknown[]) => unknown>(),
  uploadCvDocument: vi.fn<(...args: unknown[]) => unknown>(),
  applyImport: vi.fn<(...args: unknown[]) => unknown>(),
  updateSuggestion: vi.fn<(...args: unknown[]) => unknown>(),
  batchUpdateSuggestions: vi.fn<(...args: unknown[]) => unknown>(),
  retryCvDocument: vi.fn<(...args: unknown[]) => unknown>(),
  deleteCvDocument: vi.fn<(...args: unknown[]) => unknown>(),
}))

describe('useCvIngestion', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsPending.value = true
    mockIsError.value = false
    mockQueryResult.value = undefined
  })

  it('starts in choose_mode stage', async () => {
    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()
    expect(state.stage.value).toBe('choose_mode')
  })

  it('has announcements ref initially empty', async () => {
    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()
    expect(state.announcement.value).toBe('')
  })

  it('can choose mode', async () => {
    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()
    state.chooseMode('update_existing')
    expect(state.uploadMode.value).toBe('update_existing')
    expect(state.stage.value).toBe('idle')
  })

  it('can reset', async () => {
    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()
    state.chooseMode('create_new')
    state.reset()
    expect(state.stage.value).toBe('choose_mode')
    expect(state.activeDocumentId.value).toBeNull()
  })

  it('completes review progress with no suggestions', async () => {
    mockIsPending.value = false
    mockQueryResult.value = undefined
    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()
    expect(state.reviewProgress.value.reviewed).toBe(0)
    expect(state.reviewProgress.value.total).toBe(0)
    expect(state.reviewProgress.value.pct).toBe(0)
  })
})
