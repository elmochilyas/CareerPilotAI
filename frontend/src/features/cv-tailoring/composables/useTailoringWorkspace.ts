import { computed, ref, shallowRef, toValue, watch } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import axios from 'axios'
import type { Resume, ResumeContent, WorkspaceStep } from '../types'
import { extractProblemDetail } from '@/api/client'
import {
  approveResume,
  createResume,
  listResumes,
  previewResume,
  resumeKeys,
  showResume,
  tailorResume,
  updateResume,
} from '../api'

export function useTailoringWorkspace(
  opportunityId: MaybeRefOrGetter<number>,
  resumeId?: MaybeRefOrGetter<number | null>,
) {
  const queryClient = useQueryClient()

  const resolvedOppId = computed(() => toValue(opportunityId))
  const resolvedResumeId = computed(() => (resumeId ? toValue(resumeId) : null))

  const currentStep = ref<WorkspaceStep>('generate')
  const loadErrorDetail = shallowRef<string | null>(null)

  const resumeQuery = useQuery({
    queryKey: computed(() => {
      if (resolvedResumeId.value) {
        return resumeKeys.detail(resolvedResumeId.value)
      }
      return resumeKeys.opportunity(resolvedOppId.value)
    }),
    queryFn: async (): Promise<Resume | null> => {
      loadErrorDetail.value = null
      try {
        if (resolvedResumeId.value) {
          return await showResume(resolvedResumeId.value)
        }
        const resumes = await listResumes(resolvedOppId.value)
        if (resumes.length === 0) {
          return null
        }
        const latestResume = resumes[0]
        return latestResume ? await showResume(latestResume.id) : null
      } catch (error) {
        const problem = extractProblemDetail(error as never)
        loadErrorDetail.value =
          problem?.detail ??
          (problem?.status
            ? `The resume request failed with status ${problem.status}.`
            : axios.isAxiosError(error) && error.response?.status
              ? `The resume request failed with status ${error.response.status}.`
              : error instanceof Error
                ? error.message
                : null)
        throw error
      }
    },
    enabled: computed(() => resolvedOppId.value > 0),
  })

  const previewQuery = useQuery({
    queryKey: computed(() => {
      const resume = resumeQuery.data.value
      return resume
        ? resumeKeys.preview(resume.id)
        : (['resumes', 'preview', 'placeholder'] as const)
    }),
    queryFn: async () => {
      const resume = resumeQuery.data.value
      if (!resume) return null
      return previewResume(resume.id)
    },
    enabled: computed(() => !!resumeQuery.data.value),
  })

  const updateMutation = useMutation({
    mutationFn: async ({ resumeId, content, proposal_decisions }: { resumeId: number; content: ResumeContent; proposal_decisions?: Array<{ id: number; status: 'proposed' | 'accepted' | 'rejected'; edited_text?: string | null }> }) => {
      return updateResume(resumeId, { content, proposal_decisions })
    },
    onSuccess: (updated) => {
      const resume = resumeQuery.data.value
      if (resume) {
        queryClient.setQueryData(resumeKeys.detail(resume.id), updated)
        queryClient.setQueryData(resumeKeys.opportunity(resolvedOppId.value), updated)
        queryClient.invalidateQueries({ queryKey: resumeKeys.detail(resume.id) })
        queryClient.invalidateQueries({ queryKey: resumeKeys.opportunity(resolvedOppId.value) })
        queryClient.invalidateQueries({ queryKey: resumeKeys.preview(resume.id) })
      }
    },
  })

  const createMutation = useMutation({
    mutationFn: async () => {
      const existingResumes = await listResumes(resolvedOppId.value)
      const latestResume = existingResumes[0]
      const draft = latestResume
        ? await showResume(latestResume.id)
        : await createResume(resolvedOppId.value, {})

      await tailorResume(draft.id, { use_ai: true })

      return showResume(draft.id)
    },
    onSuccess: (generatedResume) => {
      queryClient.setQueryData(resumeKeys.opportunity(resolvedOppId.value), generatedResume)
      queryClient.setQueryData(resumeKeys.detail(generatedResume.id), generatedResume)
      queryClient.invalidateQueries({ queryKey: resumeKeys.preview(generatedResume.id) })
      currentStep.value = 'review'
    },
  })

  const tailorMutation = useMutation({
    mutationFn: async (resumeId: number) => {
      return tailorResume(resumeId, { use_ai: true })
    },
    onSuccess: async () => {
      const resume = resumeQuery.data.value
      if (resume) {
        queryClient.invalidateQueries({ queryKey: resumeKeys.detail(resume.id) })
        queryClient.invalidateQueries({ queryKey: resumeKeys.preview(resume.id) })
        await queryClient.refetchQueries({ queryKey: resumeKeys.detail(resume.id) })
        await queryClient.refetchQueries({ queryKey: resumeKeys.preview(resume.id) })
        currentStep.value = 'review'
      }
    },
  })

  const approveMutation = useMutation({
    mutationFn: async (resumeId: number) => {
      return approveResume(resumeId)
    },
    onSuccess: (approved) => {
      const resume = resumeQuery.data.value
      if (resume) {
        queryClient.setQueryData(resumeKeys.detail(resume.id), approved)
        queryClient.setQueryData(resumeKeys.opportunity(resolvedOppId.value), approved)
        queryClient.invalidateQueries({ queryKey: resumeKeys.detail(resume.id) })
        queryClient.invalidateQueries({ queryKey: resumeKeys.opportunity(resolvedOppId.value) })
      }
      currentStep.value = 'export'
    },
  })

  const resume = computed(() => resumeQuery.data.value)
  const preview = computed(() => previewQuery.data.value)
  const isCreating = computed(() => resumeQuery.isLoading.value || createMutation.isPending.value)
  const isDraft = computed(() => resume.value?.status === 'draft')
  const isApproved = computed(() => resume.value?.status === 'approved')

  const steps: WorkspaceStep[] = ['generate', 'review', 'export']
  const currentStepIndex = computed(() => steps.indexOf(currentStep.value))

  function goToStep(step: WorkspaceStep): void {
    currentStep.value = step
  }

  function nextStep(): void {
    const idx = currentStepIndex.value
    const next = steps[idx + 1]
    if (next) {
      currentStep.value = next
    }
  }

  function prevStep(): void {
    const idx = currentStepIndex.value
    const prev = steps[idx - 1]
    if (prev) {
      currentStep.value = prev
    }
  }

  let initialized = false
  watch(resume, (value) => {
    if (!value || initialized) return
    initialized = true
    currentStep.value = value.status === 'approved' ? 'export' : value.content.sections.length > 0 ? 'review' : 'generate'
  }, { immediate: true })

  return {
    resume,
    loadErrorDetail,
    preview,
    isCreating,
    isDraft,
    isApproved,
    currentStep,
    currentStepIndex,
    steps,
    goToStep,
    nextStep,
    prevStep,
    resumeQuery,
    previewQuery,
    updateMutation,
    createMutation,
    tailorMutation,
    approveMutation,
  }
}
