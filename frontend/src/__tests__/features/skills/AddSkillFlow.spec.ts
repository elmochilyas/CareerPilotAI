import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AddSkillFlow from '@/features/skills/components/AddSkillFlow.vue'
import type { CandidateSkill } from '@/features/skills/types'

vi.mock('@tanstack/vue-query', async () => {
  const actual = await vi.importActual<typeof import('@tanstack/vue-query')>('@tanstack/vue-query')
  return {
    ...actual,
    useQuery: () => ({
      data: { value: [] },
      isPending: { value: false },
      isFetching: { value: false },
      isError: { value: false },
      error: { value: null },
    }),
  }
})

function makeExistingSkill(overrides: Partial<CandidateSkill> = {}): CandidateSkill {
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
    proficiency_level: 'intermediate',
    years_experience: null,
    last_used_at: null,
    evidence: [],
    verification_at_risk: false,
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

describe('AddSkillFlow', () => {
  it('shows search step initially', () => {
    const wrapper = mount(AddSkillFlow, {
      props: { saving: false, existingSkills: [] },
    })
    expect(wrapper.text()).toContain('Add a Skill')
    expect(wrapper.text()).toContain('Search the catalog or enter a custom skill')
  })

  it('shows cancel button', () => {
    const wrapper = mount(AddSkillFlow, {
      props: { saving: false, existingSkills: [] },
    })
    expect(wrapper.text()).toContain('Cancel')
  })

  it('emits cancel on cancel click', async () => {
    const wrapper = mount(AddSkillFlow, {
      props: { saving: false, existingSkills: [] },
    })
    const cancelBtn = wrapper.findAll('button').find((b) => b.text().includes('Cancel'))
    await cancelBtn!.trigger('click')
    expect(wrapper.emitted('cancel')).toBeTruthy()
  })

  it('shows custom skill step when "add a custom skill" clicked', async () => {
    const wrapper = mount(AddSkillFlow, {
      props: { saving: false, existingSkills: [] },
    })
    const customLink = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Or add a custom skill'))
    await customLink!.trigger('click')
    expect(wrapper.text()).toContain('Custom Skill')
    expect(wrapper.find('input').exists()).toBe(true)
  })

  it('shows details step from custom step', async () => {
    const wrapper = mount(AddSkillFlow, {
      props: { saving: false, existingSkills: [] },
    })
    const customLink = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Or add a custom skill'))
    await customLink!.trigger('click')
    const input = wrapper.find('input')
    await input.setValue('My Custom Skill')
    const nextBtn = wrapper.findAll('button').find((b) => b.text().includes('Next'))
    await nextBtn!.trigger('click')
    expect(wrapper.text()).toContain('Skill Details')
    expect(wrapper.text()).toContain('My Custom Skill')
  })

  it('shows state and proficiency selectors in details step', async () => {
    const wrapper = mount(AddSkillFlow, {
      props: { saving: false, existingSkills: [] },
    })
    const customLink = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Or add a custom skill'))
    await customLink!.trigger('click')
    const input = wrapper.find('input')
    await input.setValue('My Skill')
    const nextBtn = wrapper.findAll('button').find((b) => b.text().includes('Next'))
    await nextBtn!.trigger('click')
    expect(wrapper.text()).toContain('State')
    expect(wrapper.text()).toContain('Proficiency')
  })

  it('emits save with custom skill data', async () => {
    const wrapper = mount(AddSkillFlow, {
      props: { saving: false, existingSkills: [] },
    })
    const customLink = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Or add a custom skill'))
    await customLink!.trigger('click')
    const input = wrapper.find('input')
    await input.setValue('My Custom')
    const nextBtn = wrapper.findAll('button').find((b) => b.text().includes('Next'))
    await nextBtn!.trigger('click')
    const addBtn = wrapper.findAll('button').find((b) => b.text().includes('Add to Profile'))
    await addBtn!.trigger('click')
    expect(wrapper.emitted('save')).toBeTruthy()
    const savePayload = wrapper.emitted('save')![0][0]
    expect(savePayload.customName).toBe('My Custom')
    expect(savePayload.skillId).toBeNull()
    expect(savePayload.state).toBe('claimed')
    expect(savePayload.proficiency).toBe('intermediate')
  })

  it('shows duplicate error when skill already exists', async () => {
    const existing = [makeExistingSkill()]
    const wrapper = mount(AddSkillFlow, {
      props: { saving: false, existingSkills: existing },
    })
    expect(wrapper.text()).toContain('Add a Skill')
  })

  it('shows "Adding..." text while saving', async () => {
    const wrapper = mount(AddSkillFlow, {
      props: { saving: true, existingSkills: [] },
    })
    const customLink = wrapper
      .findAll('button')
      .find((b) => b.text().includes('Or add a custom skill'))
    await customLink!.trigger('click')
    const input = wrapper.find('input')
    await input.setValue('My Skill')
    const nextBtn = wrapper.findAll('button').find((b) => b.text().includes('Next'))
    await nextBtn!.trigger('click')
    expect(wrapper.text()).toContain('Adding...')
  })

  it('exposes a reset method', () => {
    const wrapper = mount(AddSkillFlow, {
      props: { saving: false, existingSkills: [] },
    })
    expect(typeof wrapper.vm.reset).toBe('function')
  })
})
