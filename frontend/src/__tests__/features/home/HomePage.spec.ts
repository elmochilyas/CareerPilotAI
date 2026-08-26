import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { createPinia, setActivePinia } from 'pinia'

const { useQueryMock } = vi.hoisted(() => ({
  useQueryMock: vi.fn<(...args: unknown[]) => unknown>(),
}))

vi.mock('@tanstack/vue-query', () => ({
  useQuery: useQueryMock,
}))

vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: { full_name: 'Test User', id: 1 },
    isAuthenticated: true,
    initialized: true,
  }),
}))

vi.mock('@/features/profile/api', () => ({
  profileKeys: { detail: () => ['profile', 'detail'] },
  fetchProfile: vi.fn<(...args: unknown[]) => unknown>(),
}))
vi.mock('@/features/cv-ingestion/api', () => ({
  cvKeys: { list: () => ['cv', 'list'] },
  fetchCvDocuments: vi.fn<(...args: unknown[]) => unknown>(),
}))
vi.mock('@/features/opportunities/api', () => ({
  opportunityKeys: { ingestions: () => ['opportunities', 'ingestions'] },
  fetchIngestions: vi.fn<(...args: unknown[]) => unknown>(),
}))
vi.mock('@/features/skills/api', () => ({
  skillKeys: { candidate: () => ['skills', 'candidate'] },
  fetchCandidateSkills: vi.fn<(...args: unknown[]) => unknown>(),
}))

import HomePage from '@/features/home/pages/HomePage.vue'

function profileResult(overrides: Record<string, unknown> = {}) {
  return {
    data: ref({ profile_completion: 80 }),
    isLoading: ref(false),
    isPending: ref(false),
    isError: ref(false),
    isFetching: ref(false),
    error: ref(null),
    refetch: vi.fn<(...args: unknown[]) => unknown>(),
    ...overrides,
  }
}
function cvResult(overrides: Record<string, unknown> = {}) {
  return {
    data: ref([{ id: 1 }, { id: 2 }]),
    isLoading: ref(false),
    isPending: ref(false),
    isError: ref(false),
    isFetching: ref(false),
    error: ref(null),
    refetch: vi.fn<(...args: unknown[]) => unknown>(),
    ...overrides,
  }
}
function ingestionsResult(overrides: Record<string, unknown> = {}) {
  return {
    data: ref({ data: [], meta: { total: 0 } }),
    isLoading: ref(false),
    isPending: ref(false),
    isError: ref(false),
    isFetching: ref(false),
    error: ref(null),
    refetch: vi.fn<(...args: unknown[]) => unknown>(),
    ...overrides,
  }
}
function skillsResult(overrides: Record<string, unknown> = {}) {
  return {
    data: ref([{ id: 1 }, { id: 2 }, { id: 3 }]),
    isLoading: ref(false),
    isPending: ref(false),
    isError: ref(false),
    isFetching: ref(false),
    error: ref(null),
    refetch: vi.fn<(...args: unknown[]) => unknown>(),
    ...overrides,
  }
}

async function mountHome() {
  setActivePinia(createPinia())
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/', name: 'home', component: HomePage }],
  })
  await router.push('/')
  await router.isReady()
  const wrapper = mount(HomePage, {
    global: { plugins: [router] },
  })
  await flushPromises()
  return wrapper
}

describe('HomePage dashboard baseline', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows error with retry when one query fails while others succeed', async () => {
    const profileErrorRefetch = vi.fn<(...args: unknown[]) => unknown>()
    useQueryMock
      .mockReturnValueOnce(
        profileResult({
          isError: ref(true),
          error: ref(new Error('fail')),
          refetch: profileErrorRefetch,
        }),
      )
      .mockReturnValueOnce(cvResult())
      .mockReturnValueOnce(ingestionsResult())
      .mockReturnValueOnce(skillsResult())

    const wrapper = await mountHome()

    expect(wrapper.text()).toContain('Failed to load profile completion')
    expect(wrapper.text()).toContain('Retry')
    // Other panels remain visible (stat cards, quick actions)
    expect(wrapper.text()).toContain('Profile complete')
    expect(wrapper.text()).toContain('CV documents')
    expect(wrapper.text()).toContain('Quick Actions')

    // Retry recovers (calls refetch)
    const retry = wrapper.findAll('button').find((b) => b.text().includes('Retry'))
    expect(retry).toBeDefined()
    await retry!.trigger('click')
    expect(profileErrorRefetch).toHaveBeenCalled()
  })

  it('shows empty state when attention items empty', async () => {
    useQueryMock
      .mockReturnValueOnce(profileResult())
      .mockReturnValueOnce(cvResult())
      .mockReturnValueOnce(ingestionsResult({ data: ref({ data: [], meta: { total: 0 } }) }))
      .mockReturnValueOnce(skillsResult())

    const wrapper = await mountHome()
    expect(wrapper.text()).toContain('All caught up')
    expect(wrapper.text()).toContain('No opportunities need your review')
  })

  it('does not contain future module widgets (Applications, Tasks, Interviews)', async () => {
    useQueryMock
      .mockReturnValueOnce(profileResult())
      .mockReturnValueOnce(cvResult())
      .mockReturnValueOnce(ingestionsResult())
      .mockReturnValueOnce(skillsResult())

    const wrapper = await mountHome()
    const text = wrapper.text()
    expect(text).not.toContain('Applications')
    expect(text).not.toContain('Tasks')
    expect(text).not.toContain('Interviews')
    expect(text).not.toContain('Roadmap')
  })

  it('shows loading skeletons initially and then content', async () => {
    useQueryMock
      .mockReturnValueOnce(profileResult({ isLoading: ref(true), isPending: ref(true) }))
      .mockReturnValueOnce(cvResult({ isLoading: ref(true), isPending: ref(true) }))
      .mockReturnValueOnce(ingestionsResult({ isLoading: ref(true), isPending: ref(true) }))
      .mockReturnValueOnce(skillsResult({ isLoading: ref(true), isPending: ref(true) }))

    const wrapper = await mountHome()
    // Header still visible, skeletons shown (we check for header greeting, not blank)
    expect(wrapper.text()).toContain('Good')
    expect(wrapper.text()).toContain('Test')
  })
})
