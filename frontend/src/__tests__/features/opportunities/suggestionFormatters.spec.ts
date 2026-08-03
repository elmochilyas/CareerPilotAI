import { describe, expect, it } from 'vitest'
import {
  getSuggestionField,
  isSuggestionDisplayable,
  SUGGESTION_VALUE_FIELDS,
} from '@/features/opportunities/utils/suggestionFormatters'

function sug(
  type: string,
  extracted_value: Record<string, unknown> = {},
  review_decision = 'pending',
  edited_value: Record<string, unknown> | null = null,
) {
  return { type, extracted_value, review_decision, edited_value }
}

describe('getSuggestionField', () => {
  it('returns value for scalar types', () => {
    expect(getSuggestionField(sug('job_title', { value: 'Developer' }))).toBe('Developer')
    expect(getSuggestionField(sug('company', { value: 'Acme' }))).toBe('Acme')
    expect(getSuggestionField(sug('summary', { value: 'A great role' }))).toBe('A great role')
    expect(getSuggestionField(sug('city', { value: 'Berlin' }))).toBe('Berlin')
    expect(getSuggestionField(sug('work_mode', { value: 'remote' }))).toBe('remote')
  })

  it('returns text for responsibility types', () => {
    expect(getSuggestionField(sug('responsibility', { text: 'Build APIs' }))).toBe('Build APIs')
  })

  it('returns label for skill types', () => {
    expect(getSuggestionField(sug('required_skill', { label: 'Node.js' }))).toBe('Node.js')
    expect(getSuggestionField(sug('preferred_skill', { label: 'TypeScript' }))).toBe('TypeScript')
  })

  it('returns language with proficiency when available', () => {
    expect(getSuggestionField(sug('language', { language: 'German', proficiency: 'C1' }))).toBe(
      'German (C1)',
    )
    expect(getSuggestionField(sug('language', { language: 'English' }))).toBe('English')
  })

  it('returns certification name', () => {
    expect(getSuggestionField(sug('certification', { name: 'AWS Certified' }))).toBe(
      'AWS Certified',
    )
  })

  it('returns benefit name', () => {
    expect(getSuggestionField(sug('benefit', { name: 'Remote work option' }))).toBe(
      'Remote work option',
    )
  })

  it('returns compensation original text when available', () => {
    expect(
      getSuggestionField(
        sug('compensation', {
          text: 'Attractive salary up to €110,000',
          currency: '€',
          salary_max: 110000,
          period: 'yearly',
        }),
      ),
    ).toBe('Attractive salary up to €110,000')
  })

  it('formats compensation with max only', () => {
    expect(
      getSuggestionField(
        sug('compensation', {
          currency: '€',
          salary_max: 110000,
          period: 'yearly',
        }),
      ),
    ).toBe('Up to €110,000 per yearly')
  })

  it('formats compensation with range', () => {
    expect(
      getSuggestionField(
        sug('compensation', {
          currency: '€',
          salary_min: 80000,
          salary_max: 110000,
          period: 'yearly',
        }),
      ),
    ).toBe('€80,000–€110,000 per yearly')
  })

  it('formats compensation with min only', () => {
    expect(
      getSuggestionField(
        sug('compensation', {
          currency: 'USD',
          salary_min: 80000,
          period: 'yearly',
        }),
      ),
    ).toBe('From USD80,000 per yearly')
  })

  it('formats compensation without period', () => {
    expect(
      getSuggestionField(
        sug('compensation', {
          currency: '€',
          salary_max: 110000,
        }),
      ),
    ).toBe('Up to €110,000')
  })

  it('returns empty string for compensation with no data', () => {
    expect(getSuggestionField(sug('compensation', {}))).toBe('')
  })

  it('returns additional requirement text', () => {
    expect(getSuggestionField(sug('additional_requirement', { text: 'Must know PHP' }))).toBe(
      'Must know PHP',
    )
  })

  it('returns boolean display for travel and relocation', () => {
    expect(getSuggestionField(sug('travel_required', { value: true }))).toBe('Yes')
    expect(getSuggestionField(sug('travel_required', { value: false }))).toBe('No')
    expect(getSuggestionField(sug('relocation_required', { value: true }))).toBe('Yes')
  })

  it('returns education degree or field', () => {
    expect(getSuggestionField(sug('education', { degree: 'Bachelor', field: 'CS' }))).toBe(
      'Bachelor',
    )
    expect(getSuggestionField(sug('education', { field: 'Computer Science' }))).toBe(
      'Computer Science',
    )
  })

  it('returns experience summary', () => {
    expect(getSuggestionField(sug('required_experience', { summary: '5 years in Node.js' }))).toBe(
      '5 years in Node.js',
    )
  })

  it('returns empty for rejected or keep_blank decisions', () => {
    expect(getSuggestionField(sug('job_title', { value: 'Dev' }, 'rejected'))).toBe('EXCLUDED')
    expect(getSuggestionField(sug('benefit', { name: 'Free coffee' }, 'keep_blank'))).toBe(
      'EXCLUDED',
    )
  })

  it('returns edited value when decision is edited', () => {
    expect(
      getSuggestionField(
        sug('job_title', { value: 'Original' }, 'edited', { value: 'Edited Title' }),
      ),
    ).toBe('Edited Title')
  })

  it('returns empty string for unsupported types', () => {
    expect(getSuggestionField(sug('unknown_type' as string, {}))).toBe('')
  })
})

