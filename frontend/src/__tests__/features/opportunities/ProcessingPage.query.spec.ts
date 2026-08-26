import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import ProcessingPage from '@/features/opportunities/pages/ProcessingPage.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import type { JobIngestion } from '@/features/opportunities/types'

const mocks = vi.hoisted(() => ({
  routeId: '42',
  fetchIngestion: vi.fn<(id: number) => Promise<JobIngestion>>(),
  retryIngestion: vi.fn<(id: number) => Promise<JobIngestion>>(),
  reanalyzeIngestion: vi.fn<(id: number) => Promise<JobIngestion>>(),
  deleteIngestion: vi.fn<(id: number) => Promise<JobIngestion>>(),
  push: vi.fn<(to: string | Record<string, unknown>) => void>(),
  replace: vi.fn<(to: string | Record<string, unknown>) => void>(),
}))

vi.mock('@/features/opportunities/api', () => ({
  opportunityKeys: {
    ingestions: () => ['opportunities', 'ingestions'],
    ingestion: (id: number) => ['opportunities', 'ingestions', id],
  },
  fetchIngestion: mocks.fetchIngestion,
  retryIngestion: mocks.retryIngestion,
  reanalyzeIngestion: mocks.reanalyzeIngestion,
  deleteIngestion: mocks.deleteIngestion,
}))

vi.mock('vue-router', async () => {
  const { defineComponent, h } = await import('vue')
  return {
    RouterLink: defineComponent({
      name: 'RouterLink',
      props: ['to'],
      setup(_, { slots }) {
        return () => h('a', slots.default?.())
      },
    }),
    useRouter: () => ({ push: mocks.push, replace: mocks.replace }),
    useRoute: () => ({
      params: {
        get id() {
          return mocks.routeId
        },
      },
    }),
  }
})

vi.mock('@/api/client', () => ({
  extractProblemDetail: vi.fn<() => null>(() => null),
}))

function makeIngestion(overrides: Partial<JobIngestion> = {}): JobIngestion {
  return {
    id: 42,
    status: 'failed',
    source_url: null,
    personal_label: null,
    failure_reason: 'The analysis could not be completed.',
    failure_code: 'permanent_failure',
    retry_count: 0,
    last_retry_at: null,
    confirmed_at: null,
    version: 1,
    confirmed_opportunity_id: null,
    created_at: '2026-07-27T10:00:00.000Z',
    updated_at: '2026-07-27T10:00:00.000Z',
    ...overrides,
  }
}

let wrapper: VueWrapper | undefined
let queryClient: QueryClient | undefined

async function mountPage(): Promise<VueWrapper> {
  queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        gcTime: 0,
      },
    },
  })

  wrapper = mount(ProcessingPage, {
    global: {
      plugins: [[VueQueryPlugin, { queryClient }]],
      stubs: {
        Teleport: true,
      },
    },
  })

  await flushPromises()

  return wrapper
}

