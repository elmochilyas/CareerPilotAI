import type { SuggestionType } from '../types'

export const SUGGESTION_VALUE_FIELDS: Record<
  SuggestionType,
  string | ((ev: Record<string, unknown>) => string)
> = {
  job_title: 'value',
  company: 'value',
  department: 'value',
  external_reference: 'value',
  summary: 'value',
  application_url: 'value',
  city: 'value',
  region: 'value',
  country: 'value',
  work_mode: 'value',
  contract_type: 'value',
  seniority_level: 'value',
  working_hours: 'value',
  travel_required: (ev) => (ev.value ? 'Yes' : 'No'),
  relocation_required: (ev) => (ev.value ? 'Yes' : 'No'),
  responsibility: 'text',
  required_experience: (ev) => String(ev.summary ?? ev.years ?? ''),
  preferred_experience: (ev) => String(ev.summary ?? ev.years ?? ''),
  education: (ev) => String(ev.degree ?? ev.field ?? ''),
  required_skill: 'label',
  preferred_skill: 'label',
  language: (ev) => {
    const lang = String(ev.language ?? '')
    const prof = String(ev.proficiency ?? '')
    return prof ? `${lang} (${prof})` : lang
  },
  certification: 'name',
  compensation: (ev) => {
    const text = String(ev.text ?? '').trim()
    const currency = String(ev.currency ?? '').trim()
    const min = ev.salary_min
    const max = ev.salary_max
    const period = String(ev.period ?? '').trim()

    if (text) return text

    if (min && max) {
      const formattedMin = Number(min).toLocaleString()
      const formattedMax = Number(max).toLocaleString()
      const range = `${currency}${formattedMin}–${currency}${formattedMax}`
      return period ? `${range} per ${period}` : range
    }

    if (max) {
      const formattedMax = Number(max).toLocaleString()
      const prefix = min ? `${currency}${Number(min).toLocaleString()}–` : `Up to ${currency}`
      const value = min ? `${prefix}${formattedMax}` : `${prefix}${formattedMax}`
      return period ? `${value} per ${period}` : value
    }

    if (min) {
      const formattedMin = Number(min).toLocaleString()
      const value = `From ${currency}${formattedMin}`
      return period ? `${value} per ${period}` : value
    }

    return ''
  },
  benefit: 'name',
  publication_date: 'value',
  application_deadline: 'value',
  expected_start_date: 'value',
  employment_duration: 'value',
  additional_requirement: 'text',
}

export function getSuggestionField(suggestion: {
  type: string
  extracted_value: Record<string, unknown>
  edited_value: Record<string, unknown> | null
  review_decision: string
}): string {
  if (suggestion.review_decision === 'rejected' || suggestion.review_decision === 'keep_blank') {
    return 'EXCLUDED'
  }

  const formatter = SUGGESTION_VALUE_FIELDS[suggestion.type as SuggestionType]
  if (formatter === undefined) return ''

  if (suggestion.review_decision === 'edited' && suggestion.edited_value) {
    if (typeof formatter === 'string') {
      return String(suggestion.edited_value[formatter] ?? '')
    }
    return String(formatter(suggestion.edited_value))
  }

  if (typeof formatter === 'string') {
    return String(suggestion.extracted_value[formatter] ?? '')
  }

  return String(formatter(suggestion.extracted_value))
}

const SUGGESTION_LABELS: Record<string, string> = {
  job_title: 'Job Title',
  company: 'Company',
  department: 'Department',
  external_reference: 'Reference',
  summary: 'Summary',
  application_url: 'Application URL',
  city: 'City',
  region: 'Region',
  country: 'Country',
  work_mode: 'Work Mode',
  contract_type: 'Contract Type',
  seniority_level: 'Seniority Level',
  working_hours: 'Working Hours',
  travel_required: 'Travel Required',
  relocation_required: 'Relocation Required',
  responsibility: 'Responsibility',
  required_experience: 'Required Experience',
  preferred_experience: 'Preferred Experience',
  education: 'Education',
  required_skill: 'Required Skill',
  preferred_skill: 'Preferred Skill',
  language: 'Language',
  certification: 'Certification',
  compensation: 'Compensation',
  benefit: 'Benefit',
  publication_date: 'Publication Date',
  application_deadline: 'Application Deadline',
  expected_start_date: 'Expected Start Date',
  employment_duration: 'Employment Duration',
  additional_requirement: 'Additional Requirement',
}

export function suggestionLabel(type: string): string {
  return SUGGESTION_LABELS[type] ?? type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

export function isSuggestionDisplayable(suggestion: {
  type: string
  extracted_value: Record<string, unknown>
  edited_value: Record<string, unknown> | null
  review_decision: string
}): boolean {
  if (suggestion.review_decision === 'rejected' || suggestion.review_decision === 'keep_blank') {
    return false
  }

  const formatter = SUGGESTION_VALUE_FIELDS[suggestion.type as SuggestionType]
  if (formatter === undefined) return false

  return getSuggestionField(suggestion) !== ''
}
