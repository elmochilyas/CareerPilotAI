import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ScalarReviewItem from '@/features/opportunities/components/ScalarReviewItem.vue'
import type { JobSuggestion } from '@/features/opportunities/types'

function suggestion(overrides: Partial<JobSuggestion> = {}): JobSuggestion {
  return {
    id: 8,
    ingestion_id: 1,
    type: 'company',
    group_key: 'overview',
    field: 'company',
    extracted_value: { value: 'Ratbacher GmbH' },
    edited_value: null,
    review_decision: 'pending',
    source_evidence: 'Ratbacher GmbH is hiring a backend developer.',
    schema_version: '1.0.0',
    resolution: null,
    resolved_skill_id: null,
    reviewed_at: null,
    version: 1,
    created_at: '2026-07-27T10:00:00Z',
    updated_at: '2026-07-27T10:00:00Z',
    ...overrides,
  }
}

describe('ScalarReviewItem', () => {
  it('renders the undecided visual state', () => {
    const wrapper = mount(ScalarReviewItem, {
      props: { suggestion: suggestion(), label: 'Company', value: 'Ratbacher GmbH' },
    })

    expect(wrapper.get('article').classes()).toContain('scalar-review-item-pending')
    expect(wrapper.text()).toContain('Include')
    expect(wrapper.text()).toContain('Edit')
    expect(wrapper.text()).toContain('Exclude')
  })

  it('renders the extracted value and source badge without raw JSON', () => {
    const wrapper = mount(ScalarReviewItem, {
      props: {
        suggestion: suggestion(),
        label: 'Company',
        value: 'Ratbacher GmbH',
        sourceLabel: 'Job heading',
      },
    })

    expect(wrapper.text()).toContain('Ratbacher GmbH')
    expect(wrapper.text()).toContain('Source: Job heading')
    expect(wrapper.text()).toContain('View source')
    expect(wrapper.text()).not.toContain('{"value"')
    expect(wrapper.text()).not.toContain('[object Object]')
  })

  it('emits keep and exclude decisions', async () => {
    const wrapper = mount(ScalarReviewItem, {
      props: { suggestion: suggestion(), label: 'Company', value: 'Ratbacher GmbH' },
    })

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Include')!
      .trigger('click')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Exclude')!
      .trigger('click')

    expect(wrapper.emitted('keep')?.[0]).toEqual([8])
    expect(wrapper.emitted('exclude')?.[0]).toEqual([8])
  })

  it('supports editing the extracted value', async () => {
    const wrapper = mount(ScalarReviewItem, {
      props: { suggestion: suggestion(), label: 'Company', value: 'Ratbacher GmbH' },
    })

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Edit')!
      .trigger('click')
    await wrapper.get('input').setValue('Ratbacher AG')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('edit')?.[0]).toEqual([8, 'Ratbacher AG'])
  })

  it('shows accepted items in a compact completed state', () => {
    const wrapper = mount(ScalarReviewItem, {
      props: {
        suggestion: suggestion({ review_decision: 'accepted' }),
        label: 'Company',
        value: 'Ratbacher GmbH',
      },
    })

    expect(wrapper.text()).toContain('Included in opportunity')
    expect(wrapper.text()).toContain('Change')
    expect(wrapper.text()).not.toContain('Exclude')
    expect(wrapper.get('article').classes()).toContain('scalar-review-item-included')
  })

  it('shows edited items with their persisted status', () => {
    const wrapper = mount(ScalarReviewItem, {
      props: {
        suggestion: suggestion({
          review_decision: 'edited',
          edited_value: { value: 'Ratbacher AG' },
        }),
        label: 'Company',
        value: 'Ratbacher AG',
      },
    })

    expect(wrapper.text()).toContain('Edited value will be used')
    expect(wrapper.text()).toContain('View change')
    expect(wrapper.get('article').classes()).toContain('scalar-review-item-edited')
  })

  it('allows an excluded item to be restored', async () => {
    const wrapper = mount(ScalarReviewItem, {
      props: {
        suggestion: suggestion({ review_decision: 'rejected' }),
        label: 'Company',
        value: 'Ratbacher GmbH',
      },
    })

    expect(wrapper.text()).toContain('Excluded from opportunity')
    expect(wrapper.get('article').classes()).toContain('scalar-review-item-excluded')
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('restore')?.[0]).toEqual([8])
  })
})
