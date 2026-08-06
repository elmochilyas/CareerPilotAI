import client from '@/api/client/axios'
import type { MatchAnalysis, MatchListResult, MatchOperation } from '../types'

export const matchKeys = {
  all: ['matches'] as const,
  list: (opportunityId: number) => [...matchKeys.all, 'list', opportunityId] as const,
  detail: (matchId: number) => [...matchKeys.all, 'detail', matchId] as const,
}

function nextRequestId(): string {
  return `req_${crypto.randomUUID()}`
}

export async function createMatchAnalysis(
  opportunityId: number,
  idempotencyKey: string,
): Promise<MatchOperation> {
  const res = await client.post(
    `/api/v1/opportunities/${opportunityId}/matches`,
    {},
    {
      headers: {
        'X-Request-ID': nextRequestId(),
        'Idempotency-Key': idempotencyKey,
      },
    },
  )

  return res.data.data
}

export async function fetchMatchAnalyses(opportunityId: number): Promise<MatchListResult> {
  const res = await client.get(`/api/v1/opportunities/${opportunityId}/matches`)
  return res.data
}

export async function fetchMatchAnalysis(matchId: number): Promise<MatchAnalysis> {
  return (await client.get(`/api/v1/matches/${matchId}`)).data.data
}

export async function recalculateMatchAnalysis(matchId: number): Promise<MatchOperation> {
  const res = await client.post(
    `/api/v1/matches/${matchId}/recalculate`,
    {},
    {
      headers: {
        'X-Request-ID': nextRequestId(),
      },
    },
  )

  return res.data.data
}
