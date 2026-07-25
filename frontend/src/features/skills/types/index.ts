export type SkillState = 'claimed' | 'verified' | 'learning' | 'rejected' | 'archived'

export type ProficiencyLevel = 'beginner' | 'elementary' | 'intermediate' | 'advanced' | 'expert'

export type EvidenceType = 'profile_item' | 'url' | 'text'

export interface Skill {
  id: number
  name: string
  normalized_name: string
  category: string | null
  is_active: boolean
  aliases: string[]
  created_at: string
  updated_at: string
}

export interface SkillEvidenceEntry {
  key: string
  type: EvidenceType
  value: string
  label: string | null
  profile_item_snapshot?: {
    title: string
    type: string
    organization: string | null
  }
}

export interface CandidateSkill {
  id: number
  skill: Skill | null
  is_custom: boolean
  custom_skill_name: string | null
  state: SkillState
  proficiency_level: ProficiencyLevel
  years_experience: number | null
  last_used_at: string | null
  evidence: SkillEvidenceEntry[]
  verification_at_risk: boolean
  created_at: string
  updated_at: string
}

export interface CandidateSkillInput {
  skill_id?: number | null
  custom_skill_name?: string | null
  state: SkillState
  proficiency_level: ProficiencyLevel
  years_experience?: number | null
  last_used_at?: string | null
}

export interface CandidateSkillUpdate {
  state?: SkillState
  proficiency_level?: ProficiencyLevel
  years_experience?: number | null
  last_used_at?: string | null
  updated_at?: string
}

export interface EvidenceInput {
  type: EvidenceType
  value: string
  label?: string | null
}

export interface ApiData<T> {
  data: T
}
