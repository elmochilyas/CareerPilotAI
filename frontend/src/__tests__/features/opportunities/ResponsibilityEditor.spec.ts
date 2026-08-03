import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ResponsibilityEditor from '@/features/opportunities/components/ResponsibilityEditor.vue'
import type { JobSuggestion } from '@/features/opportunities/types'

function createSuggestion(overrides: Partial<JobSuggestion> = {}): JobSuggestion {
  return {
    id: 42,
    ingestion_id: 1,
    type: 'responsibility',
    group_key: null,
    field: null,
    extracted_value: { text: 'Manage team of developers' },
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

describe('ResponsibilityEditor', () => {
  it('renders responsibility text', () => {
    const wrapper = mount(ResponsibilityEditor, {
      props: { suggestion: createSuggestion() },
    })
    expect(wrapper.text()).toContain('Manage team of developers')
  })

  it('renders Keep button', () => {
    const wrapper = mount(ResponsibilityEditor, {
      props: { suggestion: createSuggestion() },
    })
    expect(wrapper.text()).toContain('Keep')
  })

  it('renders Remove button', () => {
    const wrapper = mount(ResponsibilityEditor, {
      props: { suggestion: createSuggestion() },
    })
    expect(wrapper.text()).toContain('Remove')
  })

  it('emits keep with suggestion id', () => {
    const wrapper = mount(ResponsibilityEditor, {
      props: { suggestion: createSuggestion() },
    })
    const btn = wrapper.findAll('button').find((b) => b.text() === 'Keep')
    btn?.trigger('click')
    expect(wrapper.emitted('keep')).toBeTruthy()
    expect(wrapper.emitted('keep')![0]).toEqual([42])
  })

  it('emits remove with suggestion id', () => {
    const wrapper = mount(ResponsibilityEditor, {
      props: { suggestion: createSuggestion() },
    })
    const btn = wrapper.findAll('button').find((b) => b.text() === 'Remove')
    btn?.trigger('click')
    expect(wrapper.emitted('remove')).toBeTruthy()
    expect(wrapper.emitted('remove')![0]).toEqual([42])
  })

  it('shows accepted state on Keep button', () => {
    const wrapper = mount(ResponsibilityEditor, {
      props: { suggestion: createSuggestion({ review_decision: 'accepted' }) },
    })
    const keepBtn = wrapper.findAll('button').find((b) => b.text() === 'Keep')
    expect(keepBtn?.classes()).toContain('bg-green-100')
  })

  it('shows pending state on Keep button', () => {
    const wrapper = mount(ResponsibilityEditor, {
      props: { suggestion: createSuggestion({ review_decision: 'pending' }) },
    })
    const keepBtn = wrapper.findAll('button').find((b) => b.text() === 'Keep')
    expect(keepBtn?.classes()).toContain('text-gray-500')
  })

  it('renders empty string when extracted_value has no text', () => {
    const wrapper = mount(ResponsibilityEditor, {
      props: { suggestion: createSuggestion({ extracted_value: {} }) },
    })
    expect(wrapper.text()).toContain('Keep')
  })
})
