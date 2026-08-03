import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OpportunityPipelineSummary from '@/features/opportunities/components/OpportunityPipelineSummary.vue'

describe('OpportunityPipelineSummary', () => {
  it('explains the three pipeline stages and their totals', () => {
    const wrapper = mount(OpportunityPipelineSummary, {
      props: {
        attentionCount: 2,
        activeCount: 1,
        savedCount: 4,
      },
    })

    expect(wrapper.get('h2').text()).toBe('Opportunity summary')
    expect(wrapper.findAll('h3').map((item) => item.text())).toEqual([
      'Saved',
      'In progress',
      'Needs review',
    ])
    expect(wrapper.findAll('.summary-count').map((item) => item.text())).toEqual(['4', '1', '2'])
    expect(wrapper.findAll('.summary-unit').map((item) => item.text())).toEqual([
      'roles',
      'role',
      'roles',
    ])
    expect(wrapper.text()).toContain('Approved roles kept in your trusted shortlist.')
    expect(wrapper.text()).toContain('Review to keep your pipeline moving')
    expect(wrapper.get('.summary-card-attention').classes()).toContain('summary-card-has-items')
  })

  it('announces loading state while counts update', () => {
    const wrapper = mount(OpportunityPipelineSummary, {
      props: {
        attentionCount: 0,
        activeCount: 0,
        savedCount: 0,
        loading: true,
      },
    })

    expect(wrapper.get('ul').attributes('aria-busy')).toBe('true')
    expect(wrapper.get('ul').attributes('aria-live')).toBe('polite')
    expect(wrapper.findAll('.summary-count').every((item) => item.text() === '—')).toBe(true)
    expect(
      wrapper.findAll('.summary-status').every((item) => item.text() === 'Updating pipeline…'),
    ).toBe(true)
  })
})
