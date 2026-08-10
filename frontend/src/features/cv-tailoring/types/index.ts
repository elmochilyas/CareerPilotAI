export type ResumeStatus = 'draft' | 'approved'

export type TailoringProposalStatus = 'proposed' | 'accepted' | 'rejected'

export type TailoringChangeType = 'reword' | 'reorder' | 'include' | 'exclude'

export type TailoringRelevance = 'high' | 'medium' | 'low' | 'excluded'

export type StalenessStatus = 'fresh' | 'stale' | 'expired'

export type WorkspaceStep = 'generate' | 'review' | 'export'

export interface ResumeItem {
  source_ref: string
  original_text: string
  current_text: string
  ai_proposals: TailoringProposal[]
  metadata?: Record<string, unknown>
}

export interface ResumeSection {
  type: string
  title: string
  items: ResumeItem[]
  has_changes: boolean
}

export interface ResumeContent {
  sections: ResumeSection[]
  summary: string | null
  headline: string | null
  proposals?: TailoringProposal[]
}

export interface TailoringProposal {
  id: number
  source_ref: string
  original_text: string
  proposed_text: string
  change_type: TailoringChangeType
  status: TailoringProposalStatus
  edited_text: string | null
  accepted_at: string | null
}

export interface Resume {
  id: number
  candidate_profile_id: number
  opportunity_id: number
  title: string
  template_key: string | null
  content: ResumeContent
  status: ResumeStatus
  generated_by: string
  stale: boolean
  stale_reason: string | null
  version_no: number
  approved_at: string | null
  created_at: string
  updated_at: string
}

export interface ResumeListItem {
  id: number
  title: string
  status: ResumeStatus
  version_no: number
  stale: boolean
  created_at: string
}

export interface CreateResumeInput {
  title?: string
  template_key?: string
}

export interface UpdateResumeInput {
  content?: ResumeContent
  proposal_decisions?: Array<{
    id: number
    status: TailoringProposalStatus
    edited_text?: string | null
  }>
  title?: string
  template_key?: string
}

export interface TailorResumeInput {
  use_ai?: boolean
}

export interface ResumePreview {
  sections: ResumeSection[]
  summary: string | null
  headline: string | null
}
