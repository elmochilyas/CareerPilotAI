import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref, nextTick } from 'vue'
import CvDocumentList from '@/features/cv-ingestion/components/CvDocumentList.vue'
import type { CvDocument, CvDocumentStatus } from '@/features/cv-ingestion/types'

const mockQueryResult = ref<CvDocument[]>([])
const mockIsLoading = ref(true)
const mockIsError = ref(false)
const mockRefetch = vi.fn<() => void>()
const mockInvalidate = vi.fn<(...args: unknown[]) => unknown>()
const mockMutate = vi.fn<(...args: unknown[]) => unknown>()

vi.mock('@tanstack/vue-query', () => ({
  useQuery: vi.fn<(...args: unknown[]) => unknown>(() => ({
    data: mockQueryResult,
    isLoading: mockIsLoading,
    isError: mockIsError,
    refetch: mockRefetch,
  })),
  useMutation: vi.fn<(...args: unknown[]) => unknown>(() => ({
    mutate: mockMutate,
    isPending: ref(false),
    data: ref(undefined),
  })),
  useQueryClient: vi.fn<(...args: unknown[]) => unknown>(() => ({
    invalidateQueries: mockInvalidate,
  })),
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
  fetchCvDocuments: vi.fn<(...args: unknown[]) => unknown>(),
  deleteCvDocument: vi.fn<(...args: unknown[]) => unknown>(),
}))

function makeDocument(overrides: Partial<CvDocument> = {}): CvDocument {
  return {
    id: 1,
    original_name: 'cv.pdf',
    mime_type: 'application/pdf',
    size: 2048,
    status: 'pending',
    failure_reason: null,
    failure_code: null,
    metadata: null,
    latest_run: null,
    created_at: '2026-07-01T10:00:00Z',
    updated_at: '2026-07-01T10:00:00Z',
    ...overrides,
  }
}

function findButtonByText(wrapper: ReturnType<typeof mount>, text: string) {
  return wrapper.findAll('button').find((b) => b.text().includes(text))
}

describe('CvDocumentList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsLoading.value = false
    mockIsError.value = false
    mockQueryResult.value = [makeDocument()]
  })

  it('shows loading skeleton while pending', () => {
    mockIsLoading.value = true
    mockQueryResult.value = []
    const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
    expect(wrapper.findComponent({ name: 'Skeleton' }).exists()).toBe(true)
  })

  it('renders the document name and status', () => {
    mockQueryResult.value = [makeDocument({ status: 'queued' })]
    const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
    expect(wrapper.text()).toContain('cv.pdf')
    expect(wrapper.text()).toContain('Queued')
  })

  it.each<CvDocumentStatus>(['queued', 'validating', 'extracting', 'analyzing'])(
    'shows a Track action for the %s status',
    (status) => {
      mockQueryResult.value = [makeDocument({ id: 5, status })]
      const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
      expect(wrapper.text()).toContain('Track')
    },
  )

  it('shows a Retry action for failed documents', () => {
    mockQueryResult.value = [makeDocument({ status: 'failed' })]
    const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
    expect(wrapper.text()).toContain('Retry')
  })

  it('does not show Track or Retry for ready_for_review documents', () => {
    mockQueryResult.value = [makeDocument({ status: 'ready_for_review' })]
    const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
    expect(wrapper.text()).not.toContain('Track')
    expect(wrapper.text()).not.toContain('Retry')
  })

  it('emits track with the document id', async () => {
    mockQueryResult.value = [makeDocument({ id: 7, status: 'analyzing' })]
    const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
    const trackBtn = findButtonByText(wrapper, 'Track')
    await trackBtn!.trigger('click')
    expect(wrapper.emitted('track')).toEqual([[7]])
  })

  it('emits retry with the document id', async () => {
    mockQueryResult.value = [makeDocument({ id: 7, status: 'failed' })]
    const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
    const retryBtn = findButtonByText(wrapper, 'Retry')
    await retryBtn!.trigger('click')
    expect(wrapper.emitted('retry')).toEqual([[7]])
  })

  it('emits open with the document when the row is clicked', async () => {
    const doc = makeDocument({ id: 7, status: 'ready_for_review' })
    mockQueryResult.value = [doc]
    const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
    const rowBtn = wrapper
      .findAll('button')
      .find((b) => b.attributes('aria-label') === 'Open cv.pdf')
    await rowBtn!.trigger('click')
    expect(wrapper.emitted('open')).toEqual([[doc]])
  })

  it('emits review when the Review action is clicked', async () => {
    mockQueryResult.value = [makeDocument({ id: 7, status: 'ready_for_review' })]
    const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
    const reviewBtn = findButtonByText(wrapper, 'Review')
    await reviewBtn!.trigger('click')
    expect(wrapper.emitted('review')).toEqual([[7]])
  })

  it('emits deleted after a confirmed delete', async () => {
    mockQueryResult.value = [makeDocument({ id: 7, status: 'failed' })]
    const wrapper = mount(CvDocumentList, { props: { highlightId: null } })
    const deleteBtn = wrapper
      .findAll('button')
      .find((b) => b.attributes('aria-label') === 'Delete cv.pdf')
    await deleteBtn!.trigger('click')
    await nextTick()
    const confirmBtn = [...document.body.querySelectorAll('button')].find((b) =>
      b.textContent?.includes('Delete'),
    )
    confirmBtn!.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(mockMutate).toHaveBeenCalledWith(7)
  })
})
