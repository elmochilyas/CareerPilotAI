import client from '@/api/client/axios'
import type {
  Skill,
  CandidateSkill,
  CandidateSkillInput,
  CandidateSkillUpdate,
  EvidenceInput,
  ApiData,
} from '../types'

export const skillKeys = {
  all: ['skills'] as const,
  catalog: () => [...skillKeys.all, 'catalog'] as const,
  catalogSearch: (q: string) => [...skillKeys.catalog(), q] as const,
  detail: (id: number) => [...skillKeys.all, 'detail', id] as const,
  candidate: () => ['candidate-skills'] as const,
  candidateDetail: (id: number) => ['candidate-skills', 'detail', id] as const,
}

export async function searchSkills(q?: string): Promise<Skill[]> {
  const params: Record<string, string> = {}
  if (q) params.q = q
  return (await client.get<ApiData<Skill[]>>('/api/v1/skills', { params })).data.data
}

export async function fetchSkill(id: number): Promise<Skill> {
  return (await client.get<ApiData<Skill>>(`/api/v1/skills/${id}`)).data.data
}

export async function fetchCandidateSkills(state?: string): Promise<CandidateSkill[]> {
  const params: Record<string, string> = {}
  if (state) params.state = state
  return (await client.get<ApiData<CandidateSkill[]>>('/api/v1/candidate/skills', { params })).data
    .data
}

export async function createCandidateSkill(input: CandidateSkillInput): Promise<CandidateSkill> {
  return (await client.post<ApiData<CandidateSkill>>('/api/v1/candidate/skills', input)).data.data
}

export async function fetchCandidateSkill(id: number): Promise<CandidateSkill> {
  return (await client.get<ApiData<CandidateSkill>>(`/api/v1/candidate/skills/${id}`)).data.data
}

export async function updateCandidateSkill(
  id: number,
  input: CandidateSkillUpdate,
): Promise<CandidateSkill> {
  return (await client.patch<ApiData<CandidateSkill>>(`/api/v1/candidate/skills/${id}`, input)).data
    .data
}

export async function archiveCandidateSkill(
  id: number,
  updatedAt?: string,
): Promise<CandidateSkill> {
  const body: Record<string, string> = {}
  if (updatedAt) body.updated_at = updatedAt
  return (
    await client.post<ApiData<CandidateSkill>>(`/api/v1/candidate/skills/${id}/archive`, body)
  ).data.data
}

export async function restoreCandidateSkill(
  id: number,
  state: string,
  updatedAt?: string,
): Promise<CandidateSkill> {
  const body: Record<string, string> = { state }
  if (updatedAt) body.updated_at = updatedAt
  return (
    await client.post<ApiData<CandidateSkill>>(`/api/v1/candidate/skills/${id}/restore`, body)
  ).data.data
}

export async function deleteCandidateSkill(id: number): Promise<void> {
  await client.delete(`/api/v1/candidate/skills/${id}`)
}

export async function addEvidence(id: number, input: EvidenceInput): Promise<CandidateSkill> {
  return (
    await client.post<ApiData<CandidateSkill>>(`/api/v1/candidate/skills/${id}/evidence`, input)
  ).data.data
}

export async function updateEvidence(
  id: number,
  key: string,
  input: Partial<EvidenceInput>,
): Promise<CandidateSkill> {
  return (
    await client.patch<ApiData<CandidateSkill>>(
      `/api/v1/candidate/skills/${id}/evidence/${key}`,
      input,
    )
  ).data.data
}

export async function removeEvidence(id: number, key: string): Promise<CandidateSkill> {
  return (
    await client.delete<ApiData<CandidateSkill>>(`/api/v1/candidate/skills/${id}/evidence/${key}`)
  ).data.data
}
