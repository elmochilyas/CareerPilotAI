import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick, ref } from 'vue'

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

  it('can resume processing for a document', async () => {
    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()
    state.resumeProcessing(42)
    expect(state.activeDocumentId.value).toBe(42)
    expect(state.stage.value).toBe('processing')
    expect(state.reviewReadonly.value).toBe(false)
    expect(mockRefetch).toHaveBeenCalled()
  })

  it('restores the processing stage from params', async () => {
    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()
    state.restoreFromParams({ documentId: 42, stage: 'processing' })
    expect(state.activeDocumentId.value).toBe(42)
    expect(state.stage.value).toBe('processing')
    expect(state.reviewReadonly.value).toBe(false)
  })

  it('proceeds from review to the import stage when starting an import', async () => {
    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()
    state.resumeReview(42)
    expect(state.stage.value).toBe('review')

    state.startImport()

    expect(state.stage.value).toBe('import')
    expect(mockRefetch).toHaveBeenCalled()
  })

  it('sets the profile concurrency token from the preview and clears it to null', async () => {
    mockIsPending.value = false

    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()

    mockQueryResult.value = { profile_updated_at: '2026-08-05T10:00:00.000000Z' }
    await nextTick()

    expect(state.profileUpdatedAt.value).toBe('2026-08-05T10:00:00.000000Z')

    mockQueryResult.value = undefined
    await nextTick()

    expect(state.profileUpdatedAt.value).toBeNull()
  })

  it('never leaves a stale token behind after a fresh preview', async () => {
    mockIsPending.value = false

    const { useCvIngestion } = await import('@/features/cv-ingestion/composables/useCvIngestion')
    const state = useCvIngestion()

    mockQueryResult.value = { profile_updated_at: 'stale-token' }
    await nextTick()
    expect(state.profileUpdatedAt.value).toBe('stale-token')

    mockQueryResult.value = { profile_updated_at: 'fresh-token' }
    await nextTick()
    expect(state.profileUpdatedAt.value).toBe('fresh-token')
  })
})
