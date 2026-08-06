import { describe, expect, it } from 'vitest'
import {
  alignmentLabel,
  alignmentTier,
  categoryLabels,
  categoryTagLabel,
  collapsedSummary,
  countImportant,
  countUnknown,
  evidenceEmptyMessage,
  findingKey,
  gapPriority,
  gapSentence,
  gapsOf,
  humanReason,
  importantSentence,
  labelOf,
  requirementKind,
  stateLabels,
  strengthsOf,
} from '@/features/matching/utils/matchPresentation'
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
    display_order: 3,
    ...overrides,
  }
}

describe('matchPresentation helpers', () => {
  it('classifies alignment tiers and labels with human language', () => {
    expect(alignmentTier(84)).toBe('strong')
    expect(alignmentTier(60)).toBe('moderate')
    expect(alignmentTier(30)).toBe('weak')
    expect(alignmentLabel(84)).toBe('Good match')
    expect(alignmentLabel(60)).toBe('Moderate match')
    expect(alignmentLabel(30)).toBe('Low match')
  })

  it('labels every match category', () => {
    expect(categoryLabels.required_skills).toBe('Required skills')
    expect(categoryLabels.preferred_skills).toBe('Preferred skills')
    expect(categoryLabels.evidence).toBe('Evidence')
    expect(categoryLabels.experience_education).toBe('Experience & education')
    expect(categoryLabels.language_soft).toBe('Languages & soft skills')
  })

  it('maps categories to short requirement kinds', () => {
    expect(categoryTagLabel('required_skills')).toBe('skill')
    expect(categoryTagLabel('preferred_skills')).toBe('skill')
    expect(categoryTagLabel('language_soft')).toBe('language')
    expect(categoryTagLabel('experience_education')).toBe('experience & education')
    expect(categoryTagLabel('evidence')).toBe('evidence')
    expect(categoryTagLabel(null)).toBe('requirement')
    expect(requirementKind(makeFinding())).toBe('Required language')
    expect(
      requirementKind(makeFinding({ category: 'required_skills', importance: 'preferred' })),
    ).toBe('Preferred skill')
  })

  it('labels every match state', () => {
    expect(stateLabels.matched).toBe('Matched')
    expect(stateLabels.partial).toBe('Partial')
    expect(stateLabels.gap).toBe('Gap')
    expect(stateLabels.unknown).toBe('Unknown')
  })

  it('prefers requirement labels when present', () => {
    expect(labelOf(makeFinding())).toBe('Fluent English')
    expect(labelOf(makeFinding({ requirement_label: 'English' }))).toBe('English')
  })

  it('counts important requirements and builds the summary sentence', () => {
    const findings = [
      makeFinding({ match_state: 'matched' }),
      makeFinding({ source_id: 2, match_state: 'gap' }),
      makeFinding({ source_id: 3, importance: 'preferred', match_state: 'matched' }),
      makeFinding({ source_id: 4, match_state: 'unknown' }),
    ]

    expect(countImportant(findings)).toEqual({ matched: 1, total: 3, gaps: 1 })
    expect(importantSentence({ matched: 1, total: 3, gaps: 1 })).toBe(
      'You match 1 of 3 important requirements.',
    )
    expect(importantSentence({ matched: 1, total: 1, gaps: 0 })).toBe(
      'You match 1 of 1 important requirement.',
    )
  })

  it('describes the gap count in human terms', () => {
    expect(gapSentence(3)).toBe('3 important gaps need attention.')
    expect(gapSentence(1)).toBe('1 important gap needs attention.')
    expect(gapSentence(0)).toBe('No important gaps to address.')
  })

  it('prioritizes required gaps over preferred, then by category', () => {
    const requiredSkill = makeFinding({ category: 'required_skills', display_order: 9 })
    const requiredLanguage = makeFinding({ category: 'language_soft', display_order: 1 })
    const preferredSkill = makeFinding({
      category: 'preferred_skills',
      importance: 'preferred',
      display_order: 0,
    })

    expect(gapPriority(requiredSkill)).toBeLessThan(gapPriority(requiredLanguage))
    expect(gapPriority(requiredLanguage)).toBeLessThan(gapPriority(preferredSkill))
  })

  it('separates strengths and gaps, keeping engine states out of the summary lists', () => {
    const matched = makeFinding({ match_state: 'matched', display_order: 2 })
    const partial = makeFinding({ source_id: 2, match_state: 'partial' })
    const gap = makeFinding({ source_id: 3, match_state: 'gap', display_order: 1 })
    const unknown = makeFinding({ source_id: 4, match_state: 'unknown' })
    const findings = [partial, gap, matched, unknown]

    expect(strengthsOf(findings).map((finding) => finding.source_id)).toEqual([matched.source_id])
    expect(gapsOf(findings).map((finding) => finding.source_id)).toEqual([gap.source_id])
    expect(countUnknown(findings)).toBe(1)
  })

  it('explains missing evidence per category and state', () => {
    expect(
      evidenceEmptyMessage(makeFinding({ match_state: 'gap', category: 'required_skills' })),
    ).toBe('No trusted skill evidence found in your profile.')
    expect(
      evidenceEmptyMessage(makeFinding({ match_state: 'gap', category: 'preferred_skills' })),
    ).toBe('No trusted skill evidence found in your profile.')
    expect(
      evidenceEmptyMessage(makeFinding({ match_state: 'gap', category: 'language_soft' })),
    ).toBe('No trusted language evidence found in your profile.')
    expect(
      evidenceEmptyMessage(makeFinding({ match_state: 'matched', category: 'required_skills' })),
    ).toBe('No trusted candidate evidence found.')
  })

  it('gives a one-line collapsed summary per state', () => {
    expect(collapsedSummary(makeFinding({ match_state: 'matched' }))).toBe('Matches your profile.')
    expect(collapsedSummary(makeFinding({ match_state: 'partial' }))).toBe(
      'Partially covered in your profile.',
    )
    expect(collapsedSummary(makeFinding({ match_state: 'gap' }))).toBe(
      'No matching profile evidence found.',
    )
    expect(collapsedSummary(makeFinding({ match_state: 'unknown' }))).toBe(
      "Couldn't be verified automatically.",
    )
  })

  it('explains the reason in human terms, preferring stored justification', () => {
    expect(humanReason(makeFinding({ match_state: 'gap', category: 'required_skills' }))).toBe(
      'This skill is required by the job but is not present in your profile.',
    )
    expect(humanReason(makeFinding({ match_state: 'gap', category: 'language_soft' }))).toBe(
      'This language requirement is not present in your profile.',
    )
    expect(humanReason(makeFinding({ match_state: 'matched' }))).toBe('This matches your profile.')
    expect(humanReason(makeFinding({ match_state: 'unknown' }))).toBe(
      "We couldn't verify this requirement automatically.",
    )
    expect(
      humanReason(makeFinding({ match_state: 'gap', justification: 'No certification found.' })),
    ).toBe('No certification found.')
  })

  it('builds stable finding keys', () => {
    expect(findingKey(makeFinding())).toBe('job_requirement:1:3')
  })
})
