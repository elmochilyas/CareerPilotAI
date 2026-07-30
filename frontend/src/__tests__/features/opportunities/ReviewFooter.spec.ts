import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ReviewFooter from '@/features/opportunities/components/ReviewFooter.vue'

describe('ReviewFooter', () => {
  it('shows exact review progress and emits the primary action', async () => {
    const wrapper = mount(ReviewFooter, {
      props: {
        reviewed: 6,
        total: 10,
        primaryLabel: 'Save and continue',
      },
    })

    expect(wrapper.text()).toContain('6 of 10 reviewed')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('6')
    expect(wrapper.get('.footer-progress > span').attributes('style')).toContain('scaleX(0.6)')
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('primary')).toBeTruthy()
  })

  it('explains why the final action is disabled', () => {
    const wrapper = mount(ReviewFooter, {
      props: {
        reviewed: 8,
        total: 10,
        primaryLabel: 'Generate preview',
        primaryDisabled: true,
        blockingReason: 'Review 2 remaining items before continuing.',
        finalStep: true,
      },
    })

    expect(wrapper.get('.footer-primary').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Review 2 remaining items before continuing.')
  })

  it('keeps the primary action disabled and stable while loading', () => {
    const wrapper = mount(ReviewFooter, {
      props: {
        reviewed: 4,
        total: 10,
        primaryLabel: 'Save and continue',
        pending: true,
        pendingLabel: 'Saving…',
      },
    })

    expect(wrapper.get('.footer-primary').attributes('disabled')).toBeDefined()
    expect(wrapper.get('.footer-primary').text()).toBe('Saving…')
  })
})
