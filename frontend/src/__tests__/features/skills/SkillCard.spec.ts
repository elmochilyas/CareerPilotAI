import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SkillCard from '@/features/skills/components/SkillCard.vue'
import type { CandidateSkill } from '@/features/skills/types'

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
    years_experience: 3,
    last_used_at: '2025-01-01',
    evidence: [],
    verification_at_risk: false,
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

describe('SkillCard', () => {
  it('renders catalog skill name', () => {
    const wrapper = mount(SkillCard, { props: { skill: makeSkill(), saving: false } })
    expect(wrapper.text()).toContain('TypeScript')
  })

  it('renders "Custom Skill" for custom skills', () => {
    const wrapper = mount(SkillCard, {
      props: { skill: makeSkill({ is_custom: true, skill: null }), saving: false },
    })
    expect(wrapper.text()).toContain('Custom Skill')
  })

  it('shows custom badge for custom skills', () => {
    const wrapper = mount(SkillCard, {
      props: { skill: makeSkill({ is_custom: true, skill: null }), saving: false },
    })
    expect(wrapper.text()).toContain('custom')
  })

  it('shows state chip with claimed state', () => {
    const wrapper = mount(SkillCard, {
      props: { skill: makeSkill({ state: 'claimed' }), saving: false },
    })
    expect(wrapper.text()).toContain('Claimed')
  })

  it('shows proficiency level', () => {
    const wrapper = mount(SkillCard, {
      props: { skill: makeSkill({ proficiency_level: 'expert' }), saving: false },
    })
    expect(wrapper.text()).toContain('expert')
  })

  it('shows years experience', () => {
    const wrapper = mount(SkillCard, {
      props: { skill: makeSkill({ years_experience: 3 }), saving: false },
    })
    expect(wrapper.text()).toContain('3 years')
  })

  it('shows evidence count', () => {
    const skill = makeSkill({
      evidence: [{ key: 'e1', type: 'url', value: 'https://example.com', label: 'Cert' }],
    })
    const wrapper = mount(SkillCard, { props: { skill, saving: false } })
    expect(wrapper.text()).toContain('1 evidence entry')
  })

  it('shows verification warning when at risk', () => {
    const wrapper = mount(SkillCard, {
      props: { skill: makeSkill({ verification_at_risk: true }), saving: false },
    })
    expect(wrapper.text()).toContain('Verified but no evidence provided')
  })

  it('shows Manage evidence button', () => {
    const wrapper = mount(SkillCard, { props: { skill: makeSkill(), saving: false } })
    expect(wrapper.text()).toContain('Manage evidence')
  })

  it('shows Archive button for non-archived skills', () => {
    const wrapper = mount(SkillCard, {
      props: { skill: makeSkill({ state: 'claimed' }), saving: false },
    })
    expect(wrapper.text()).toContain('Archive')
  })

  it('shows Restore button for archived skills', () => {
    const wrapper = mount(SkillCard, {
      props: { skill: makeSkill({ state: 'archived' }), saving: false },
    })
    expect(wrapper.text()).toContain('Restore')
  })

  it('shows Remove button for claimable states', () => {
    const wrapper = mount(SkillCard, {
      props: { skill: makeSkill({ state: 'claimed' }), saving: false },
    })
    expect(wrapper.text()).toContain('Remove')
  })

  it('emits edit event', async () => {
    const skill = makeSkill()
    const wrapper = mount(SkillCard, { props: { skill, saving: false } })
    await wrapper.findAll('button')[0].trigger('click')
    expect(wrapper.emitted('edit')).toBeTruthy()
    expect(wrapper.emitted('edit')![0]).toEqual([skill])
  })

  it('emits archive event', async () => {
    const skill = makeSkill()
    const wrapper = mount(SkillCard, { props: { skill, saving: false } })
    const buttons = wrapper.findAll('button')
    const archiveBtn = buttons.find((b) => b.text().includes('Archive'))
    await archiveBtn!.trigger('click')
    expect(wrapper.emitted('archive')).toBeTruthy()
    expect(wrapper.emitted('archive')![0]).toEqual([skill])
  })

  it('emits restore event for archived skills', async () => {
    const skill = makeSkill({ state: 'archived' })
    const wrapper = mount(SkillCard, { props: { skill, saving: false } })
    const restoreBtn = wrapper.findAll('button').find((b) => b.text().includes('Restore'))
    await restoreBtn!.trigger('click')
    expect(wrapper.emitted('restore')).toBeTruthy()
    expect(wrapper.emitted('restore')![0]).toEqual([skill])
  })

  it('emits delete event', async () => {
    const skill = makeSkill()
    const wrapper = mount(SkillCard, { props: { skill, saving: false } })
    const buttons = wrapper.findAll('button')
    const deleteBtn = buttons.find((b) => b.text().includes('Remove'))
    await deleteBtn!.trigger('click')
    expect(wrapper.emitted('delete')).toBeTruthy()
    expect(wrapper.emitted('delete')![0]).toEqual([skill])
  })

  it('hides action buttons while saving', () => {
    const wrapper = mount(SkillCard, { props: { skill: makeSkill(), saving: true } })
    expect(wrapper.text()).not.toContain('Edit')
  })
})
