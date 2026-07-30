import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ResponsibilityReviewItem from '@/features/opportunities/components/ResponsibilityReviewItem.vue'
import type { JobSuggestion } from '@/features/opportunities/types'

function createSuggestion(overrides: Partial<JobSuggestion> = {}): JobSuggestion {
  return {
    id: 42,
    ingestion_id: 1,
    type: 'responsibility',
    group_key: null,
    field: null,
    extracted_value: { text: 'Manage team of developers and architects.' },
    edited_value: null,
    review_decision: 'pending',
    source_evidence: 'Responsibilities section of the job description.',
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

describe('ResponsibilityReviewItem', () => {
  it('renders responsibility text', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion(), index: 0 },
    })

    expect(wrapper.text()).toContain('Manage team of developers and architects.')
  })

  it('renders the padded item number', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion(), index: 0 },
    })

    expect(wrapper.text()).toContain('01')
  })

  it('renders item number 13 for index 12', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion(), index: 12 },
    })

    expect(wrapper.text()).toContain('13')
  })

  it('shows Include button in pending state', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion({ review_decision: 'pending' }), index: 0 },
    })

    const includeBtn = wrapper.find('.action-include')
    expect(includeBtn.exists()).toBe(true)
    expect(includeBtn.text()).toBe('Include')
  })

  it('emits include with suggestion id', async () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion({ review_decision: 'pending' }), index: 0 },
    })

    await wrapper.find('.action-include').trigger('click')

    expect(wrapper.emitted('include')).toBeTruthy()
    expect(wrapper.emitted('include')![0]).toEqual([42])
  })

  it('shows Included state when accepted', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion({ review_decision: 'accepted' }), index: 0 },
    })

    expect(wrapper.find('.action-include').text()).toBe('Included')
    expect(wrapper.find('.action-include').attributes('aria-pressed')).toBe('true')
    expect(wrapper.text()).toContain('Included in opportunity')
  })

  it('shows Edited state with label', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: {
        suggestion: createSuggestion({
          review_decision: 'edited',
          edited_value: { text: 'Edited text.' },
        }),
        index: 0,
      },
    })

    expect(wrapper.text()).toContain('Edited value will be used')
  })

  it('shows Excluded state with muted text and an undo button', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion({ review_decision: 'rejected' }), index: 0 },
    })

    expect(wrapper.text()).toContain('Excluded from opportunity')
    expect(wrapper.find('.action-restore').exists()).toBe(true)
    expect(wrapper.find('.action-restore').text()).toBe('Undo remove')
  })

  it('emits restore with suggestion id', async () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion({ review_decision: 'rejected' }), index: 0 },
    })

    await wrapper.find('.action-restore').trigger('click')

    expect(wrapper.emitted('restore')).toBeTruthy()
    expect(wrapper.emitted('restore')![0]).toEqual([42])
  })

  it('opens an editor from the overflow menu and emits the edited payload on save', async () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion({ review_decision: 'pending' }), index: 0 },
    })

    const details = wrapper.find('.review-actions-overflow')
    await details.element.setAttribute('open', 'true')
    await details.find('button').trigger('click')

    const textarea = wrapper.find('textarea')
    expect(textarea.exists()).toBe(true)

    await textarea.setValue('Lead the platform engineering team.')
    await wrapper.find('.item-edit-form').trigger('submit')

    expect(wrapper.emitted('edit')).toEqual([[42, { text: 'Lead the platform engineering team.' }]])
  })

  it('renders the edited value instead of the extracted value', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: {
        suggestion: createSuggestion({
          review_decision: 'edited',
          edited_value: { text: 'Lead the platform engineering team.' },
        }),
        index: 0,
      },
    })

    expect(wrapper.text()).toContain('Lead the platform engineering team.')
    expect(wrapper.text()).not.toContain('Manage team of developers and architects.')
  })

  it('emits exclude from the overflow menu in pending state', async () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion({ review_decision: 'pending' }), index: 0 },
    })

    const details = wrapper.find('.review-actions-overflow')
    await details.element.setAttribute('open', 'true')
    const buttons = details.findAll('button')
    const excludeBtn = buttons.find((b) => b.text() === 'Exclude')
    await excludeBtn?.trigger('click')

    expect(wrapper.emitted('exclude')).toBeTruthy()
    expect(wrapper.emitted('exclude')![0]).toEqual([42])
  })

  it('renders source metadata when source_evidence is available', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: {
        suggestion: createSuggestion({
          source_evidence: 'Job description paragraph 3.',
        }),
        index: 0,
      },
    })

    expect(wrapper.text()).toContain('Source:')
    expect(wrapper.text()).toContain('Job description paragraph 3.')
  })

  it('hides source metadata when source_evidence is null', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: {
        suggestion: createSuggestion({ source_evidence: null }),
        index: 0,
      },
    })

    expect(wrapper.text()).not.toContain('Source:')
  })

  it('renders empty responsibility text when extracted_value has no text', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: {
        suggestion: createSuggestion({ extracted_value: {} }),
        index: 0,
      },
    })

    expect(wrapper.find('.item-text').text()).toBe('')
  })

  it('does not expose raw JSON in the rendered output', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion(), index: 0 },
    })

    expect(wrapper.text()).not.toContain('{"text"')
    expect(wrapper.text()).not.toContain('[object Object]')
  })

  it('does not show (blank) fallback', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion({ extracted_value: {} }), index: 0 },
    })

    expect(wrapper.text()).not.toContain('(blank)')
  })

  it('has an accessible aria-label on the article', () => {
    const wrapper = mount(ResponsibilityReviewItem, {
      props: { suggestion: createSuggestion(), index: 0 },
    })

    expect(wrapper.find('article').attributes('aria-label')).toContain('Responsibility 01')
  })
})
