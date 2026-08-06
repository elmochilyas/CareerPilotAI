import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import MatchScoreDetails from '@/features/matching/components/MatchScoreDetails.vue'
import type { MatchAnalysis } from '@/features/matching/types'

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
    score_components: [
      {
        category: 'required_skills',
        weight: 0.5,
        score: 80,
        achieved_points: 80,
        total_points: 100,
        has_candidate_data: true,
      },
      {
        category: 'language_soft',
        weight: 0.05,
        score: 40,
        achieved_points: 40,
        total_points: 100,
        has_candidate_data: true,
      },
    ],
    findings: [],
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

describe('MatchScoreDetails', () => {
  it('is collapsed by default so scoring details stay out of the first view', () => {
    const wrapper = mount(MatchScoreDetails, { props: { analysis: makeAnalysis() } })

    expect(wrapper.find('#score-details').attributes('open')).toBeUndefined()
    expect(wrapper.text()).toContain('How this match was calculated')
  })

  it('reveals category meters, evidence coverage, and the updated date when expanded', async () => {
    const wrapper = mount(MatchScoreDetails, { props: { analysis: makeAnalysis() } })

    await wrapper.find('#score-details summary').trigger('click')

    expect(wrapper.find('#score-details').attributes('open')).toBeDefined()
    expect(wrapper.text()).toContain('Required skills')
    expect(wrapper.text()).toContain('80%')
    expect(wrapper.text()).toContain('Languages & soft skills')
    expect(wrapper.text()).toContain('Evidence coverage')
    expect(wrapper.text()).toContain('67%')
    expect(wrapper.text()).toContain('Analysis updated Jul 27, 2026')
  })

  it('reports when evidence coverage was not measured', async () => {
    const wrapper = mount(MatchScoreDetails, {
      props: { analysis: makeAnalysis({ evidence_coverage_score: null }) },
    })

    await wrapper.find('#score-details summary').trigger('click')

    expect(wrapper.text()).toContain('not measured')
  })

  it('shows a calm message when no category scores exist', async () => {
    const wrapper = mount(MatchScoreDetails, {
      props: { analysis: makeAnalysis({ score_components: [] }) },
    })

    await wrapper.find('#score-details summary').trigger('click')

    expect(wrapper.text()).toContain('No category scores are available for this analysis.')
  })
})
