import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import ReviewPage from '@/features/opportunities/pages/ReviewPage.vue'

vi.mock('@tanstack/vue-query', () => ({
  useQuery: vi.fn<(...args: unknown[]) => unknown>(() => ({
    data: ref(undefined),
    isPending: ref(false),
    isError: ref(false),
    refetch: vi.fn<() => void>(),
  })),
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
  updateSuggestion: vi.fn<() => Promise<void>>(() => Promise.resolve()),
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

describe('ReviewPage', () => {
  it('renders title', () => {
    const wrapper = mount(ReviewPage)
    expect(wrapper.text()).toContain('Review job information')
  })

  it('shows step counter', () => {
    const wrapper = mount(ReviewPage)
    expect(wrapper.text()).toContain('Step 1 of')
  })

  it('shows only final step when no suggestions', () => {
    const wrapper = mount(ReviewPage)
    expect(wrapper.text()).toContain('Step 1 of 1')
  })

  it('shows Final review step tab', () => {
    const wrapper = mount(ReviewPage)
    expect(wrapper.text()).toContain('Final review')
  })

  it('shows empty final review section', () => {
    const wrapper = mount(ReviewPage)
    expect(wrapper.text()).toContain('Decisions completed')
  })

  it('has Generate preview button on final step', () => {
    const wrapper = mount(ReviewPage)
    expect(wrapper.text()).toContain('Generate preview')
  })
})
