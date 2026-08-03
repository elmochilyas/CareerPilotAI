import { describe, it, expect } from 'vitest'
import { formatDate } from '@/app/utils/date'

describe('formatDate', () => {
  it('formats a Date object', () => {
    const result = formatDate(new Date('2026-07-26T12:00:00Z'))
    expect(result).toBe('Jul 26, 2026')
  })

  it('formats an ISO string', () => {
    const result = formatDate('2026-07-26T12:00:00Z')
    expect(result).toBe('Jul 26, 2026')
  })

  it('returns empty for null', () => {
    expect(formatDate(null)).toBe('')
  })

  it('returns empty for undefined', () => {
    expect(formatDate(undefined)).toBe('')
  })

  it('returns empty for invalid date string', () => {
    expect(formatDate('not-a-date')).toBe('')
  })
})
