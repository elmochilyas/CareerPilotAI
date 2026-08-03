import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OpportunityContextHeader from '@/features/opportunities/components/OpportunityContextHeader.vue'

describe('OpportunityContextHeader', () => {
  it('renders available job identity, metadata, status, and review progress', () => {
    const wrapper = mount(OpportunityContextHeader, {
      props: {
        title: 'Backend Developer',
        company: 'Ratbacher GmbH',
        personalLabel: 'Priority role',
        location: 'Essen, Germany',
        workMode: 'remote',
        contractType: 'full-time',
        seniority: 'senior',
        status: 'review_ready',
        updatedAt: '2026-07-27T10:00:00Z',
        reviewed: 4,
        total: 10,
      },
    })

    expect(wrapper.text()).toContain('Backend Developer')
    expect(wrapper.text()).toContain('Ratbacher GmbH')
    expect(wrapper.text()).toContain('Priority role')
    expect(wrapper.text()).toContain('Essen, Germany')
    expect(wrapper.text()).toContain('Remote')
    expect(wrapper.text()).toContain('Ready to review')
    expect(wrapper.text()).toContain('4 of 10 reviewed')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('4')
  })

  it('hides metadata that is not available', () => {
    const wrapper = mount(OpportunityContextHeader, {
      props: {
        title: 'Backend Developer',
        reviewed: 0,
        total: 2,
      },
    })

    expect(wrapper.text()).not.toContain('Location')
    expect(wrapper.text()).not.toContain('Work mode')
    expect(wrapper.find('[aria-label="Job metadata"]').exists()).toBe(false)
  })
})
