import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { extractProblemDetail } from '@/api/client'
import type { ReviewDecision } from '../types'
import {
  applyImport,
  batchUpdateSuggestions,
  cvKeys,
  deleteCvDocument,
  fetchCvDocument,
  fetchImportPreview,
  fetchSuggestions,
  retryCvDocument,
  updateSuggestion,
  uploadCvDocument,
} from '../api'

export type CvStage =
  | 'choose_mode'
  | 'idle'
  | 'upload'
  | 'processing'
  | 'review'
  | 'import'
  | 'complete'

export function useCvIngestion() {
  const queryClient = useQueryClient()
  const announcement = ref('')
  const stage = ref<CvStage>('choose_mode')
  const activeDocumentId = ref<number | null>(null)
  const uploadProgress = ref(0)
  const importIdempotencyKey = ref('')
  const profileUpdatedAt = ref('')
  const uploadMode = ref<'create_new' | 'update_existing'>('create_new')
  const uploadError = ref<{ code: string; detail: string } | null>(null)
  const duplicateCvId = ref<number | null>(null)
  const reviewReadonly = ref(false)

  const terminalStatuses = new Set(['ready_for_review', 'failed', 'importing', 'imported'])

  const documentQuery = useQuery({
    queryKey: computed(() => cvKeys.detail(activeDocumentId.value!)),
    queryFn: () => fetchCvDocument(activeDocumentId.value!),
    enabled: computed(() => activeDocumentId.value !== null),
    refetchInterval: computed(() => {
      if (stage.value === 'processing') return 3000
      return false
    }),
    retry: 1,
  })

  const suggestionsQuery = useQuery({
    queryKey: computed(() => cvKeys.suggestions(activeDocumentId.value!)),
    queryFn: () => fetchSuggestions(activeDocumentId.value!),
    enabled: computed(() => stage.value === 'review' && activeDocumentId.value !== null),
    retry: 1,
  })

  const previewQuery = useQuery({
    queryKey: computed(() => cvKeys.importPreview(activeDocumentId.value!)),
    queryFn: () => fetchImportPreview(activeDocumentId.value!),
    enabled: computed(() => stage.value === 'import' && activeDocumentId.value !== null),
    retry: 1,
  })

  const document = computed(() => documentQuery.data.value ?? null)
  const suggestions = computed(() => suggestionsQuery.data.value ?? [])
  const preview = computed(() => previewQuery.data.value ?? null)

  const isDocumentTerminal = computed(() => {
    const s = document.value?.status
    return s ? terminalStatuses.has(s) : false
  })

  watch(
    () => document.value?.status,
    (status) => {
      if (status && terminalStatuses.has(status)) {
        if (status === 'ready_for_review' && stage.value !== 'review') {
          stage.value = 'review'
        } else if (status === 'imported' && stage.value !== 'complete') {
          stage.value = 'complete'
        }
      }
    },
  )

  watch(uploadError, (err) => {
    if (!err) duplicateCvId.value = null
  })

  watch(preview, (p) => {
    if (p?.profile_updated_at) {
      profileUpdatedAt.value = p.profile_updated_at
    }
  })
  const isUploading = ref(false)

  const reviewProgress = computed(() => {
    const all = suggestions.value.length
    if (all === 0) return { reviewed: 0, total: 0, pct: 0 }
    const reviewed = suggestions.value.filter((s) => s.review_status !== 'pending').length
    return { reviewed, total: all, pct: Math.round((reviewed / all) * 100) }
  })

  watch(
    () => document.value?.status,
    (status, oldStatus) => {
      if (!status) {
        if (stage.value !== 'idle') stage.value = 'idle'
        return
      }
      if (!oldStatus && status === 'pending') {
        stage.value = 'upload'
      }
    },
  )

  function handleError(error: unknown, fallback: string): void {
    const detail = extractProblemDetail(error as never)
    if (detail?.detail) {
      announcement.value = detail.detail
    } else {
      announcement.value = fallback
    }
  }

  const uploadMutation = useMutation({
    mutationFn: (file: File) => {
      uploadProgress.value = 0
      isUploading.value = true
      return uploadCvDocument(
        file,
        (pct) => {
          uploadProgress.value = pct
        },
        uploadMode.value,
      )
    },
    onSuccess: (doc) => {
      uploadError.value = null
      activeDocumentId.value = doc.id
      stage.value = 'processing'
      announcement.value = 'CV uploaded. Processing started.'
    },
    onError: (error) => {
      const detail = extractProblemDetail(error as never)
      if (detail?.code) {
        uploadError.value = { code: detail.code, detail: detail.detail ?? 'Upload failed.' }
        if (detail.code === 'file_duplicate' && typeof detail.errors?.existing_cv_id === 'number') {
          duplicateCvId.value = detail.errors.existing_cv_id
        }
      } else {
        uploadError.value = { code: 'unknown', detail: 'Upload failed. Please try again.' }
      }
      handleError(error, 'Upload failed. Please try again.')
    },
    onSettled: () => {
      isUploading.value = false
      queryClient.invalidateQueries({ queryKey: cvKeys.list() })
    },
  })

  const retryMutation = useMutation({
    mutationFn: (id: number) => retryCvDocument(id),
    onSuccess: () => {
      stage.value = 'processing'
      announcement.value = 'Retrying...'
      documentQuery.refetch()
    },
    onError: (error) => handleError(error, 'Retry failed.'),
  })

  const isRetrying = computed(() => retryMutation.isPending.value)

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteCvDocument(id),
    onSuccess: () => {
      activeDocumentId.value = null
      stage.value = 'idle'
      announcement.value = 'CV document deleted.'
      queryClient.invalidateQueries({ queryKey: cvKeys.list() })
    },
    onError: (error) => handleError(error, 'Delete failed.'),
  })

  const batchSaveMutation = useMutation({
    mutationFn: ({
      documentId,
      decisions,
    }: {
      documentId: number
      decisions: {
        id: number
        decision: ReviewDecision['decision']
        edited_value?: Record<string, unknown>
      }[]
    }) => batchUpdateSuggestions(documentId, decisions),
    onSuccess: () => {
      announcement.value = 'Decisions saved.'
      suggestionsQuery.refetch()
    },
    onError: (error) => handleError(error, 'Failed to save decisions.'),
  })

  const updateSuggestionMutation = useMutation({
    mutationFn: ({
      documentId,
      suggestionId,
      decision,
    }: {
      documentId: number
      suggestionId: number
      decision: ReviewDecision
    }) => updateSuggestion(documentId, suggestionId, decision),
    onSuccess: () => {
      announcement.value = 'Decision saved.'
      suggestionsQuery.refetch()
    },
    onError: (error) => handleError(error, 'Failed to save decision.'),
  })

  const importMutation = useMutation({
    mutationFn: (documentId: number) =>
      applyImport(documentId, importIdempotencyKey.value, profileUpdatedAt.value),
    onSuccess: () => {
      stage.value = 'complete'
      announcement.value = 'CV imported to profile!'
      queryClient.invalidateQueries({ queryKey: cvKeys.all })
    },
    onError: (error) => {
      const detail = extractProblemDetail(error as never)
      if (detail?.code === 'profile_changed') {
        announcement.value = 'Profile changed since review. Please re-review.'
        stage.value = 'review'
      } else {
        handleError(error, 'Import failed.')
      }
    },
  })

  const reviewedSuggestions = computed(() =>
    suggestions.value.filter((s) => s.review_status !== 'pending'),
  )
  const pendingSuggestions = computed(() =>
    suggestions.value.filter((s) => s.review_status === 'pending'),
  )
  const allReviewed = computed(() => pendingSuggestions.value.length === 0)

  function chooseMode(mode: 'create_new' | 'update_existing'): void {
    uploadMode.value = mode
    stage.value = 'idle'
  }

  function startImport(): void {
    stage.value = 'import'
    importIdempotencyKey.value = crypto.randomUUID()
    previewQuery.refetch()
  }

  function resumeReview(id: number, readonly = false): void {
    uploadError.value = null
    activeDocumentId.value = id
    stage.value = 'review'
    reviewReadonly.value = readonly
    documentQuery.refetch()
  }

  function restoreFromParams(params: {
    documentId: number
    stage: string
    readonly?: boolean
  }): void {
    uploadError.value = null
    activeDocumentId.value = params.documentId

    if (params.stage === 'review') {
      stage.value = 'review'
      reviewReadonly.value = params.readonly ?? false
    } else if (params.stage === 'import') {
      stage.value = 'import'
      importIdempotencyKey.value = crypto.randomUUID()
    } else if (params.stage === 'complete') {
      stage.value = 'complete'
    }
  }

  function reset(): void {
    uploadError.value = null
    duplicateCvId.value = null
    reviewReadonly.value = false
    activeDocumentId.value = null
    stage.value = 'choose_mode'
    uploadProgress.value = 0
    importIdempotencyKey.value = ''
  }

  return {
    stage,
    activeDocumentId,
    uploadProgress,
    isUploading,
    uploadError,
    duplicateCvId,
    announcement,
    document,
    suggestions,
    preview,
    reviewProgress,
    reviewedSuggestions,
    pendingSuggestions,
    allReviewed,
    uploadMode,
    chooseMode,
    reviewReadonly,
    startImport,
    resumeReview,
    restoreFromParams,
    reset,
    isRetrying,
    isDocumentTerminal,
    documentQuery,
    suggestionsQuery,
    previewQuery,
    uploadMutation,
    retryMutation,
    deleteMutation,
    batchSaveMutation,
    updateSuggestionMutation,
    importMutation,
  }
}
