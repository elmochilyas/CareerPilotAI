import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CvSkillChip from '@/features/cv-ingestion/components/CvSkillChip.vue'
import type { CvSuggestion } from '@/features/cv-ingestion/types'

function makeSkill(overrides: Partial<CvSuggestion<'skill'>> = {}): CvSuggestion<'skill'> {
  return {
    id: 1,
    cv_document_id: 1,
    type: 'skill',
    category: 'Backend',
    field_name: null,
    current_value: null,
    suggested_value: { name: 'Laravel', category: 'Backend' },
    source_page: null,
    source_text: null,
    extraction_method: 'ai_extraction',
    confidence: 0.95,
    review_status: 'pending',
    reviewed_decision: null,
    reviewed_at: null,
    ...overrides,
  } as CvSuggestion<'skill'>
}

describe('CvSkillChip', () => {
  it('renders skill name', () => {
    const wrapper = mount(CvSkillChip, { props: { suggestion: makeSkill() } })
    expect(wrapper.text()).toContain('Laravel')
  })

  it('shows remove button', () => {
    const wrapper = mount(CvSkillChip, { props: { suggestion: makeSkill() } })
    const btn = wrapper.find('button[aria-label*="Remove"]')
    expect(btn.exists()).toBe(true)
  })

  it('remove button has accessible label', () => {
    const wrapper = mount(CvSkillChip, { props: { suggestion: makeSkill() } })
    const btn = wrapper.find('button[aria-label="Remove Laravel from imported skills"]')
    expect(btn.exists()).toBe(true)
  })

  it('emits remove on click', async () => {
    const wrapper = mount(CvSkillChip, { props: { suggestion: makeSkill() } })
    const btn = wrapper.find('button[aria-label*="Remove"]')
    await btn.trigger('click')
    expect(wrapper.emitted('remove')).toBeTruthy()
    expect(wrapper.emitted('remove')![0]).toEqual([1])
  })

  it('shows undo button when removed', () => {
    const wrapper = mount(CvSkillChip, {
      props: { suggestion: makeSkill(), removed: true },
    })
    expect(wrapper.text()).toContain('Undo')
    expect(wrapper.text()).toContain('Laravel')
  })

  it('emits undo on click', async () => {
    const wrapper = mount(CvSkillChip, {
      props: { suggestion: makeSkill(), removed: true },
    })
    const btns = wrapper.findAll('button')
    const undoBtn = btns.find((b) => b.text() === 'Undo')
    await undoBtn!.trigger('click')
    expect(wrapper.emitted('undo')).toBeTruthy()
  })

  it('shows existing badge', () => {
    const wrapper = mount(CvSkillChip, {
      props: {
        suggestion: makeSkill({ current_value: { name: 'Laravel' } }),
        existing: true,
      },
    })
    expect(wrapper.text()).toContain('In profile')
  })

  it('shows archived badge', () => {
    const wrapper = mount(CvSkillChip, {
      props: {
        suggestion: makeSkill({ current_value: { name: 'Laravel', state: 'archived' } }),
        archived: true,
      },
    })
    expect(wrapper.text()).toContain('Archived')
  })

  it('hides remove button in readonly mode', () => {
    const wrapper = mount(CvSkillChip, {
      props: { suggestion: makeSkill(), readonly: true },
    })
    expect(wrapper.find('button[aria-label*="Remove"]').exists()).toBe(false)
  })

  it('shows line-through when removed', () => {
    const wrapper = mount(CvSkillChip, {
      props: { suggestion: makeSkill(), removed: true },
    })
    expect(wrapper.classes()).toContain('line-through')
  })
})
