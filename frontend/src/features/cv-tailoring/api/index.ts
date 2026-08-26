import client from '@/api/client/axios'
import type {
  CreateResumeInput,
  Resume,
  ResumeListItem,
  ResumePreview,
  TailorResumeInput,
  UpdateResumeInput,
} from '../types'

export const resumeKeys = {
  all: ['resumes'] as const,
  list: () => [...resumeKeys.all, 'list'] as const,
  opportunity: (opportunityId: number) =>
    [...resumeKeys.all, 'opportunity', opportunityId] as const,
  detail: (resumeId: number) => [...resumeKeys.all, 'detail', resumeId] as const,
  preview: (resumeId: number) => [...resumeKeys.all, 'preview', resumeId] as const,
}

export async function createResume(
  opportunityId: number,
  input: CreateResumeInput,
): Promise<Resume> {
  const res = await client.post(`/api/v1/opportunities/${opportunityId}/resumes`, {
    opportunity_id: opportunityId,
    ...input,
  })
  return res.data.data
}

export async function listResumes(opportunityId: number): Promise<ResumeListItem[]> {
  const res = await client.get(`/api/v1/opportunities/${opportunityId}/resumes`, {
    params: { opportunity_id: opportunityId },
  })
  return res.data.data
}

export async function showResume(resumeId: number): Promise<Resume> {
  const res = await client.get(`/api/v1/resumes/${resumeId}`)
  return res.data.data
}

export async function updateResume(resumeId: number, input: UpdateResumeInput): Promise<Resume> {
  const res = await client.patch(`/api/v1/resumes/${resumeId}`, input)
  return res.data.data
}

export async function approveResume(resumeId: number): Promise<Resume> {
  const res = await client.post(`/api/v1/resumes/${resumeId}/approve`)
  return res.data.data
}

export async function previewResume(resumeId: number): Promise<ResumePreview> {
  const res = await client.get(`/api/v1/resumes/${resumeId}/preview`)
  return res.data.data
}

export async function tailorResume(
  resumeId: number,
  input?: TailorResumeInput,
): Promise<{ status: string }> {
  const res = await client.post(`/api/v1/resumes/${resumeId}/tailor`, input ?? {})
  return res.data.data
}

export async function deleteResume(resumeId: number): Promise<void> {
  await client.delete(`/api/v1/resumes/${resumeId}`)
}
