export type ProfileItemType = 'education' | 'experience' | 'project' | 'certification'
export type AvailabilityStatus = 'immediately' | 'within_2_weeks' | 'within_month' | 'not_looking'
export type WorkMode = 'remote' | 'hybrid' | 'on_site'
export type ContractType = 'full-time' | 'part-time' | 'contract' | 'internship' | 'freelance'
export type LanguageProficiency = 'native' | 'fluent' | 'advanced' | 'intermediate' | 'basic'

export interface ProfileItem {
  id: number
  type: ProfileItemType
  title: string
  organization: string | null
  location: string | null
  start_date: string | null
  end_date: string | null
  description: string | null
  metadata: Record<string, unknown> | null
  display_order: number
  created_at: string
  updated_at: string
}

export interface CompletionArea {
  key: string
  earned: number
  available: number
  complete: boolean
  guidance: string | null
}

export interface CandidateProfile {
  id: number | null
  full_name: string
  headline: string | null
  professional_summary: string | null
  phone: string | null
  city: string | null
  country: string | null
  linkedin_url: string | null
  github_url: string | null
  portfolio_url: string | null
  availability_status: AvailabilityStatus | null
  availability_date: string | null
  target_roles: string[]
  preferred_locations: string[]
  work_mode: string | null
  work_modes: string[]
  contract_types: string[]
  salary_min: string | null
  salary_max: string | null
  salary_currency: string | null
  salary_period: string | null
  languages: Array<{ language: string; proficiency: string }>
  profile_completion: number
  completion_details: {
    areas: CompletionArea[]
    missing_areas: Array<{ key: string; guidance: string }>
  }
  items: Record<ProfileItemType, ProfileItem[]>
  created_at: string | null
  updated_at: string | null
}

export type ProfileUpdate = Partial<
  Omit<
    CandidateProfile,
    | 'id'
    | 'full_name'
    | 'profile_completion'
    | 'completion_details'
    | 'items'
    | 'created_at'
    | 'work_mode'
  >
>
export type ProfileItemInput = Partial<Omit<ProfileItem, 'id' | 'created_at' | 'display_order'>> & {
  type: ProfileItemType
  title: string
  is_current?: boolean
}
export interface ApiData<T> {
  data: T
}
