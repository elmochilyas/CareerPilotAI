import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SkillChipEditor from '@/features/opportunities/components/SkillChipEditor.vue'
import type { JobSuggestion } from '@/features/opportunities/types'

function createSuggestion(overrides: Partial<JobSuggestion> = {}): JobSuggestion {
  return {
    id: 1,
    ingestion_id: 1,
    type: 'required_skill',
    group_key: null,
    field: null,
    extracted_value: { label: 'Laravel' },
    edited_value: null,
    review_decision: 'pending',
    source_evidence: null,
    schema_version: '1.0.0',
    resolution: null,
    resolved_skill_id: null,
    reviewed_at: null,
    version: 1,
    created_at: '2026-07-26T12:00:00Z',
    updated_at: '2026-07-26T12:00:00Z',
    ...overrides,
  }
}

describe('SkillChipEditor', () => {
  it('renders skill label', () => {
    const wrapper = mount(SkillChipEditor, {
      props: { suggestion: createSuggestion() },
    })
    expect(wrapper.text()).toContain('Laravel')
  })

  it('does not show resolution badge when no resolution', () => {
    const wrapper = mount(SkillChipEditor, {
      props: { suggestion: createSuggestion() },
    })
    expect(wrapper.text()).not.toContain('Resolve')
  })

  it('shows Resolve badge when ambiguous', () => {
    const wrapper = mount(SkillChipEditor, {
      props: { suggestion: createSuggestion({ resolution: 'ambiguous' }) },
    })
    expect(wrapper.text()).toContain('Resolve')
  })

  it('shows resolution name when resolved', () => {
    const wrapper = mount(SkillChipEditor, {
      props: { suggestion: createSuggestion({ resolution: 'exact' }) },
    })
    expect(wrapper.text()).toContain('exact')
  })

  it('emits keep when Keep button clicked', () => {
    const wrapper = mount(SkillChipEditor, {
      props: { suggestion: createSuggestion() },
    })
    wrapper.findAll('button').forEach((btn) => {
      if (btn.text() === 'Keep') btn.trigger('click')
    })
    expect(wrapper.emitted('keep')).toBeTruthy()
    expect(wrapper.emitted('keep')![0]).toEqual([1])
  })

  it('emits remove when Remove button clicked', () => {
    const wrapper = mount(SkillChipEditor, {
      props: { suggestion: createSuggestion() },
    })
    wrapper.findAll('button').forEach((btn) => {
      if (btn.text() === 'Remove') btn.trigger('click')
    })
    expect(wrapper.emitted('remove')).toBeTruthy()
    expect(wrapper.emitted('remove')![0]).toEqual([1])
  })

  it('hides action buttons when readonly', () => {
    const wrapper = mount(SkillChipEditor, {
      props: { suggestion: createSuggestion(), readonly: true },
    })
    expect(wrapper.text()).not.toContain('Keep')
    expect(wrapper.text()).not.toContain('Remove')
  })

  it('shows accepted state on Keep button', () => {
    const wrapper = mount(SkillChipEditor, {
      props: { suggestion: createSuggestion({ review_decision: 'accepted' }) },
    })
    const keepBtn = wrapper.findAll('button').find((b) => b.text() === 'Keep')
    expect(keepBtn?.classes()).toContain('bg-green-100')
  })
})
