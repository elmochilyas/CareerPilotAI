import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OpportunityStatusBadge from '@/features/opportunities/components/OpportunityStatusBadge.vue'

describe('OpportunityStatusBadge', () => {
  it.each([
    ['processing', 'Analyzing', 'status-info'],
    ['review_ready', 'Ready to review', 'status-attention'],
    ['failed', 'Needs attention', 'status-danger'],
    ['cancelled', 'Cancelled', 'status-neutral'],
    ['confirmed', 'Confirmed', 'status-success'],
  ] as const)('renders the candidate-facing label for %s', (status, label, className) => {
    const wrapper = mount(OpportunityStatusBadge, {
      props: { status },
    })

    expect(wrapper.text()).toContain(label)
    expect(wrapper.get('.status-badge').classes()).toContain(className)
  })
})
