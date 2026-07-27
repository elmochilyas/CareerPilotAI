import client from '@/api/client/axios'
import type {
  ApiMeta,
  DecisionInput,
  JobIngestion,
  JobOpportunity,
  JobSuggestion,
  PreviewData,
} from '../types'

export const opportunityKeys = {
  all: ['opportunities'] as const,
  ingestions: () => [...opportunityKeys.all, 'ingestions'] as const,
  ingestion: (id: number) => [...opportunityKeys.ingestions(), id] as const,
  suggestions: (id: number) => [...opportunityKeys.ingestion(id), 'suggestions'] as const,
  preview: (id: number) => [...opportunityKeys.ingestion(id), 'preview'] as const,
  list: () => [...opportunityKeys.all, 'list'] as const,
  detail: (id: number) => [...opportunityKeys.all, 'detail', id] as const,
}

export async function fetchIngestions(params?: {
  status?: string
  page?: number
}): Promise<{ data: JobIngestion[]; meta: ApiMeta }> {
  const res = await client.get('/api/v1/opportunities/ingestions', { params })
  return res.data
}

export async function createIngestion(data: {
  source_description: string
  source_url?: string
  personal_label?: string
}): Promise<JobIngestion> {
  return (await client.post('/api/v1/opportunities/ingestions', data)).data.data
}

export async function fetchIngestion(id: number): Promise<JobIngestion> {
  return (await client.get(`/api/v1/opportunities/ingestions/${id}`)).data.data
}

export async function retryIngestion(id: number): Promise<JobIngestion> {
  return (await client.post(`/api/v1/opportunities/ingestions/${id}/retry`)).data.data
}

export async function reanalyzeIngestion(id: number): Promise<JobIngestion> {
  return (await client.post(`/api/v1/opportunities/ingestions/${id}/reanalyze`)).data.data
}

export async function deleteIngestion(id: number): Promise<JobIngestion> {
  return (await client.delete(`/api/v1/opportunities/ingestions/${id}`)).data.data
}

export async function fetchSource(id: number): Promise<{
  source_description: string
  source_url: string | null
}> {
  return (await client.get(`/api/v1/opportunities/ingestions/${id}/source`)).data.data
}

export async function fetchSuggestions(id: number): Promise<JobSuggestion[]> {
  return (await client.get(`/api/v1/opportunities/ingestions/${id}/suggestions`)).data.data
}

export async function updateSuggestion(
  ingestionId: number,
  suggestionId: number,
  data: { decision: string; edited_value?: Record<string, unknown> },
): Promise<JobSuggestion> {
  return (
    await client.patch(
      `/api/v1/opportunities/ingestions/${ingestionId}/suggestions/${suggestionId}`,
      data,
    )
  ).data.data
}

export async function batchUpdateSuggestions(
  ingestionId: number,
  decisions: DecisionInput[],
): Promise<JobSuggestion[]> {
  return (
    await client.post(`/api/v1/opportunities/ingestions/${ingestionId}/suggestions/batch`, {
      decisions,
    })
  ).data.data
}

export async function generatePreview(ingestionId: number): Promise<PreviewData> {
  return (await client.post(`/api/v1/opportunities/ingestions/${ingestionId}/preview`)).data.data
}

export async function confirmIngestion(
  ingestionId: number,
  versionToken: string,
): Promise<JobOpportunity> {
  return (
    await client.post(`/api/v1/opportunities/ingestions/${ingestionId}/confirm`, {
      version_token: versionToken,
    })
  ).data.data
}

export async function fetchOpportunities(params?: {
  page?: number
}): Promise<{ data: JobOpportunity[]; meta: ApiMeta }> {
  const res = await client.get('/api/v1/opportunities', { params })
  return res.data
}

export async function fetchOpportunity(id: number): Promise<JobOpportunity> {
  return (await client.get(`/api/v1/opportunities/${id}`)).data.data
}
