import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { useJobIngestion } from '@/features/opportunities/composables/useJobIngestion'

const mocks = vi.hoisted(() => ({
  fetchIngestion: vi.fn<(id: number) => Promise<unknown>>(),
  fetchOpportunities: vi.fn<() => Promise<unknown>>(),
  push: vi.fn<(to: string | Record<string, unknown>) => void>(),
}))

vi.mock('@/features/opportunities/api', () => ({
  opportunityKeys: {
    all: ['opportunities'],
    ingestions: () => ['opportunities', 'ingestions'],
    ingestion: (id: number) => ['opportunities', 'ingestions', id],
    suggestions: (id: number) => ['opportunities', 'ingestions', id, 'suggestions'],
    list: () => ['opportunities', 'list'],
  },
  batchUpdateSuggestions: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  confirmIngestion: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  createIngestion: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  fetchIngestion: mocks.fetchIngestion,
  fetchOpportunities: mocks.fetchOpportunities,
  fetchSuggestions: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  generatePreview: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  retryIngestion: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
  updateSuggestion: vi.fn<(...args: unknown[]) => Promise<unknown>>(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: mocks.push }),
}))

const TestHost = defineComponent({
  setup() {
    const { activeIngestionId } = useJobIngestion()

    return () => h('div', activeIngestionId.value ?? 'inactive')
  },
})

let wrapper: VueWrapper | undefined
let queryClient: QueryClient | undefined

afterEach(() => {
  wrapper?.unmount()
  queryClient?.clear()
  wrapper = undefined
  queryClient = undefined
  vi.clearAllMocks()
})

describe('useJobIngestion with Vue Query', () => {
  it('initializes with no active ingestion without a temporal dead zone error', async () => {
    mocks.fetchOpportunities.mockResolvedValue({
      data: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
      },
    })
    queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
          gcTime: 0,
        },
      },
    })

    wrapper = mount(TestHost, {
      global: {
        plugins: [[VueQueryPlugin, { queryClient }]],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toBe('inactive')
    expect(mocks.fetchIngestion).not.toHaveBeenCalled()
  })
})
