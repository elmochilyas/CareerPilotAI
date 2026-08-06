import client from '@/api/client/axios'
import type {
  ApiData,
  CvDocument,
  CvImportBatch,
  CvSuggestion,
  ImportPreview,
  ReviewDecision,
} from '../types'

interface BatchDecisionItem {
  id: number
  decision: ReviewDecision['decision']
  edited_value?: Record<string, unknown>
}

export const cvKeys = {
  all: ['cv'] as const,
  list: () => [...cvKeys.all, 'list'] as const,
  detail: (id: number) => [...cvKeys.all, 'detail', id] as const,
  suggestions: (id: number) => [...cvKeys.all, id, 'suggestions'] as const,
  importPreview: (id: number) => [...cvKeys.all, id, 'import-preview'] as const,
  importResult: (docId: number) => [...cvKeys.all, docId, 'import-result'] as const,
}

export async function fetchCvDocuments(): Promise<CvDocument[]> {
  return (await client.get<ApiData<CvDocument[]>>('/api/v1/cv')).data.data
}

export async function fetchCvDocument(id: number): Promise<CvDocument> {
  return (await client.get<ApiData<CvDocument>>(`/api/v1/cv/${id}`)).data.data
}

export async function uploadCvDocument(
  file: File,
  onProgress?: (pct: number) => void,
  mode?: 'create_new' | 'update_existing',
): Promise<CvDocument> {
  const form = new FormData()
  form.append('file', file)
  if (mode) form.append('mode', mode)

  if (onProgress) {
    const res = await client.post<ApiData<CvDocument>>('/api/v1/cv', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => {
        if (e.total) onProgress(Math.round((e.loaded / e.total) * 100))
      },
    })
    return res.data.data
  }

  return (await client.post<ApiData<CvDocument>>('/api/v1/cv', form)).data.data
}

export async function deleteCvDocument(id: number): Promise<void> {
  await client.delete(`/api/v1/cv/${id}`)
}

export async function retryCvDocument(id: number): Promise<CvDocument> {
  return (await client.post<ApiData<CvDocument>>(`/api/v1/cv/${id}/retry`)).data.data
}

export async function fetchSuggestions(documentId: number): Promise<CvSuggestion[]> {
  return (await client.get<ApiData<CvSuggestion[]>>(`/api/v1/cv/${documentId}/suggestions`)).data
    .data
}

export async function updateSuggestion(
  documentId: number,
  suggestionId: number,
  decision: ReviewDecision,
): Promise<CvSuggestion> {
  return (
    await client.patch<ApiData<CvSuggestion>>(
      `/api/v1/cv/${documentId}/suggestions/${suggestionId}`,
      decision,
    )
  ).data.data
}

export async function batchUpdateSuggestions(
  documentId: number,
  decisions: BatchDecisionItem[],
): Promise<CvSuggestion[]> {
  return (
    await client.patch<ApiData<CvSuggestion[]>>(`/api/v1/cv/${documentId}/suggestions/batch`, {
      decisions,
    })
  ).data.data
}

export async function fetchImportPreview(documentId: number): Promise<ImportPreview> {
  return (await client.get<ApiData<ImportPreview>>(`/api/v1/cv/${documentId}/import-preview`)).data
    .data
}

export async function applyImport(
  documentId: number,
  idempotencyKey?: string,
  profileUpdatedAt?: string | null,
): Promise<CvImportBatch> {
  const headers: Record<string, string> = {}
  if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey

  const body: Record<string, unknown> = {}
  if (profileUpdatedAt) body.profile_updated_at = profileUpdatedAt

  return (
    await client.post<ApiData<CvImportBatch>>(`/api/v1/cv/${documentId}/apply`, body, { headers })
  ).data.data
}

export async function fetchImportResult(documentId: number): Promise<CvImportBatch> {
  return (await client.get<ApiData<CvImportBatch>>(`/api/v1/cv/${documentId}/import-result`)).data
    .data
}
