import { describe, it, expect } from 'vitest'
import {
  validateNativeForm,
  serverFieldErrors,
  normalizeUrl,
} from '@/features/profile/utils/validation'
import type { AxiosError } from 'axios'
import type { ProblemDetail } from '@/types/problem-detail'

describe('validateNativeForm', () => {
  it('returns valid when no errors', () => {
    const form = document.createElement('form')
    const input = document.createElement('input')
    input.name = 'email'
    input.value = 'test@example.com'
    form.appendChild(input)
    const result = validateNativeForm(form)
    expect(result.valid).toBe(true)
    expect(result.errors).toEqual({})
    expect(result.firstInvalidField).toBeNull()
  })

  it('gathers validation messages for invalid controls', () => {
    const form = document.createElement('form')
    const input = document.createElement('input')
    input.name = 'email'
    input.required = true
    input.value = ''
    form.appendChild(input)
    const result = validateNativeForm(form)
    expect(result.valid).toBe(false)
    expect(result.errors.email).toBeTruthy()
    expect(result.firstInvalidField).toBe('email')
  })

  it('skips controls without a name', () => {
    const form = document.createElement('form')
    const input = document.createElement('input')
    input.required = true
    input.value = ''
    form.appendChild(input)
    const result = validateNativeForm(form)
    expect(result.valid).toBe(true)
  })
})

describe('serverFieldErrors', () => {
  it('extracts first message per field from problem-detail response', () => {
    const error = {
      response: {
        data: {
          errors: {
            title: ['Title is required.', 'Title must be unique.'],
            email: ['Email is invalid.'],
          },
        },
      },
    } as AxiosError<ProblemDetail>
    expect(serverFieldErrors(error)).toEqual({
      title: 'Title is required.',
      email: 'Email is invalid.',
    })
  })

  it('returns empty object when no errors', () => {
    const error = { response: { data: {} } } as AxiosError<ProblemDetail>
    expect(serverFieldErrors(error)).toEqual({})
  })

  it('handles missing response gracefully', () => {
    const error = {} as AxiosError<ProblemDetail>
    expect(serverFieldErrors(error)).toEqual({})
  })
})

describe('normalizeUrl', () => {
  it('prepends https:// when missing protocol', () => {
    expect(normalizeUrl('example.com')).toBe('https://example.com')
  })

  it('returns https:// URLs unchanged', () => {
    expect(normalizeUrl('https://example.com')).toBe('https://example.com')
  })

  it('returns http:// URLs unchanged', () => {
    expect(normalizeUrl('http://example.com')).toBe('http://example.com')
  })

  it('trims whitespace', () => {
    expect(normalizeUrl('  example.com  ')).toBe('https://example.com')
  })

  it('returns empty string for empty input', () => {
    expect(normalizeUrl('')).toBe('')
  })
})
