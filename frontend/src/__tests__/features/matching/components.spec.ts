import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CategoryMeter from '@/features/matching/components/CategoryMeter.vue'
import InsufficientProfileGate from '@/features/matching/components/InsufficientProfileGate.vue'
import MatchErrorState from '@/features/matching/components/MatchErrorState.vue'
import MatchFilterBar from '@/features/matching/components/MatchFilterBar.vue'
import RequirementResultRow from '@/features/matching/components/RequirementResultRow.vue'
import ScoreAnchor from '@/features/matching/components/ScoreAnchor.vue'
import StaleNotice from '@/features/matching/components/StaleNotice.vue'
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

describe('ScoreAnchor', () => {
  it('renders the match percentage with an accessible label', () => {
    const wrapper = mount(ScoreAnchor, { props: { score: 84 } })

    expect(wrapper.text()).toContain('84%')
    expect(wrapper.text()).toContain('Match')
    const label = wrapper.find('[aria-label]').attributes('aria-label')
    expect(label).toContain('84')
    expect(label).toContain('100')
  })

  it('labels the alignment tier with human language', () => {
    const strong = mount(ScoreAnchor, { props: { score: 84 } })
    const weak = mount(ScoreAnchor, { props: { score: 30 } })

    expect(strong.text()).toContain('Good match')
    expect(weak.text()).toContain('Low match')
  })
})

describe('CategoryMeter', () => {
  it('renders a labeled meter with the category score', () => {
    const wrapper = mount(CategoryMeter, {
      props: { label: 'Required skills', score: 72, hasData: true },
    })

    expect(wrapper.text()).toContain('Required skills')
    expect(wrapper.text()).toContain('72%')
    expect(wrapper.find('[role="meter"]').attributes('aria-valuenow')).toBe('72')
    expect(wrapper.find('[role="meter"]').attributes('aria-valuemax')).toBe('100')
  })

  it('announces when the category has no candidate data', () => {
    const wrapper = mount(CategoryMeter, {
      props: { label: 'Evidence', score: 0, hasData: false },
    })

    expect(wrapper.text()).toContain('No candidate data available for this category')
    expect(wrapper.find('[role="meter"]').attributes('aria-valuetext')).toContain('Not evaluated')
  })
})

describe('RequirementResultRow', () => {
  it('renders a collapsed row with the requirement, state, and a simple summary', () => {
    const wrapper = mount(RequirementResultRow, {
      props: {
        finding: makeFinding({
          match_state: 'matched',
          evidence_refs: [{ type: 'candidate_skill', id: 7, label: 'English — Native' }],
        }),
      },
    })

    expect(wrapper.text()).toContain('Fluent English')
    expect(wrapper.text()).toContain('Matched')
    expect(wrapper.text()).toContain('Required language')
    expect(wrapper.text()).toContain('Matches your profile.')
    expect(wrapper.find('details').attributes('open')).toBeUndefined()
  })

  it('reveals the job requirement, evidence, and reason when expanded', async () => {
    const wrapper = mount(RequirementResultRow, {
      props: {
        finding: makeFinding({
          match_state: 'matched',
          evidence_refs: [{ type: 'candidate_skill', id: 7, label: 'English — Native' }],
        }),
      },
    })

    await wrapper.find('summary').trigger('click')

    expect(wrapper.find('details').attributes('open')).toBeDefined()
    expect(wrapper.text()).toContain('Job requires')
    expect(wrapper.text()).toContain('English — Native')
    expect(wrapper.text()).toContain('Why')
    expect(wrapper.text()).toContain('This matches your profile.')
  })

  it('explains why a gap exists in human terms without engine metrics', () => {
    const wrapper = mount(RequirementResultRow, {
      props: {
        finding: makeFinding({ category: 'required_skills', match_state: 'gap' }),
      },
    })

    expect(wrapper.text()).toContain('No matching profile evidence found.')
    expect(wrapper.text()).not.toContain('% credit')
    expect(wrapper.text()).not.toContain('Classifier confidence')
  })
})

