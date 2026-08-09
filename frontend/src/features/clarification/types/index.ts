export type ClarificationAnswerType =
  | 'yes'
  | 'no'
  | 'no_with_ack'
  | 'text'
  | 'select_option'
  | 'number'

export type ClarificationQuestionType =
  | 'yes_no'
  | 'yes_no_with_details'
  | 'text'
  | 'select'
  | 'number'

export type ClarificationQuestionStatus = 'pending' | 'answered' | 'skipped' | 'expired'

export type ClarificationAnswerStatus = 'pending' | 'reviewed' | 'accepted' | 'skipped' | 'expired'

export type ClarificationProposalStatus = 'proposed' | 'accepted' | 'rejected' | 'skipped'

export type ClarificationReviewDecision = 'accept' | 'edit' | 'reject' | 'skip'

export interface ClarificationRequirement {
  text: string
  label: string | null
}

export interface ClarificationProgress {
  answered: number
  total: number
}

export interface ClarificationProposalTarget {
  type: string
  id: number | null
}

export interface ClarificationProposal {
  id: number
  answer_id: number
  target: ClarificationProposalTarget
  field: string
  before_value: unknown
  after_value: unknown
  status: ClarificationProposalStatus
}

export interface ClarificationAnswer {
  id: number
  question_id: number
  answer_type: ClarificationAnswerType
  value: string
  acknowledged_no_evidence: boolean
  status: ClarificationAnswerStatus
  proposal: ClarificationProposal | null
}

export interface ClarificationQuestion {
  id: number
  question_no: number
  question_type: ClarificationQuestionType
  prompt: string
  detail: string | null
  template_key: string
  options: string[] | null
  unit: string | null
  status: ClarificationQuestionStatus
  requirement: ClarificationRequirement | null
  answer: ClarificationAnswer | null
}

export interface ClarificationSession {
  analysis_id: number
  questions: ClarificationQuestion[]
  progress: ClarificationProgress
  generable_count: number
}

export interface ClarificationAnswerInput {
  answer_type: ClarificationAnswerType
  value: string
  acknowledged_no_evidence: boolean
}

export interface ClarificationReviewInput {
  decision?: ClarificationReviewDecision | null
  edited_value?: string | null
}

export interface ClarificationSkipped {
  id: number
  status: string
}
