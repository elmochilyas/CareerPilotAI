import type { AxiosError } from 'axios'
import type { ProblemDetail } from '@/types/problem-detail'

export interface ValidationResult {
  valid: boolean
  errors: Record<string, string>
  firstInvalidField: string | null
}

export function validateNativeForm(form: HTMLFormElement): ValidationResult {
  const errors: Record<string, string> = {}
  for (const control of Array.from(form.elements)) {
    if (
      control instanceof HTMLInputElement ||
      control instanceof HTMLTextAreaElement ||
      control instanceof HTMLSelectElement
    ) {
      if (control.name && !control.checkValidity()) errors[control.name] = control.validationMessage
    }
  }
  const firstInvalidField = Object.keys(errors)[0] ?? null
  if (firstInvalidField) {
    const control = form.elements.namedItem(firstInvalidField)
    if (control instanceof HTMLElement && 'focus' in control) control.focus()
  }
  return { valid: firstInvalidField === null, errors, firstInvalidField }
}

export function serverFieldErrors(error: unknown): Record<string, string> {
  const problem = (error as AxiosError<ProblemDetail>).response?.data
  return Object.fromEntries(
    Object.entries(problem?.errors ?? {}).map(([field, messages]) => [
      field,
      messages[0] ?? 'Invalid value.',
    ]),
  )
}

export function normalizeUrl(value: string): string {
  const trimmed = value.trim()
  return trimmed && !/^https?:\/\//i.test(trimmed) ? `https://${trimmed}` : trimmed
}