describe('isSuggestionDisplayable', () => {
  it('returns true for pending suggestions with meaningful values', () => {
    expect(isSuggestionDisplayable(sug('job_title', { value: 'Dev' }))).toBe(true)
    expect(isSuggestionDisplayable(sug('benefit', { name: 'Free coffee' }))).toBe(true)
  })

  it('returns false for rejected or keep_blank decisions', () => {
    expect(isSuggestionDisplayable(sug('job_title', { value: 'Dev' }, 'rejected'))).toBe(false)
    expect(isSuggestionDisplayable(sug('benefit', { name: 'Free coffee' }, 'keep_blank'))).toBe(
      false,
    )
  })

  it('returns false for unsupported types', () => {
    expect(isSuggestionDisplayable(sug('unknown_type' as string, { value: 'x' }))).toBe(false)
  })

  it('returns false for empty meaningful values', () => {
    expect(isSuggestionDisplayable(sug('job_title', { value: '' }))).toBe(false)
    expect(isSuggestionDisplayable(sug('benefit', { name: '' }))).toBe(false)
    expect(isSuggestionDisplayable(sug('compensation', {}))).toBe(false)
  })
})

describe('controlled fallback for malformed values', () => {
  it('shows unsupported message for types with empty formatter result', () => {
    const field = getSuggestionField(sug('benefit', { name: '' }))
    const displayable = isSuggestionDisplayable(sug('benefit', { name: '' }))
    expect(field).toBe('')
    expect(displayable).toBe(false)
  })

  it('does not expose raw JSON', () => {
    const result = getSuggestionField(sug('benefit', { name: 'Free coffee' }))
    expect(result).not.toContain('{')
    expect(result).not.toContain('[')
    expect(result).toBe('Free coffee')
  })

  it('assigns a formatter for every supported SuggestionType', () => {
    const allTypes = [
      'job_title',
      'company',
      'department',
      'external_reference',
      'summary',
      'application_url',
      'city',
      'region',
      'country',
      'work_mode',
      'contract_type',
      'seniority_level',
      'working_hours',
      'travel_required',
      'relocation_required',
      'responsibility',
      'required_experience',
      'preferred_experience',
      'education',
      'required_skill',
      'preferred_skill',
      'language',
      'certification',
      'compensation',
      'benefit',
      'publication_date',
      'application_deadline',
      'expected_start_date',
      'employment_duration',
      'additional_requirement',
    ]
    for (const type of allTypes) {
      expect(SUGGESTION_VALUE_FIELDS).toHaveProperty(type)
    }
  })
})
