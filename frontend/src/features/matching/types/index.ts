export type MatchAnalysisStatus = 'queued' | 'processing' | 'completed' | 'failed'

export type MatchState = 'matched' | 'partial' | 'gap' | 'unknown'

export type MatchImportance = 'required' | 'preferred'

export type MatchCategory =
  | 'required_skills'
  | 'preferred_skills'
  | 'evidence'
  | 'experience_education'
  | 'language_soft'

export type RequirementSourceType = 'job_requirement' | 'job_opportunity_skill'

export interface MatchCounts {
  required: number
  preferred: number
  matched: number
  partial: number
  gap: number
  unknown: number
}

export interface MatchVersions {
  algorithm: string
  scoring: string
  classifier_schema: string
}

export interface MatchFingerprints {
  profile: string
  opportunity: string
}

export interface MatchFailure {
  code: string | null
  reason: string | null
}

export interface MatchClassifier {
  provider: string | null
  model: string | null
  prompt_version: string | null
  latency_ms: number | null
  tokens_prompt: number | null
  tokens_completion: number | null
  response_id: string | null
  status: string | null
}

export interface MatchTimestamps {
  queued_at: string | null
  processing_started_at: string | null
  completed_at: string | null
  failed_at: string | null
  created_at: string
  updated_at: string
}

export interface MatchScoreComponent {
  category: MatchCategory
  weight: number
  score: number
  achieved_points: number
  total_points: number
  has_candidate_data: boolean
}

export interface MatchEvidenceRef {
  type: string
  id: number | null
  label: string | null
}

export interface MatchFinding {
  source_type: RequirementSourceType
  source_id: number
  requirement_text: string
  requirement_label: string | null
  importance: MatchImportance
  category: MatchCategory | null
  match_state: MatchState
  factor: number
  matched_candidate_skill_id: number | null
  evidence_refs: MatchEvidenceRef[]
  justification: string | null
  confidence: string | null
  classifier_source: string | null
  display_order: number
}

export interface MatchAnalysis {
  id: number
  candidate_profile_id: number
  job_opportunity_id: number
  status: MatchAnalysisStatus
  overall_score: number | null
  evidence_coverage_score: number | null
  counts: MatchCounts
  versions: MatchVersions
  fingerprints: MatchFingerprints
  stale: boolean
  latest: boolean
  warnings: string[]
  failure: MatchFailure
  classifier: MatchClassifier
  request_id: string | null
  score_components: MatchScoreComponent[]
  findings: MatchFinding[]
  timestamps: MatchTimestamps
}

export interface MatchOperation {
  id: number
  status: MatchAnalysisStatus
  candidate_profile_id: number
  job_opportunity_id: number
  request_id: string | null
  failure_code: string | null
  failure_reason: string | null
  queued_at: string | null
  processing_started_at: string | null
  completed_at: string | null
  failed_at: string | null
}

export interface MatchListMeta {
  path: string
  per_page: number
  next_cursor: string | null
  prev_cursor: string | null
}

export interface MatchListResult {
  data: MatchAnalysis[]
  meta: MatchListMeta
}
