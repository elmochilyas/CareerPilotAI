import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import OpportunitiesListPage from '@/features/opportunities/pages/OpportunitiesListPage.vue'

const { useQueryMock } = vi.hoisted(() => ({
  useQueryMock: vi.fn<(...args: unknown[]) => unknown>(),
}))

vi.mock('@tanstack/vue-query', () => ({
  useQuery: useQueryMock,
}))

vi.mock('@/features/opportunities/api', () => ({
  opportunityKeys: {
    all: ['opportunities'],
    ingestions: (page?: number) => ['opportunities', 'ingestions', page],
    list: (page?: number) => ['opportunities', 'list', page],
  },
  fetchIngestions: vi.fn<(...args: unknown[]) => unknown>(),
  fetchOpportunities: vi.fn<(...args: unknown[]) => unknown>(),
}))

function queryResult(lastPage: number) {
  return {
    data: ref({
      data: [],
      meta: {
        current_page: 1,
        last_page: lastPage,
        per_page: 15,
        total: 31,
      },
    }),
    isPending: ref(false),
    isError: ref(false),
    refetch: vi.fn<() => void>(),
  }
}

async function mountPage() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/opportunities',
        name: 'opportunities',
        component: OpportunitiesListPage,
      },
      {
        path: '/opportunities/import',
        name: 'opportunities-import',
        component: { template: '<div />' },
      },
    ],
  })
  await router.push('/opportunities')
  await router.isReady()

  return {
    router,
    wrapper: mount(OpportunitiesListPage, {
      global: { plugins: [router] },
    }),
  }
}

describe('OpportunitiesListPage pagination', () => {
  beforeEach(() => {
    useQueryMock.mockReset()
    useQueryMock.mockReturnValueOnce(queryResult(2)).mockReturnValueOnce(queryResult(3))
  })

  it('renders API pagination and navigates without losing the active filter', async () => {
    const { router, wrapper } = await mountPage()
    const paginationContainer = wrapper.find('.flex.items-center.justify-center.gap-4')
    const pagination = wrapper.get('nav[aria-label="Pagination"]')
    const previous = pagination.get('button[aria-label="Previous page"]')
    const next = pagination.get('button[aria-label="Next page"]')

    expect(paginationContainer.text()).toContain('Page 1 of 3')
    expect(previous.attributes('disabled')).toBeDefined()

    await router.replace({ query: { view: 'saved' } })
    await next.trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({
      view: 'saved',
      page: '2',
    })
  })
})
