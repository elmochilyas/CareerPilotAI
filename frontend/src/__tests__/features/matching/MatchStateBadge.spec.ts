import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MatchStateBadge from '@/features/matching/components/MatchStateBadge.vue'

describe('MatchStateBadge', () => {
  it('renders the state label with an icon', () => {
    const wrapper = mount(MatchStateBadge, { props: { state: 'gap' } })

    expect(wrapper.text()).toContain('Gap')
    expect(wrapper.find('svg').exists()).toBe(true)
  })

  it('shows an optional count with tabular figures', () => {
    const wrapper = mount(MatchStateBadge, { props: { state: 'matched', count: 4 } })

    expect(wrapper.text()).toContain('Matched')
    expect(wrapper.text()).toContain('4')
  })

  it('does not render a count when omitted', () => {
    const wrapper = mount(MatchStateBadge, { props: { state: 'unknown' } })

    expect(wrapper.text()).toContain('Unknown')
    expect(wrapper.text()).not.toMatch(/\d/)
  })
})
