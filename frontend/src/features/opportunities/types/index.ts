export type IngestionStatus =
  | 'draft'
  | 'queued'
  | 'processing'
  | 'review_ready'
  | 'confirmed'
  | 'failed'
  | 'cancelled'

export type ReviewDecisionValue =
  | 'pending'
  | 'accepted'
  | 'edited'
  | 'rejected'
  | 'keep_blank'
  | 'resolved'

export type SkillResolution = 'exact' | 'alias' | 'ambiguous' | 'unknown' | 'candidate_resolved'

export type SuggestionType =
  | 'job_title'
  | 'company'
  | 'department'
  | 'external_reference'
  | 'summary'
  | 'application_url'
  | 'city'
  | 'region'
  | 'country'
  | 'work_mode'
  | 'contract_type'
  | 'seniority_level'
  | 'working_hours'
  | 'travel_required'
  | 'relocation_required'
  | 'responsibility'
  | 'required_experience'
  | 'preferred_experience'
  | 'education'
  | 'required_skill'
  | 'preferred_skill'
  | 'language'
  | 'certification'
  | 'compensation'
  | 'benefit'
  | 'publication_date'
  | 'application_deadline'
  | 'expected_start_date'
  | 'employment_duration'
  | 'additional_requirement'

export interface JobIngestion {
  id: number
  status: IngestionStatus
  source_url: string | null
  personal_label: string | null
  failure_reason: string | null
  failure_code: string | null
  retry_count: number
  last_retry_at: string | null
  confirmed_at: string | null
  version: number
  confirmed_opportunity_id: number | null
  created_at: string
  updated_at: string
}

export interface JobSuggestion {
  id: number
  ingestion_id: number
  type: SuggestionType
  group_key: string | null
  field: string | null
  extracted_value: Record<string, unknown>
  edited_value: Record<string, unknown> | null
  review_decision: ReviewDecisionValue
  source_evidence: string | null
  schema_version: string
  resolution: SkillResolution | null
  resolved_skill_id: number | null
  reviewed_at: string | null
  version: number
  created_at: string
  updated_at: string
}

export interface JobRequirement {
  id: number
  category: string
  content: string
  classification: string | null
  language: string | null
  language_proficiency: string | null
  display_order: number
}

export interface JobOpportunitySkill {
  id: number
  skill_id: number | null
  original_label: string
  classification: string
  proficiency: string | null
  years_experience: number | null
  source_evidence: string | null
  display_order: number
}

export interface JobOpportunity {
  id: number
  title: string
  company_name: string | null
  department: string | null
  external_reference: string | null
  summary: string | null
  application_url: string | null
  personal_label: string | null
  source_url: string | null
  city: string | null
  region: string | null
  country: string | null
  work_mode: string | null
  contract_type: string | null
  seniority_level: string | null
  working_hours: string | null
  travel_required: boolean | null
  relocation_required: boolean | null
  salary_min: number | null
  salary_max: number | null
  salary_currency: string | null
  salary_period: string | null
  compensation_text: string | null
  benefits: string[] | null
  publication_date: string | null
  application_deadline: string | null
  expected_start_date: string | null
  employment_duration: string | null
  additional_requirements: string[] | null
  requirements: JobRequirement[]
  skills: JobOpportunitySkill[]
  company: { id: number; name: string } | null
  saved_at: string
  created_at: string
  updated_at: string
}

export interface PreviewData {
  data: {
    overview: Record<string, string>
    work_details: Record<string, unknown>
    responsibilities: Array<{ id: number; text: string; source_evidence: string | null }>
    required_experience: Array<{ id: number; summary: string; years: number | null }>
    preferred_experience: Array<{ id: number; summary: string; years: number | null }>
    education: Array<{ id: number; degree: string; field: string | null; required: boolean | null }>
    required_skills: Array<{
      id: number
      label: string
      proficiency: string | null
      years_experience: number | null
      resolution: string
    }>
    preferred_skills: Array<{
      id: number
      label: string
      proficiency: string | null
      years_experience: number | null
      resolution: string
    }>
    languages_certifications: Array<{
      id: number
      type: string
      name: string
      required: boolean | null
      proficiency: string | null
    }>
    compensation: Record<string, unknown> | null
    dates: Record<string, string | null>
    excluded: Array<{ id: number; type: string }>
    unknown_skills: Array<{ id: number; type: string }>
    warnings: string[]
  }
  version_token: string
  schema_version: string
}

export interface DecisionInput {
  id: number
  decision: ReviewDecisionValue
  edited_value?: Record<string, unknown>
}

export interface ApiMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}