describe('ProcessingPage with Vue Query', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocks.routeId = '42'
    mocks.fetchIngestion.mockResolvedValue(makeIngestion())
    mocks.retryIngestion.mockResolvedValue(makeIngestion())
    mocks.reanalyzeIngestion.mockResolvedValue(
      makeIngestion({ status: 'queued', version: 2, retry_count: 1 }),
    )
    mocks.deleteIngestion.mockResolvedValue(makeIngestion({ status: 'cancelled' }))
  })

  afterEach(() => {
    wrapper?.unmount()
    queryClient?.clear()
    wrapper = undefined
    queryClient = undefined
  })

  it('initializes the real query without a temporal dead zone error', async () => {
    const page = await mountPage()

    expect(mocks.fetchIngestion).toHaveBeenCalledOnce()
    expect(page.text()).toContain("We couldn't complete the analysis")
  })

  it('does not query an invalid ingestion route id', async () => {
    mocks.routeId = 'not-an-id'

    const page = await mountPage()

    expect(mocks.fetchIngestion).not.toHaveBeenCalled()
    expect(page.text()).toContain('Invalid ingestion identifier')
  })

  it('navigates to a confirmed opportunity by named route', async () => {
    mocks.fetchIngestion.mockResolvedValue(
      makeIngestion({
        status: 'confirmed',
        confirmed_opportunity_id: 73,
      }),
    )
    const page = await mountPage()
    const viewButton = page.findAll('button').find((button) => button.text() === 'View opportunity')

    expect(viewButton).toBeDefined()
    await viewButton!.trigger('click')

    expect(mocks.push).toHaveBeenCalledWith({
      name: 'opportunities-detail',
      params: { id: 73 },
    })
  })

  it('automatically navigates to review when processing completes', async () => {
    mocks.fetchIngestion.mockResolvedValue(
      makeIngestion({
        status: 'review_ready',
      }),
    )

    await mountPage()

    expect(mocks.replace).toHaveBeenCalledWith({
      name: 'opportunities-review',
      params: { id: 42 },
    })
  })

  it('shows a recovery action instead of navigating to an undefined opportunity', async () => {
    mocks.fetchIngestion.mockResolvedValue(
      makeIngestion({
        status: 'confirmed',
        confirmed_opportunity_id: null,
      }),
    )

    const page = await mountPage()

    expect(page.text()).toContain('The confirmed opportunity link is temporarily unavailable.')
    expect(page.text()).not.toContain('View opportunity')
  })

  it('dismisses cancellation without calling the API', async () => {
    const page = await mountPage()
    const requestButton = page
      .findAll('button')
      .find((button) => button.text() === 'Cancel ingestion')

    await requestButton!.trigger('click')
    expect(page.text()).toContain('Cancel this ingestion?')

    const dialog = page.getComponent(ConfirmDialog)
    const dismissButton = dialog.findAll('button').find((button) => button.text() === 'Cancel')
    await dismissButton!.trigger('click')

    expect(mocks.deleteIngestion).not.toHaveBeenCalled()
    expect(page.text()).not.toContain('Cancel this ingestion?')
  })

  it('cancels once, refreshes the list, and replaces the route', async () => {
    const page = await mountPage()
    const invalidateQueries = vi.spyOn(queryClient!, 'invalidateQueries')
    const requestButton = page
      .findAll('button')
      .find((button) => button.text() === 'Cancel ingestion')

    await requestButton!.trigger('click')
    const dialog = page.getComponent(ConfirmDialog)
    const confirmButton = dialog
      .findAll('button')
      .find((button) => button.text() === 'Cancel ingestion')
    await confirmButton!.trigger('click')
    await flushPromises()

    expect(mocks.deleteIngestion).toHaveBeenCalledOnce()
    expect(mocks.deleteIngestion).toHaveBeenCalledWith(42)
    expect(invalidateQueries).toHaveBeenCalledWith({
      queryKey: ['opportunities', 'ingestions'],
    })
    expect(mocks.replace).toHaveBeenCalledWith({ name: 'opportunities' })
  })

  it('disables cancellation while the request is pending', async () => {
    let resolveCancellation: ((value: JobIngestion) => void) | undefined
    mocks.deleteIngestion.mockReturnValue(
      new Promise((resolve) => {
        resolveCancellation = resolve
      }),
    )
    const page = await mountPage()
    const requestButton = page
      .findAll('button')
      .find((button) => button.text() === 'Cancel ingestion')

    await requestButton!.trigger('click')
    const dialog = page.getComponent(ConfirmDialog)
    const confirmButton = dialog
      .findAll('button')
      .find((button) => button.text() === 'Cancel ingestion')
    await confirmButton!.trigger('click')
    await flushPromises()

    const busyButton = dialog.findAll('button').find((button) => button.text() === 'Cancelling...')
    expect(busyButton?.attributes('disabled')).toBeDefined()
    expect(mocks.deleteIngestion).toHaveBeenCalledOnce()

    resolveCancellation?.(makeIngestion({ status: 'cancelled' }))
    await flushPromises()
  })

  it('shows an actionable error and stays on the page when cancellation fails', async () => {
    mocks.deleteIngestion.mockRejectedValue(new Error('Network unavailable'))
    const page = await mountPage()
    const requestButton = page
      .findAll('button')
      .find((button) => button.text() === 'Cancel ingestion')

    await requestButton!.trigger('click')
    const dialog = page.getComponent(ConfirmDialog)
    const confirmButton = dialog
      .findAll('button')
      .find((button) => button.text() === 'Cancel ingestion')
    await confirmButton!.trigger('click')
    await flushPromises()

    expect(page.text()).toContain('Cancellation failed. Please try again')
    expect(mocks.replace).not.toHaveBeenCalled()
    expect(page.find('[role="alert"]').exists()).toBe(true)
  })

  it('confirms reanalysis and resumes processing on the same ingestion', async () => {
    mocks.fetchIngestion.mockResolvedValue(makeIngestion({ status: 'cancelled' }))
    const page = await mountPage()
    const invalidateQueries = vi.spyOn(queryClient!, 'invalidateQueries')
    const requestButton = page.findAll('button').find((button) => button.text() === 'Reanalyze job')

    await requestButton!.trigger('click')
    expect(page.text()).toContain('Reanalyze this job?')

    const dialog = page
      .findAllComponents(ConfirmDialog)
      .find((component) => component.props('title') === 'Reanalyze this job?')
    const confirmButton = dialog!
      .findAll('button')
      .find((button) => button.text() === 'Reanalyze job')
    await confirmButton!.trigger('click')
    await flushPromises()

    expect(mocks.reanalyzeIngestion).toHaveBeenCalledOnce()
    expect(mocks.reanalyzeIngestion).toHaveBeenCalledWith(42)
    expect(invalidateQueries).toHaveBeenCalledWith({
      queryKey: ['opportunities', 'ingestions'],
      exact: true,
    })
    expect(page.text()).toContain('Validating')
    expect(page.text()).not.toContain('Processing cancelled')
    expect(mocks.push).not.toHaveBeenCalled()
    expect(mocks.replace).not.toHaveBeenCalled()
  })

  it('shows a safe inline recovery message when reanalysis fails', async () => {
    mocks.fetchIngestion.mockResolvedValue(makeIngestion({ status: 'cancelled' }))
    mocks.reanalyzeIngestion.mockRejectedValue(
      new Error('SQLSTATE[23000]: Integrity constraint violation'),
    )
    const page = await mountPage()
    const requestButton = page.findAll('button').find((button) => button.text() === 'Reanalyze job')

    await requestButton!.trigger('click')
    const dialog = page
      .findAllComponents(ConfirmDialog)
      .find((component) => component.props('title') === 'Reanalyze this job?')
    const confirmButton = dialog!
      .findAll('button')
      .find((button) => button.text() === 'Reanalyze job')
    await confirmButton!.trigger('click')
    await flushPromises()

    expect(page.text()).toContain("We couldn't restart the analysis")
    expect(page.text()).not.toContain('SQLSTATE')
    expect(page.find('[role="alert"]').exists()).toBe(true)
  })
})
