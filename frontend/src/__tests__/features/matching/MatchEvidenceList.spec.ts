import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MatchEvidenceList from '@/features/matching/components/MatchEvidenceList.vue'
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

describe('MatchEvidenceList', () => {
  it('renders each evidence reference with its label', () => {
    const wrapper = mount(MatchEvidenceList, {
      props: {
        finding: makeFinding({
          match_state: 'matched',
          evidence_refs: [
            { type: 'candidate_skill', id: 7, label: 'English — Native' },
            { type: 'project', id: 3, label: 'Support tooling' },
          ],
        }),
      },
    })

    expect(wrapper.get('ul').attributes('aria-label')).toBe('Candidate evidence')
    expect(wrapper.text()).toContain('English — Native')
    expect(wrapper.text()).toContain('Support tooling')
  })

  it('explains a missing skill for a skill gap', () => {
    const wrapper = mount(MatchEvidenceList, {
      props: {
        finding: makeFinding({ category: 'required_skills', match_state: 'gap' }),
      },
    })

    expect(wrapper.text()).toContain('No trusted skill evidence found in your profile.')
  })

  it('explains a missing language for a language gap', () => {
    const wrapper = mount(MatchEvidenceList, {
      props: {
        finding: makeFinding({ category: 'language_soft', match_state: 'gap' }),
      },
    })

    expect(wrapper.text()).toContain('No trusted language evidence found in your profile.')
  })

  it('falls back to a generic message for other empty states', () => {
    const wrapper = mount(MatchEvidenceList, {
      props: {
        finding: makeFinding({ match_state: 'matched' }),
      },
    })

    expect(wrapper.text()).toContain('No trusted candidate evidence found.')
  })
})
