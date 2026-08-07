import client from '@/api/client/axios'
import type {
  ClarificationAnswer,
  ClarificationAnswerInput,
  ClarificationReviewInput,
  ClarificationSession,
  ClarificationSkipped,
} from '../types'

export const clarificationKeys = {
  all: ['clarifications'] as const,
  session: (analysisId: number) => [...clarificationKeys.all, 'session', analysisId] as const,
  question: (questionId: number) => [...clarificationKeys.all, 'question', questionId] as const,
}

function nextRequestId(): string {
  return `req_${crypto.randomUUID()}`
}

export async function fetchClarificationSession(analysisId: number): Promise<ClarificationSession> {
  const res = await client.get(`/api/v1/matches/${analysisId}/clarifications`, {
    headers: { 'X-Request-ID': nextRequestId() },
  })
  return res.data.data
}

export async function generateClarificationSession(
  analysisId: number,
): Promise<ClarificationSession> {
  const res = await client.post(`/api/v1/matches/${analysisId}/clarifications`, null, {
    headers: { 'X-Request-ID': nextRequestId() },
  })
  return res.data.data
}

export async function answerClarificationQuestion(
  questionId: number,
  input: ClarificationAnswerInput,
): Promise<ClarificationAnswer> {
  const res = await client.post(`/api/v1/clarifications/${questionId}/answer`, input, {
    headers: { 'X-Request-ID': nextRequestId() },
  })
  return res.data.data
}

export async function reviewClarificationAnswer(
  questionId: number,
  input: ClarificationReviewInput,
): Promise<ClarificationAnswer> {
  const res = await client.post(`/api/v1/clarifications/${questionId}/review`, input, {
    headers: { 'X-Request-ID': nextRequestId() },
  })
  return res.data.data
}

export async function skipClarificationQuestion(questionId: number): Promise<ClarificationSkipped> {
  const res = await client.post(
    `/api/v1/clarifications/${questionId}/skip`,
    {},
    { headers: { 'X-Request-ID': nextRequestId() } },
  )
  return res.data.data
}
