import { describe, it, expect, vi, beforeEach } from 'vitest'
import { VueQueryPlugin } from '@tanstack/vue-query'
import { mount } from '@vue/test-utils'

vi.mock('@/features/skills/api', () => ({
  fetchCandidateSkills: vi.fn<() => Promise<never[]>>().mockResolvedValue([]),
  createCandidateSkill: vi.fn<() => Promise<never>>(),
  archiveCandidateSkill: vi.fn<() => Promise<never>>(),
  restoreCandidateSkill: vi.fn<() => Promise<never>>(),
  deleteCandidateSkill: vi.fn<() => Promise<void>>(),
  addEvidence: vi.fn<() => Promise<never>>(),
  updateEvidence: vi.fn<() => Promise<never>>(),
  removeEvidence: vi.fn<() => Promise<never>>(),
  skillKeys: {
    all: ['skills'],
    catalog: () => ['skills', 'catalog'],
    catalogSearch: (q: string) => ['skills', 'catalog', q],
    detail: (id: number) => ['skills', 'detail', id],
    candidate: () => ['candidate-skills'],
    candidateDetail: (id: number) => ['candidate-skills', 'detail', id],
  },
}))

import { useSkills } from '@/features/skills/composables/useSkills'

function createWrapper() {
  return mount(
    { template: '<div />', setup: () => useSkills() },
    {
      global: { plugins: [VueQueryPlugin] },
    },
  )
}

describe('useSkills', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns skills array', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(Array.isArray(composable.skills)).toBe(true)
  })

  it('returns isReady computed', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(typeof composable.isReady).toBe('boolean')
  })

  it('returns announcement ref', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(typeof composable.announcement).toBe('string')
  })

  it('returns conflictError ref', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(composable.conflictError).toBeDefined()
  })

  it('returns refreshCandidate function', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(typeof composable.refreshCandidate).toBe('function')
  })

  it('returns handleError function', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(typeof composable.handleError).toBe('function')
  })

  it('returns createMutation with mutate', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(composable.createMutation).toBeDefined()
    expect(typeof composable.createMutation.mutate).toBe('function')
  })

  it('returns archiveMutation with mutate', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(composable.archiveMutation).toBeDefined()
    expect(typeof composable.archiveMutation.mutate).toBe('function')
  })

  it('returns restoreMutation with mutate', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(composable.restoreMutation).toBeDefined()
    expect(typeof composable.restoreMutation.mutate).toBe('function')
  })

  it('returns deleteMutation with mutate', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(composable.deleteMutation).toBeDefined()
    expect(typeof composable.deleteMutation.mutate).toBe('function')
  })

  it('returns addEvidenceMutation with mutate', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(composable.addEvidenceMutation).toBeDefined()
    expect(typeof composable.addEvidenceMutation.mutate).toBe('function')
  })

  it('returns updateEvidenceMutation with mutate', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(composable.updateEvidenceMutation).toBeDefined()
    expect(typeof composable.updateEvidenceMutation.mutate).toBe('function')
  })

  it('returns removeEvidenceMutation with mutate', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect(composable.removeEvidenceMutation).toBeDefined()
    expect(typeof composable.removeEvidenceMutation.mutate).toBe('function')
  })

  it('returns isPending status', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect('isPending' in composable).toBe(true)
  })

  it('returns isError status', () => {
    const wrapper = createWrapper()
    const composable = wrapper.vm
    expect('isError' in composable).toBe(true)
  })
})
