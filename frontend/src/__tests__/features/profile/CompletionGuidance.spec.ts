import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CompletionGuidance from '@/features/profile/components/CompletionGuidance.vue'

function makeDetails(
  areas: Array<{
    key: string
    earned: number
    available: number
    complete: boolean
    guidance: string | null
  }>,
) {
  return {
    areas,
    missing_areas: areas
      .filter((a) => !a.complete)
      .map((a) => ({ key: a.key, guidance: a.guidance ?? '' })),
  }
}

describe('CompletionGuidance', () => {
  it('shows guidance when areas are missing', () => {
    const details = makeDetails([
      {
        key: 'experience',
        earned: 0,
        available: 2,
        complete: false,
        guidance: 'Add at least one work experience',
      },
    ])
    const wrapper = mount(CompletionGuidance, { props: { details } })
    expect(wrapper.text()).toContain('Profile strength')
    expect(wrapper.text()).toContain('Recommended next step')
    expect(wrapper.text()).toContain('experience')
  })

  it('shows complete message when all areas are satisfied', () => {
    const details = makeDetails([
      { key: 'experience', earned: 2, available: 2, complete: true, guidance: null },
    ])
    const wrapper = mount(CompletionGuidance, { props: { details } })
    expect(wrapper.text()).toContain('Profile strength')
    expect(wrapper.text()).toContain('1 of 1')
  })

  it('renders all areas complete when there are no areas', () => {
    const wrapper = mount(CompletionGuidance, {
      props: { details: { areas: [], missing_areas: [] } },
    })
    expect(wrapper.text()).toContain('Profile strength')
    expect(wrapper.text()).toContain('0 of 0')
  })
})
