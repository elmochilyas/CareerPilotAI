import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import type { CandidateProfile } from '@/features/profile/types'

const mockQueryResult = ref<CandidateProfile | undefined>(undefined)
const mockIsPending = ref(true)
const mockIsError = ref(false)
const mockRefetch = vi.fn<() => void>()

vi.mock('@tanstack/vue-query', () => ({
  useQuery: vi.fn<(...args: unknown[]) => unknown>(() => ({
    data: mockQueryResult,
    isPending: mockIsPending,
    isError: mockIsError,
    refetch: mockRefetch,
  })),
  useMutation: vi.fn<(...args: unknown[]) => unknown>(() => ({
    mutate: vi.fn<(...args: unknown[]) => unknown>(),
    isPending: ref(false),
  })),
  useQueryClient: vi.fn<(...args: unknown[]) => unknown>(() => ({
    invalidateQueries: vi.fn<(...args: unknown[]) => unknown>(),
  })),
}))

vi.mock('@/api/client', () => ({
  extractProblemDetail: vi.fn<(...args: unknown[]) => unknown>(() => null),
}))

vi.mock('@/features/profile/api', () => ({
  fetchProfile: vi.fn<(...args: unknown[]) => unknown>(),
  updateProfile: vi.fn<(...args: unknown[]) => unknown>(),
  createProfileItem: vi.fn<(...args: unknown[]) => unknown>(),
  updateProfileItem: vi.fn<(...args: unknown[]) => unknown>(),
  deleteProfileItem: vi.fn<(...args: unknown[]) => unknown>(),
  reorderProfileItems: vi.fn<(...args: unknown[]) => unknown>(),
  profileKeys: { all: ['profile'], detail: () => ['profile', 'detail'] },
}))

describe('useProfile', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsPending.value = true
    mockIsError.value = false
    mockQueryResult.value = undefined
  })

  it('returns loading state initially', async () => {
    const { useProfile } = await import('@/features/profile/composables/useProfile')
    const state = useProfile()
    expect(state.isPending.value).toBe(true)
    expect(state.profile.value).toBeUndefined()
  })

  it('returns profile when data is loaded', async () => {
    mockIsPending.value = false
    mockQueryResult.value = { full_name: 'Jane' } as CandidateProfile
    const { useProfile } = await import('@/features/profile/composables/useProfile')
    const state = useProfile()
    expect(state.isPending.value).toBe(false)
    expect(state.profile.value?.full_name).toBe('Jane')
  })

  it('returns error state when query fails', async () => {
    mockIsPending.value = false
    mockIsError.value = true
    const { useProfile } = await import('@/features/profile/composables/useProfile')
    const state = useProfile()
    expect(state.isError.value).toBe(true)
  })

  it('tracks dirty sections', async () => {
    const { useProfile } = await import('@/features/profile/composables/useProfile')
    const state = useProfile()
    expect(state.hasUnsavedChanges.value).toBe(false)
    state.markDirty('header', true)
    expect(state.hasUnsavedChanges.value).toBe(true)
    state.markDirty('header', false)
    expect(state.hasUnsavedChanges.value).toBe(false)
  })
})
