import type { AxiosError } from 'axios'
import { describe, it, expect } from 'vitest'
import { extractProblemDetail } from '@/api/client/problem-detail'
import type { ProblemDetail } from '@/types/problem-detail'

describe('extractProblemDetail', () => {
  it('returns null when no response data', () => {
    const error = { response: undefined } as AxiosError<ProblemDetail>
    expect(extractProblemDetail(error)).toBeNull()
  })

  it('extracts problem detail from error response', () => {
    const detail: ProblemDetail = {
      type: 'https://careerpilot.example/problems/not_found',
      title: 'Not Found',
      status: 404,
      detail: 'Resource not found',
      instance: '/api/v1/test',
      code: 'not_found',
      errors: {},
      request_id: 'req-123',
    }
    const error = { response: { data: detail } } as AxiosError<ProblemDetail>
    expect(extractProblemDetail(error)).toEqual(detail)
  })
})
