import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CvUnsupportedSuggestion from '@/features/cv-ingestion/components/CvUnsupportedSuggestion.vue'
import type { CvSuggestion } from '@/features/cv-ingestion/types'

function makeUnknown(overrides: Partial<CvSuggestion> = {}): CvSuggestion {
  return {
    id: 1,
    cv_document_id: 1,
    type: 'unknown_type' as never,
    category: null,
    field_name: null,
    current_value: null,
    suggested_value: { value: 'test' },
    source_page: null,
    source_text: null,
    extraction_method: 'ai_extraction',
    confidence: null,
    review_status: 'pending',
    reviewed_decision: null,
    reviewed_at: null,
    ...overrides,
  }
}

describe('CvUnsupportedSuggestion', () => {
  it('shows cannot-be-reviewed text', () => {
    const wrapper = mount(CvUnsupportedSuggestion, {
      props: { suggestion: makeUnknown() },
    })
    expect(wrapper.text()).toContain('cannot be reviewed')
  })

  it('shows the suggestion type safely', () => {
    const wrapper = mount(CvUnsupportedSuggestion, {
      props: { suggestion: makeUnknown() },
    })
    expect(wrapper.text()).toContain('unknown type')
  })

  it('shows ignore button', () => {
    const wrapper = mount(CvUnsupportedSuggestion, {
      props: { suggestion: makeUnknown() },
    })
    expect(wrapper.text()).toContain('Ignore')
  })

  it('emits rejected decision on ignore click', async () => {
    const wrapper = mount(CvUnsupportedSuggestion, {
      props: { suggestion: makeUnknown() },
    })
    const btn = wrapper.find('button')
    await btn.trigger('click')
    expect(wrapper.emitted('decision')).toBeTruthy()
    expect(wrapper.emitted('decision')![0]).toEqual([1, { decision: 'rejected' }])
  })

  it('does not render raw JSON', () => {
    const wrapper = mount(CvUnsupportedSuggestion, {
      props: { suggestion: makeUnknown() },
    })
    expect(wrapper.text()).not.toContain('{"value"')
    expect(wrapper.find('pre').exists()).toBe(false)
    expect(wrapper.find('code').exists()).toBe(false)
  })

  it('shows category when present', () => {
    const wrapper = mount(CvUnsupportedSuggestion, {
      props: { suggestion: makeUnknown({ category: 'Test Category' }) },
    })
    expect(wrapper.text()).toContain('Test Category')
  })
})
