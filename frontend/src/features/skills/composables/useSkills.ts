import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { extractProblemDetail } from '@/api/client'
import type { CandidateSkillInput, CandidateSkillUpdate, EvidenceInput } from '../types'
import type { ProblemDetail } from '@/types/problem-detail'
import {
  addEvidence,
  archiveCandidateSkill,
  createCandidateSkill,
  deleteCandidateSkill,
  fetchCandidateSkills,
  removeEvidence,
  restoreCandidateSkill,
  skillKeys,
  updateCandidateSkill,
  updateEvidence,
} from '../api'

export interface ConflictError {
  code: string
  detail: string
}

export function useSkills() {
  const queryClient = useQueryClient()
  const announcement = ref('')
  const conflictError = ref<ConflictError | null>(null)
  const lastError = ref<ProblemDetail | null>(null)

  const candidateQuery = useQuery({
    queryKey: skillKeys.candidate(),
    queryFn: () => fetchCandidateSkills(),
  })

  const refreshCandidate = () => queryClient.invalidateQueries({ queryKey: skillKeys.candidate() })

  const clearConflict = () => {
    conflictError.value = null
    announcement.value = ''
  }

  function handleError(error: unknown, fallbackMessage: string): boolean {
    const problem = extractProblemDetail(error as never)
    lastError.value = problem ?? null
    const code = problem?.code
    if (code === 'candidate_skill_conflict') {
      conflictError.value = {
        code,
        detail: problem?.detail ?? 'This skill was modified. Please refresh.',
      }
      announcement.value = 'This skill was modified. Please refresh.'
      return false
    }
    if (code === 'candidate_skill_duplicate') {
      announcement.value = 'This skill is already in your profile.'
      return false
    }
    announcement.value = fallbackMessage
    return true
  }

  const createMutation = useMutation({
    mutationFn: (input: CandidateSkillInput) => createCandidateSkill(input),
    onSuccess: () => {
      refreshCandidate()
      announcement.value = 'Skill added.'
    },
    onError: (error) => handleError(error, 'Could not add skill.'),
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, input }: { id: number; input: CandidateSkillUpdate }) =>
      updateCandidateSkill(id, input),
    onSuccess: () => {
      refreshCandidate()
      announcement.value = 'Skill updated.'
    },
    onError: (error) => handleError(error, 'Could not update skill.'),
  })

  const archiveMutation = useMutation({
    mutationFn: ({ id, updatedAt }: { id: number; updatedAt?: string }) =>
      archiveCandidateSkill(id, updatedAt),
    onSuccess: () => {
      refreshCandidate()
      announcement.value = 'Skill archived.'
    },
    onError: (error) => handleError(error, 'Could not archive skill.'),
  })

  const restoreMutation = useMutation({
    mutationFn: ({ id, state, updatedAt }: { id: number; state: string; updatedAt?: string }) =>
      restoreCandidateSkill(id, state, updatedAt),
    onSuccess: () => {
      refreshCandidate()
      announcement.value = 'Skill restored.'
    },
    onError: (error) => handleError(error, 'Could not restore skill.'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteCandidateSkill(id),
    onSuccess: () => {
      refreshCandidate()
      announcement.value = 'Skill removed.'
    },
    onError: (error) => handleError(error, 'Could not remove skill.'),
  })

  const addEvidenceMutation = useMutation({
    mutationFn: ({ id, input }: { id: number; input: EvidenceInput }) => addEvidence(id, input),
    onSuccess: () => {
      refreshCandidate()
      announcement.value = 'Evidence added.'
    },
    onError: (error) => handleError(error, 'Could not add evidence.'),
  })

  const updateEvidenceMutation = useMutation({
    mutationFn: ({ id, key, input }: { id: number; key: string; input: Partial<EvidenceInput> }) =>
      updateEvidence(id, key, input),
    onSuccess: () => {
      refreshCandidate()
      announcement.value = 'Evidence updated.'
    },
    onError: (error) => handleError(error, 'Could not update evidence.'),
  })

  const removeEvidenceMutation = useMutation({
    mutationFn: ({ id, key }: { id: number; key: string }) => removeEvidence(id, key),
    onSuccess: () => {
      refreshCandidate()
      announcement.value = 'Evidence removed.'
    },
    onError: (error) => handleError(error, 'Could not remove evidence.'),
  })

  const skills = computed(() => candidateQuery.data.value ?? [])

  const isReady = computed(() => !candidateQuery.isPending.value && !candidateQuery.isError.value)

  return {
    ...candidateQuery,
    skills,
    isReady,
    announcement,
    conflictError,
    lastError,
    clearConflict,
    refreshCandidate,
    handleError,
    createMutation,
    updateMutation,
    archiveMutation,
    restoreMutation,
    deleteMutation,
    addEvidenceMutation,
    updateEvidenceMutation,
    removeEvidenceMutation,
  }
}
