import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { VueQueryPlugin } from '@tanstack/vue-query'
import SkillsSection from '@/features/skills/components/SkillsSection.vue'
import type { CandidateSkill } from '@/features/skills/types'
import type { Ref } from 'vue'

interface MockRefs {
  isPending: Ref<boolean>
  isError: Ref<boolean>
  announcement: Ref<string>
  skills: Ref<CandidateSkill[]>
  conflictCount: Ref<number>
  refreshCandidate: () => void
}

const mockRefs = vi.hoisted(() => ({}) as MockRefs)

vi.mock('@/features/skills/composables/useSkills', async () => {
  const { ref, computed } = await vi.importActual<typeof import('vue')>('vue')
  const skills = ref<CandidateSkill[]>([])
  const isPending = ref(true)
  const isError = ref(false)
  const announcement = ref('')
  const conflictCount = ref(0)
  const refreshCandidate = vi.fn<() => void>()
  mockRefs.skills = skills
  mockRefs.isPending = isPending
  mockRefs.isError = isError
  mockRefs.announcement = announcement
  mockRefs.conflictCount = conflictCount
  mockRefs.refreshCandidate = refreshCandidate
  return {
    useSkills: () => ({
      skills: computed(() => skills.value),
      isPending,
      isError,
      announcement,
      conflictCount,
      refreshCandidate,
      handleError: vi.fn<(error: unknown, fallbackMessage: string) => boolean>(),
      createMutation: { mutate: vi.fn<() => void>(), isPending: ref(false) },
      archiveMutation: { mutate: vi.fn<() => void>(), isPending: ref(false) },
      restoreMutation: { mutate: vi.fn<() => void>(), isPending: ref(false) },
      deleteMutation: { mutate: vi.fn<() => void>(), isPending: ref(false) },
      addEvidenceMutation: { mutate: vi.fn<() => void>(), isPending: ref(false) },
      updateEvidenceMutation: { mutate: vi.fn<() => void>(), isPending: ref(false) },
      removeEvidenceMutation: { mutate: vi.fn<() => void>(), isPending: ref(false) },
    }),
  }
})

function makeSkill(overrides: Partial<CandidateSkill> = {}): CandidateSkill {
  return {
    id: 1,
    skill: {
      id: 10,
      name: 'TypeScript',
      normalized_name: 'typescript',
      category: 'Language',
      is_active: true,
      aliases: [],
      created_at: '',
      updated_at: '',
    },
    is_custom: false,
    state: 'claimed',
    proficiency_level: 'advanced',
    years_experience: null,
    last_used_at: null,
    evidence: [],
    verification_at_risk: false,
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

const stubs = {
  Teleport: { template: '<div><slot/></div>' },
  Transition: false,
}

describe('SkillsSection', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockRefs.isPending.value = true
    mockRefs.isError.value = false
    mockRefs.announcement.value = ''
    mockRefs.skills.value = []
    mockRefs.conflictCount.value = 0
  })

  it('renders the section heading', () => {
    const wrapper = mount(SkillsSection, {
      global: { plugins: [VueQueryPlugin], stubs },
    })
    expect(wrapper.find('[aria-labelledby="skills-heading"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Skills')
    expect(wrapper.text()).toContain('Manage your technical skills, tools, and competencies.')
  })

  it('shows error state when query fails', () => {
    mockRefs.isPending.value = false
    mockRefs.isError.value = true
    const wrapper = mount(SkillsSection, {
      global: { plugins: [VueQueryPlugin], stubs },
    })
    expect(wrapper.text()).toContain('Could not load your skills')
    expect(wrapper.text()).toContain('Retry')
  })

  it('calls refreshCandidate on retry click', async () => {
    mockRefs.isPending.value = false
    mockRefs.isError.value = true
    const wrapper = mount(SkillsSection, {
      global: { plugins: [VueQueryPlugin], stubs },
    })
    const retryBtn = wrapper.findAll('button').find((b) => b.text().includes('Retry'))
    await retryBtn!.trigger('click')
    expect(mockRefs.refreshCandidate).toHaveBeenCalled()
  })

  it('shows empty state when no skills', () => {
    mockRefs.isPending.value = false
    mockRefs.isError.value = false
    mockRefs.skills.value = []
    const wrapper = mount(SkillsSection, {
      global: { plugins: [VueQueryPlugin], stubs },
    })
    expect(wrapper.text()).toContain('No skills added yet')
  })

  it('shows "Add Your First Skill" button when empty', () => {
    mockRefs.isPending.value = false
    mockRefs.isError.value = false
    mockRefs.skills.value = []
    const wrapper = mount(SkillsSection, {
      global: { plugins: [VueQueryPlugin], stubs },
    })
    expect(wrapper.text()).toContain('Add Your First Skill')
  })

  it('renders skill cards when skills exist', () => {
    mockRefs.isPending.value = false
    mockRefs.isError.value = false
    mockRefs.skills.value = [
      makeSkill({ id: 1 }),
      makeSkill({
        id: 2,
        skill: {
          id: 11,
          name: 'Python',
          normalized_name: 'python',
          category: 'Language',
          is_active: true,
          aliases: [],
          created_at: '',
          updated_at: '',
        },
      }),
    ]
    const wrapper = mount(SkillsSection, {
      global: { plugins: [VueQueryPlugin], stubs },
    })
    expect(wrapper.text()).toContain('TypeScript')
    expect(wrapper.text()).toContain('Python')
  })

  it('shows announcement when present', () => {
    mockRefs.isPending.value = false
    mockRefs.announcement.value = 'Skill added.'
    const wrapper = mount(SkillsSection, {
      global: { plugins: [VueQueryPlugin], stubs },
    })
    expect(wrapper.text()).toContain('Skill added.')
  })

  it('shows Add Skill button when skills exist', () => {
    mockRefs.isPending.value = false
    mockRefs.isError.value = false
    mockRefs.skills.value = [makeSkill()]
    const wrapper = mount(SkillsSection, {
      global: { plugins: [VueQueryPlugin], stubs },
    })
    expect(wrapper.text()).toContain('Add Skill')
  })
})
