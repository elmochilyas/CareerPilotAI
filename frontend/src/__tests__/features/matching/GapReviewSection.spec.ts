import { describe, expect, it } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import GapReviewSection from '@/features/matching/components/GapReviewSection.vue'
import type { MatchFinding } from '@/features/matching/types'

const gap: MatchFinding = {
  source_type: 'job_requirement',
  source_id: 1,
  requirement_text: 'Laravel testing',
  requirement_label: 'Laravel testing',
  importance: 'required',
  category: 'required_skills',
  match_state: 'gap',
  factor: 0,
  matched_candidate_skill_id: null,
  evidence_refs: [],
  justification: null,
  confidence: null,
  classifier_source: null,
  display_order: 1,
}

describe('GapReviewSection', () => {
  it('always links the gaps view to the clarification validation workflow', () => {
    const wrapper = mount(GapReviewSection, {
      props: {
        findings: [gap],
        questions: [],
        opportunityId: 1,
      },
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
        mocks: {
          $router: { push: () => undefined },
        },
      },
    })

    const validationLink = wrapper
      .findAllComponents(RouterLinkStub)
      .find((link) => link.text().includes('Validate gaps'))

    expect(validationLink).toBeDefined()
    expect(validationLink!.props('to')).toEqual({
      name: 'opportunities-match-clarifications',
      params: { id: 1 },
    })
  })
})
