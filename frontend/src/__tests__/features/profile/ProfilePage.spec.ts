import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import { createPinia, setActivePinia } from 'pinia'
import { RouterLinkStub } from '@vue/test-utils'
import ProfilePage from '@/features/profile/pages/ProfilePage.vue'
import type { CandidateProfile } from '@/features/profile/types'

const mockComposable = vi.hoisted(() => ({
  isPending: { value: true },
  isError: { value: false },
  profile: { value: undefined as CandidateProfile | undefined },
  refetch: vi.fn<() => void>(),
  hasUnsavedChanges: { value: false },
  markDirty: vi.fn<(section: string, dirty?: boolean) => void>(),
  announcement: { value: '' },
  conflictCount: { value: 0 },
  profileMutation: { mutate: vi.fn<() => void>(), isPending: { value: false } },
  createItemMutation: { mutate: vi.fn<() => void>(), isPending: { value: false } },
  updateItemMutation: { mutate: vi.fn<() => void>(), isPending: { value: false } },
  deleteItemMutation: { mutate: vi.fn<() => void>(), isPending: { value: false } },
  reorderMutation: { mutate: vi.fn<() => void>(), isPending: { value: false } },
  refresh: vi.fn<() => void>(),
}))

vi.mock('@/features/profile/composables/useProfile', () => ({
  useProfile: () => mockComposable,
}))

function makeProfile(): CandidateProfile {
  return {
    id: 1,
    full_name: 'Jane Doe',
    headline: 'Engineer',
    phone: null,
    city: 'Paris',
    country: 'France',
    linkedin_url: null,
    github_url: null,
    portfolio_url: null,
    availability_status: 'immediately',
    availability_date: null,
    target_roles: ['Engineer'],
    preferred_locations: [],
    work_modes: ['hybrid'],
    contract_types: ['full-time'],
    salary_min: '50000',
    salary_max: '80000',
    salary_currency: null,
    salary_period: null,
    languages: [{ language: 'English', proficiency: 'native' }],
    professional_summary: 'A summary',
    profile_completion: 50,
    completion_details: {
      areas: [
        { key: 'experience', earned: 0, available: 2, complete: false, guidance: 'Add experience' },
      ],
      missing_areas: [{ key: 'experience', guidance: 'Add at least one work experience' }],
    },
    items: {
      education: [],
      experience: [],
      project: [],
      certification: [],
    },
    created_at: null,
    updated_at: '2026-01-01T00:00:00Z',
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: { template: '<div>Home</div>' } },
    { path: '/profile', name: 'profile', component: { template: '<div>Profile</div>' } },
  ],
})

describe('ProfilePage', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    mockComposable.isPending.value = true
    mockComposable.isError.value = false
    mockComposable.profile.value = undefined
  })

  it('shows loading skeleton while profile is loading', () => {
    mockComposable.isPending.value = true
    const wrapper = mount(ProfilePage, {
      global: { plugins: [router, createPinia()], stubs: { RouterLink: RouterLinkStub } },
    })
    expect(wrapper.find('[aria-label="Loading profile"]').exists()).toBe(true)
  })

  it('shows error section when query fails', () => {
    mockComposable.isPending.value = false
    mockComposable.isError.value = true
    const wrapper = mount(ProfilePage, {
      global: { plugins: [router, createPinia()], stubs: { RouterLink: RouterLinkStub } },
    })
    expect(wrapper.text()).toContain("We couldn't load your profile")
    expect(wrapper.find('button').text()).toContain('Retry')
  })

  it('renders profile sections when data is available', () => {
    mockComposable.isPending.value = false
    mockComposable.profile.value = makeProfile()
    const wrapper = mount(ProfilePage, {
      global: { plugins: [router, createPinia()], stubs: { RouterLink: RouterLinkStub } },
    })
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('Profile strength')
  })

  it('renders empty state when profile has no id', () => {
    mockComposable.isPending.value = false
    mockComposable.profile.value = { ...makeProfile(), id: null }
    const wrapper = mount(ProfilePage, {
      global: { plugins: [router, createPinia()], stubs: { RouterLink: RouterLinkStub } },
    })
    expect(wrapper.text()).toContain('Start building your trusted profile.')
  })

  it('triggers retry on error button click', async () => {
    mockComposable.isPending.value = false
    mockComposable.isError.value = true
    const wrapper = mount(ProfilePage, {
      global: { plugins: [router, createPinia()], stubs: { RouterLink: RouterLinkStub } },
    })
    await wrapper.find('button').trigger('click')
    expect(mockComposable.refetch).toHaveBeenCalled()
  })
})