describe('MatchFilterBar', () => {
  it('offers only the All, Matches, and Gaps pills', () => {
    const wrapper = mount(MatchFilterBar, {
      props: { matchFilter: 'all', importance: [], includeUnknown: false },
    })
    const buttons = wrapper.find('[aria-label="Filter by result"]').findAll('button')

    expect(buttons.map((button) => button.text())).toEqual(['All', 'Matches', 'Gaps'])
  })

  it('emits the match filter when a pill is selected', async () => {
    const wrapper = mount(MatchFilterBar, {
      props: { matchFilter: 'all', importance: [], includeUnknown: false },
    })
    const gapsButton = wrapper
      .find('[aria-label="Filter by result"]')
      .findAll('button')
      .find((button) => button.text().startsWith('Gaps'))

    await gapsButton!.trigger('click')

    expect(wrapper.emitted('update:matchFilter')).toEqual([['gap']])
  })

  it('shows finding counts next to state options when findings are provided', () => {
    const wrapper = mount(MatchFilterBar, {
      props: {
        matchFilter: 'all',
        importance: [],
        includeUnknown: false,
        findings: [
          makeFinding({ match_state: 'gap', importance: 'required' }),
          makeFinding({ match_state: 'matched', importance: 'preferred' }),
        ],
      },
    })
    const gapsButton = wrapper
      .find('[aria-label="Filter by result"]')
      .findAll('button')
      .find((button) => button.text().startsWith('Gaps'))

    expect(gapsButton!.text()).toContain('Gaps')
    expect(gapsButton!.text()).toContain('1')
  })

  it('emits the importance filter from the More filters checkboxes', async () => {
    const wrapper = mount(MatchFilterBar, {
      props: { matchFilter: 'all', importance: [], includeUnknown: false },
    })

    await wrapper.get('#filter-required').setValue(true)

    expect(wrapper.emitted('update:importance')).toEqual([[['required']]])
  })

  it('emits the unknown toggle from the More filters checkboxes', async () => {
    const wrapper = mount(MatchFilterBar, {
      props: { matchFilter: 'all', importance: [], includeUnknown: false },
    })

    await wrapper.get('#filter-unknown').setValue(true)

    expect(wrapper.emitted('update:includeUnknown')).toEqual([[true]])
  })
})

describe('StaleNotice', () => {
  it('emits recalculate and disables the button while busy', async () => {
    const wrapper = mount(StaleNotice, { props: { busy: false } })

    expect(wrapper.text()).toContain('Profile or opportunity changed since this analysis')

    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('recalculate')).toHaveLength(1)

    await wrapper.setProps({ busy: true })
    expect(wrapper.find('button').attributes('disabled')).toBeDefined()
  })
})

describe('InsufficientProfileGate', () => {
  it('explains the gate and links to the profile without a numeric score', () => {
    const wrapper = mount(InsufficientProfileGate, {
      global: { stubs: { RouterLink: true } },
    })

    expect(wrapper.text()).toContain('Profile needs more trusted data')
    expect(wrapper.text()).toContain('50% completion')
    expect(wrapper.findComponent({ name: 'RouterLink' }).exists()).toBe(true)
    expect(wrapper.text()).not.toMatch(/\b\d+\s*\/\s*100\b/)
  })
})

describe('MatchErrorState', () => {
  it('renders the problem detail and emits retry', async () => {
    const wrapper = mount(MatchErrorState, {
      props: { detail: 'The match could not be loaded.', busy: false },
    })

    expect(wrapper.attributes('role')).toBe('alert')
    expect(wrapper.text()).toContain('The match could not be loaded.')

    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })

  it('disables retry while busy', () => {
    const wrapper = mount(MatchErrorState, { props: { busy: true } })

    expect(wrapper.find('button').attributes('disabled')).toBeDefined()
  })
})
