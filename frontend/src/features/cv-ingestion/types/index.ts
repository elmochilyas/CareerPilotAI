export type CvDocumentStatus =
  | 'pending'
  | 'queued'
  | 'validating'
  | 'extracting'
  | 'analyzing'
  | 'ready_for_review'
  | 'importing'
  | 'imported'
  | 'failed'
  | 'deleted'

export type SuggestionType =
  | 'basic_information'
  | 'headline'
  | 'summary'
  | 'social_link'
  | 'experience'
  | 'education'
  | 'project'
  | 'certification'
  | 'language'
  | 'skill'

export type ReviewStatus =
  | 'pending'
  | 'accepted'
  | 'edited'
  | 'rejected'
  | 'keep_existing'
  | 'imported'
  | 'import_failed'

export type ImportBatchStatus = 'pending' | 'applied' | 'failed' | 'partially_applied'

export type ExtractionMethod = 'deterministic_parser' | 'ai_extraction'

export interface BasicInfoValue {
  value: string
}

export interface SocialLinkValue {
  type: 'linkedin' | 'github' | 'portfolio' | 'other'
  url: string
}

export interface ExperienceValue {
  title: string
  organization: string | null
  location: string | null
  start_date: string | null
  end_date: string | null
  is_current: boolean
  description: string | null
  technologies: string[]
}

export interface ProjectValue {
  name: string
  role: string | null
  description: string | null
  technologies: string[]
  url: string | null
  start_date: string | null
  end_date: string | null
  is_current: boolean
}

export interface EducationValue {
  degree: string
  field_of_study: string | null
  institution: string
  location: string | null
  start_date: string | null
  end_date: string | null
  is_current: boolean
  description: string | null
}

export interface CertificationValue {
  name: string
  issuer: string | null
  date: string | null
  url: string | null
  description: string | null
}

export interface LanguageValue {
  language: string
  proficiency: string | null
}

export interface SkillValue {
  name: string
  category: string | null
}

export type SuggestedValue =
  | BasicInfoValue
  | SocialLinkValue
  | ExperienceValue
  | ProjectValue
  | EducationValue
  | CertificationValue
  | LanguageValue
  | SkillValue

export type SuggestedValueByType<T extends SuggestionType> = T extends 'basic_information'
  ? BasicInfoValue
  : T extends 'headline'
    ? BasicInfoValue
    : T extends 'summary'
      ? BasicInfoValue
      : T extends 'social_link'
        ? SocialLinkValue
        : T extends 'experience'
          ? ExperienceValue
          : T extends 'project'
            ? ProjectValue
            : T extends 'education'
              ? EducationValue
              : T extends 'certification'
                ? CertificationValue
                : T extends 'language'
                  ? LanguageValue
                  : T extends 'skill'
                    ? SkillValue
                    : never

export interface CvDocument {
  id: number
  original_name: string
  mime_type: string
  size: number
  status: CvDocumentStatus
  failure_reason: string | null
  failure_code: string | null
  metadata: Record<string, unknown> | null
  created_at: string
  updated_at: string
  latest_run: CvProcessingRun | null
}

export interface CvProcessingRun {
  id: number
  cv_document_id: number
  status: string
  pipeline_version: string
  started_at: string | null
  completed_at: string | null
  failure_reason: string | null
  failure_code: string | null
  ai_provider: string | null
  ai_model: string | null
  created_at: string
}

export interface CvSuggestion<T extends SuggestionType = SuggestionType> {
  id: number
  cv_document_id: number
  type: T
  category: string | null
  field_name: string | null
  current_value: Record<string, unknown> | null
  suggested_value: SuggestedValueByType<T>
  source_page: number | null
  source_text: string | null
  extraction_method: ExtractionMethod
  confidence: number | null
  review_status: ReviewStatus
  reviewed_decision: Record<string, unknown> | null
  reviewed_at: string | null
}

export interface CvImportBatch {
  id: number
  cv_document_id: number
  status: ImportBatchStatus
  idempotency_key: string | null
  imported_at: string | null
  failure_reason: string | null
  failure_code: string | null
  summary: {
    total_accepted: number
    fields_updated: number
    items_created: number
    items_updated: number
    skills_added: number
    skipped: number
    errors: number
  } | null
  created_at: string
}

export interface ImportPreview {
  summary: {
    total: number
    accepted: number
    rejected: number
    keep_existing: number
    edited: number
  }
  conflicts: Array<{
    type: string
    field: string
    current: unknown
    suggested: unknown
    message: string
  }>
  profile_updated_at: string | null
}

export interface ReviewDecision {
  decision: 'accepted' | 'edited' | 'rejected' | 'keep_existing' | 'create_new' | 'update_existing'
  edited_value?: Record<string, unknown>
}

export interface ApiData<T> {
  data: T
}

export const FAILURE_MESSAGES = {
  file_not_found: "We couldn't access the uploaded file. Please try uploading again.",
  file_no_text:
    'We could not read any text from this file. Make sure it is a text-based PDF or DOCX, not a scanned image.',
  ai_schema_validation_failed:
    'Our AI had trouble processing this CV. You can try again or upload a different version.',
  analysis_error: 'An error occurred during analysis. Please try again.',
  extraction_error: 'An error occurred while reading your document. Please try again.',
  pipeline_error: 'Something went wrong during processing. Please try again.',
  import_failed: 'Import failed. Please try again.',
  document_not_retryable: 'This document cannot be retried. Please upload a new CV.',
  unknown: 'An unexpected error occurred. Please try again.',
} as const
