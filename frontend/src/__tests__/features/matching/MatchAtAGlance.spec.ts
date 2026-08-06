import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MatchAtAGlance from '@/features/matching/components/MatchAtAGlance.vue'
import type { MatchFinding } from '@/features/matching/types'

function makeFinding(overrides: Partial<MatchFinding> = {}): MatchFinding {
  return {
    source_type: 'job_requirement',
    source_id: 1,
    requirement_text: 'Fluent English',
    requirement_label: null,
    importance: 'required',
    category: 'language_soft',
    match_state: 'gap',
    factor: 0,
    matched_candidate_skill_id: null,
    evidence_refs: [],
    justification: null,
    confidence: null,
    classifier_source: null,
    display_order: 1,
    ...overrides,
  }
}

const strengthsGroup = (wrapper: ReturnType<typeof mount>) =>
  wrapper.get('[aria-label="Strengths"]')
const gapsGroup = (wrapper: ReturnType<typeof mount>) =>
  wrapper.get('[aria-label="Needs attention"]')

describe('MatchAtAGlance', () => {
  it('lists the top three strengths and top three gaps', () => {
    const findings = [
      makeFinding({ source_id: 1, requirement_text: 'Laravel', match_state: 'matched' }),
      makeFinding({ source_id: 2, requirement_text: 'REST APIs', match_state: 'matched' }),
      makeFinding({ source_id: 3, requirement_text: 'MySQL', match_state: 'matched' }),
      makeFinding({ source_id: 4, requirement_text: 'Redis', match_state: 'matched' }),
      makeFinding({ source_id: 5, requirement_text: 'German C1', match_state: 'gap' }),
      makeFinding({ source_id: 6, requirement_text: 'AWS', match_state: 'gap' }),
      makeFinding({ source_id: 7, requirement_text: 'Node.js', match_state: 'gap' }),
      makeFinding({ source_id: 8, requirement_text: 'Docker', match_state: 'gap' }),
    ]

    const wrapper = mount(MatchAtAGlance, { props: { findings } })

    const strengths = strengthsGroup(wrapper)
    const gaps = gapsGroup(wrapper)

    expect(strengths.text()).toContain('Laravel')
    expect(strengths.text()).toContain('REST APIs')
    expect(strengths.text()).toContain('MySQL')
    expect(strengths.text()).not.toContain('Redis')
    expect(strengths.text()).toContain('+ 1 more strength')

    expect(gaps.text()).toContain('German C1')
    expect(gaps.text()).toContain('AWS')
    expect(gaps.text()).toContain('Node.js')
    expect(gaps.text()).not.toContain('Docker')
    expect(gaps.text()).toContain('View all 1 gap')
  })

  it('does not show partial or unknown findings inside the two groups', () => {
    const wrapper = mount(MatchAtAGlance, {
      props: {
        findings: [
          makeFinding({ source_id: 1, requirement_text: 'Laravel', match_state: 'matched' }),
          makeFinding({ source_id: 2, requirement_text: 'Redis', match_state: 'partial' }),
          makeFinding({ source_id: 3, requirement_text: 'Kubernetes', match_state: 'unknown' }),
        ],
      },
    })

    const strengths = strengthsGroup(wrapper)
    const gaps = gapsGroup(wrapper)

    expect(strengths.text()).toContain('Laravel')
    expect(strengths.text()).not.toContain('Redis')
    expect(gaps.text()).not.toContain('Redis')
    expect(strengths.text()).not.toContain('Kubernetes')
    expect(gaps.text()).not.toContain('Kubernetes')
  })

  it('renders a gap exactly once in the summary', () => {
    const wrapper = mount(MatchAtAGlance, {
      props: {
        findings: [
          makeFinding({ source_id: 1, requirement_text: 'German C1', match_state: 'gap' }),
          makeFinding({ source_id: 2, requirement_text: 'Laravel', match_state: 'matched' }),
        ],
      },
    })

    const matches = wrapper.text().match(/German C1/g)
    expect(matches).toHaveLength(1)
  })

  it('prefers requirement labels over raw text', () => {
    const wrapper = mount(MatchAtAGlance, {
      props: {
        findings: [
          makeFinding({
            source_id: 1,
            requirement_text: 'Fluent English',
            requirement_label: 'English',
            match_state: 'matched',
          }),
        ],
      },
    })

    expect(strengthsGroup(wrapper).text()).toContain('English')
    expect(strengthsGroup(wrapper).text()).not.toContain('Fluent English')
  })

  it('emits view-gaps when a gap overflow link is used', async () => {
    const findings = Array.from({ length: 5 }, (_, index) =>
      makeFinding({ source_id: index + 1, match_state: 'gap' }),
    )
    const wrapper = mount(MatchAtAGlance, { props: { findings } })

    await gapsGroup(wrapper).find('button').trigger('click')

    expect(wrapper.emitted('view-gaps')).toHaveLength(1)
  })

  it('emits expand-all when the strengths overflow link is used', async () => {
    const findings = Array.from({ length: 5 }, (_, index) =>
      makeFinding({ source_id: index + 1, match_state: 'matched' }),
    )
    const wrapper = mount(MatchAtAGlance, { props: { findings } })

    await strengthsGroup(wrapper).find('button').trigger('click')

    expect(wrapper.emitted('expand-all')).toHaveLength(1)
  })

  it('offers a Review gaps action when gaps exist and View full analysis otherwise', () => {
    const withGaps = mount(MatchAtAGlance, {
      props: { findings: [makeFinding({ match_state: 'gap' })] },
    })
    const withoutGaps = mount(MatchAtAGlance, {
      props: { findings: [makeFinding({ match_state: 'matched' })] },
    })

    expect(withGaps.text()).toContain('Review gaps')
    expect(withoutGaps.text()).toContain('View full analysis')
  })

  it('shows a neutral note for unknown requirements and emits expand-unknown', async () => {
    const wrapper = mount(MatchAtAGlance, {
      props: {
        findings: [
          makeFinding({ source_id: 1, match_state: 'gap' }),
          makeFinding({ source_id: 2, match_state: 'unknown' }),
          makeFinding({ source_id: 3, match_state: 'unknown' }),
        ],
      },
    })

    expect(wrapper.text()).toContain('2 requirements could not be fully evaluated.')
    expect(gapsGroup(wrapper).text()).not.toContain('Unknown')

    const reviewButton = wrapper.findAll('button').find((button) => button.text() === 'Review them')
    await reviewButton!.trigger('click')

    expect(wrapper.emitted('expand-unknown')).toHaveLength(1)
  })
})
