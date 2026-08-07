import { computed, ref, toValue } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { extractProblemDetail } from '@/api/client'
import { matchKeys } from '@/features/matching/api'
import { skillKeys } from '@/features/skills/api'
import type {
  ClarificationAnswer,
  ClarificationAnswerInput,
  ClarificationReviewInput,
} from '../types'
import {
  answerClarificationQuestion,
  clarificationKeys,
  fetchClarificationSession,
  generateClarificationSession,
  reviewClarificationAnswer,
  skipClarificationQuestion,
} from '../api'

export function clarificationProblemCode(error: unknown): string | null {
  return extractProblemDetail(error as never)?.code ?? null
}

export function useClarificationSession(analysisId: MaybeRefOrGetter<number | null>) {
  const queryClient = useQueryClient()
  const analysisIdValue = computed(() => toValue(analysisId))
  const announcement = ref('')

  const sessionQuery = useQuery({
    queryKey: computed(() => clarificationKeys.session(analysisIdValue.value ?? 0)),
    queryFn: () => fetchClarificationSession(analysisIdValue.value!),
    enabled: computed(() => analysisIdValue.value !== null),
    retry: 1,
  })

  const session = computed(() => sessionQuery.data.value)
  const isSessionLoading = computed(() => sessionQuery.isLoading.value)
  const isSessionError = computed(() => sessionQuery.isError.value)
  const sessionErrorDetail = computed(
    () => extractProblemDetail(sessionQuery.error.value as never)?.detail ?? null,
  )
  const sessionProblemCode = computed(() => clarificationProblemCode(sessionQuery.error.value))

  const questions = computed(() => session.value?.questions ?? [])
  const totalQuestions = computed(() => session.value?.progress.total ?? 0)
  const answeredCount = computed(() => session.value?.progress.answered ?? 0)
  const openQuestionCount = computed(
    () => questions.value.filter((question) => question.status === 'pending').length,
  )
  const hasOpenQuestions = computed(() => openQuestionCount.value > 0)
  const generableCount = computed(() => session.value?.generable_count ?? 0)

  function invalidateSession(): void {
    const id = analysisIdValue.value
    if (id === null) return
    void queryClient.invalidateQueries({ queryKey: clarificationKeys.session(id) })
  }

  const generateMutation = useMutation({
    mutationFn: (analysisId: number) => generateClarificationSession(analysisId),
    onSuccess: () => {
      announcement.value = 'Questions generated.'
      invalidateSession()
    },
    onError: (error) => {
      if (clarificationProblemCode(error) === 'too_many_requests') {
        announcement.value = 'Please wait a moment before generating again.'
        return
      }
      announcement.value =
        extractProblemDetail(error as never)?.detail ?? 'Could not generate the questions.'
    },
  })

  const isGenerating = computed(() => generateMutation.isPending.value)

  const answerMutation = useMutation({
    mutationFn: (variables: { questionId: number; input: ClarificationAnswerInput }) =>
      answerClarificationQuestion(variables.questionId, variables.input),
    onSuccess: () => {
      announcement.value = 'Answer saved.'
      invalidateSession()
    },
    onError: (error) => {
      if (clarificationProblemCode(error) === 'answer_already_exists') {
        announcement.value = 'This question was already answered. Refreshing…'
        invalidateSession()
        return
      }
      announcement.value =
        extractProblemDetail(error as never)?.detail ?? 'Could not save the answer.'
    },
  })

  const reviewMutation = useMutation({
    mutationFn: (variables: { questionId: number; input: ClarificationReviewInput }) =>
      reviewClarificationAnswer(variables.questionId, variables.input),
    onSuccess: (answer: ClarificationAnswer, variables) => {
      if (variables.input.decision === 'accept') {
        announcement.value = 'Profile change applied.'
        invalidateSession()
        void queryClient.invalidateQueries({ queryKey: matchKeys.all })
        void queryClient.invalidateQueries({ queryKey: skillKeys.all })
        void queryClient.invalidateQueries({ queryKey: skillKeys.candidate() })
        return
      }
      if (
        variables.input.decision === 'edit' ||
        variables.input.decision === 'reject' ||
        variables.input.decision === 'skip'
      ) {
        announcement.value = 'Review saved.'
        invalidateSession()
        return
      }
      announcement.value = 'Proposal preview ready.'
    },
    onError: (error) => {
      if (clarificationProblemCode(error) === 'clarification_session_expired') {
        announcement.value = 'The clarification session expired.'
        return
      }
      if (clarificationProblemCode(error) === 'proposal_not_supported') {
        announcement.value = 'Answer recorded.'
        return
      }
      announcement.value =
        extractProblemDetail(error as never)?.detail ?? 'Could not submit the review.'
    },
  })

  const skipMutation = useMutation({
    mutationFn: (questionId: number) => skipClarificationQuestion(questionId),
    onSuccess: () => {
      announcement.value = 'Question skipped.'
      invalidateSession()
    },
    onError: (error) => {
      if (clarificationProblemCode(error) === 'clarification_session_expired') {
        announcement.value = 'The clarification session expired.'
        return
      }
      announcement.value =
        extractProblemDetail(error as never)?.detail ?? 'Could not skip the question.'
    },
  })

  function refetchSession(): void {
    void sessionQuery.refetch()
  }

  return {
    announcement,
    session,
    sessionQuery,
    isSessionLoading,
    isSessionError,
    sessionErrorDetail,
    sessionProblemCode,
    questions,
    totalQuestions,
    answeredCount,
    openQuestionCount,
    hasOpenQuestions,
    generableCount,
    answerMutation,
    reviewMutation,
    skipMutation,
    generateMutation,
    isGenerating,
    refetchSession,
  }
}
