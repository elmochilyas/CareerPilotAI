import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MatchSummaryHero from '@/features/matching/components/MatchSummaryHero.vue'
import type { MatchAnalysis, MatchFinding } from '@/features/matching/types'

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

function makeAnalysis(overrides: Partial<MatchAnalysis> = {}): MatchAnalysis {
  return {
    id: 9,
    candidate_profile_id: 10,
    job_opportunity_id: 5,
    status: 'completed',
    overall_score: 84,
    evidence_coverage_score: 67,
    counts: { required: 2, preferred: 1, matched: 1, partial: 0, gap: 1, unknown: 0 },
    versions: { algorithm: '1.0', scoring: '1.0', classifier_schema: '1.0' },
    fingerprints: { profile: 'p-1', opportunity: 'o-1' },
    stale: false,
    latest: true,
    warnings: [],
    failure: { code: null, reason: null },
    classifier: {
      provider: null,
      model: null,
      prompt_version: null,
      latency_ms: null,
      tokens_prompt: null,
      tokens_completion: null,
      response_id: null,
      status: null,
    },
    request_id: null,
    score_components: [],
    findings: [
      makeFinding({ source_id: 1, match_state: 'matched' }),
      makeFinding({ source_id: 2, match_state: 'gap' }),
      makeFinding({ source_id: 3, importance: 'preferred', match_state: 'matched' }),
    ],
    timestamps: {
      queued_at: '2026-07-27T10:00:00.000Z',
      processing_started_at: '2026-07-27T10:00:01.000Z',
      completed_at: '2026-07-27T10:00:02.000Z',
      failed_at: null,
      created_at: '2026-07-27T10:00:00.000Z',
      updated_at: '2026-07-27T10:00:02.000Z',
    },
    ...overrides,
  }
}

describe('MatchSummaryHero', () => {
  it('shows the match percentage and a human alignment label', () => {
    const wrapper = mount(MatchSummaryHero, { props: { analysis: makeAnalysis() } })

    expect(wrapper.text()).toContain('84%')
    expect(wrapper.text()).toContain('Match')
    expect(wrapper.text()).toContain('Good match')
  })

  it('summarizes how many important requirements match', () => {
    const wrapper = mount(MatchSummaryHero, { props: { analysis: makeAnalysis() } })

    expect(wrapper.text()).toContain('You match 1 of 2 important requirements.')
    expect(wrapper.text()).toContain('1 important gap needs attention.')
  })

  it('celebrates when there are no important gaps', () => {
    const wrapper = mount(MatchSummaryHero, {
      props: {
        analysis: makeAnalysis({
          findings: [makeFinding({ source_id: 1, match_state: 'matched' })],
        }),
      },
    })

    expect(wrapper.text()).toContain('No important gaps to address.')
  })

  it('keeps secondary metrics out of the primary summary', () => {
    const wrapper = mount(MatchSummaryHero, { props: { analysis: makeAnalysis() } })

    expect(wrapper.text()).not.toContain('Evidence coverage')
    expect(wrapper.text()).not.toContain('67%')
  })
})
