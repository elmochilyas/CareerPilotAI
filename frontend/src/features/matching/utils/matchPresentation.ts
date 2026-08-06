import type { MatchCategory, MatchFinding, MatchImportance, MatchState } from '../types'

export type AlignmentTier = 'strong' | 'moderate' | 'weak'

export function alignmentTier(score: number): AlignmentTier {
  if (score >= 75) return 'strong'
  if (score >= 50) return 'moderate'
  return 'weak'
}

export function alignmentLabel(score: number): string {
  const labels: Record<AlignmentTier, string> = {
    strong: 'Good match',
    moderate: 'Moderate match',
    weak: 'Low match',
  }

  return labels[alignmentTier(score)]
}

export const categoryLabels: Record<MatchCategory, string> = {
  required_skills: 'Required skills',
  preferred_skills: 'Preferred skills',
  evidence: 'Evidence',
  experience_education: 'Experience & education',
  language_soft: 'Languages & soft skills',
}

export function categoryTagLabel(category: MatchCategory | null): string {
  switch (category) {
    case 'required_skills':
    case 'preferred_skills':
      return 'skill'
    case 'language_soft':
      return 'language'
    case 'experience_education':
      return 'experience & education'
    case 'evidence':
      return 'evidence'
    default:
      return 'requirement'
  }
}

export const stateLabels: Record<MatchState, string> = {
  matched: 'Matched',
  partial: 'Partial',
  gap: 'Gap',
  unknown: 'Unknown',
}

export function importanceLabel(importance: MatchImportance): string {
  return importance === 'required' ? 'Required' : 'Preferred'
}

export function requirementKind(finding: MatchFinding): string {
  return `${importanceLabel(finding.importance)} ${categoryTagLabel(finding.category)}`
}

export function labelOf(finding: MatchFinding): string {
  return finding.requirement_label ?? finding.requirement_text
}

export interface ImportantCounts {
  matched: number
  total: number
  gaps: number
}

export function countImportant(findings: MatchFinding[]): ImportantCounts {
  let matched = 0
  let gaps = 0
  let total = 0

  for (const finding of findings) {
    if (finding.importance !== 'required') continue
    total += 1
    if (finding.match_state === 'matched') matched += 1
    if (finding.match_state === 'gap') gaps += 1
  }

  return { matched, total, gaps }
}

export function importantSentence(counts: ImportantCounts): string {
  const plural = counts.total === 1 ? 'requirement' : 'requirements'
  return `You match ${counts.matched} of ${counts.total} important ${plural}.`
}

export function gapSentence(gaps: number): string {
  if (gaps === 0) return 'No important gaps to address.'
  return `${gaps} important gap${gaps === 1 ? '' : 's'} need${gaps === 1 ? 's' : ''} attention.`
}

const GAP_CATEGORY_RANK: Record<string, number> = {
  required_skills: 0,
  language_soft: 1,
  experience_education: 2,
  evidence: 3,
  preferred_skills: 4,
}

export function gapPriority(finding: MatchFinding): number {
  const importanceRank = finding.importance === 'required' ? 0 : 10
  const categoryRank = finding.category === null ? 99 : (GAP_CATEGORY_RANK[finding.category] ?? 99)
  return importanceRank * 1000 + categoryRank * 100 + finding.display_order
}

export function strengthsOf(findings: MatchFinding[]): MatchFinding[] {
  return findings
    .filter((finding) => finding.match_state === 'matched')
    .sort((a, b) => a.display_order - b.display_order)
}

export function gapsOf(findings: MatchFinding[]): MatchFinding[] {
  return findings
    .filter((finding) => finding.match_state === 'gap')
    .sort((a, b) => gapPriority(a) - gapPriority(b))
}

export function countUnknown(findings: MatchFinding[]): number {
  return findings.filter((finding) => finding.match_state === 'unknown').length
}

export function collapsedSummary(finding: MatchFinding): string {
  switch (finding.match_state) {
    case 'matched':
      return 'Matches your profile.'
    case 'partial':
      return 'Partially covered in your profile.'
    case 'gap':
      return 'No matching profile evidence found.'
    case 'unknown':
      return "Couldn't be verified automatically."
  }
}

export function humanReason(finding: MatchFinding): string {
  if (finding.justification) return finding.justification

  switch (finding.match_state) {
    case 'matched':
      return 'This matches your profile.'
    case 'partial':
      return 'Your profile covers this only in part.'
    case 'unknown':
      return "We couldn't verify this requirement automatically."
    case 'gap':
      if (finding.category === 'required_skills' || finding.category === 'preferred_skills') {
        return 'This skill is required by the job but is not present in your profile.'
      }
      if (finding.category === 'language_soft') {
        return 'This language requirement is not present in your profile.'
      }
      return 'This requirement is not met by your profile.'
  }
}

export function evidenceEmptyMessage(finding: MatchFinding): string {
  if (finding.match_state === 'gap') {
    if (finding.category === 'required_skills' || finding.category === 'preferred_skills') {
      return 'No trusted skill evidence found in your profile.'
    }
    if (finding.category === 'language_soft') {
      return 'No trusted language evidence found in your profile.'
    }
  }

  return 'No trusted candidate evidence found.'
}

export function findingKey(finding: MatchFinding): string {
  return `${finding.source_type}:${finding.source_id}:${finding.display_order}`
}
