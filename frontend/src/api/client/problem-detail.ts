import type { AxiosError } from 'axios'
import type { ProblemDetail } from '@/types/problem-detail'

export function extractProblemDetail(error: AxiosError<ProblemDetail>): ProblemDetail | null {
  return error.response?.data ?? null
}
