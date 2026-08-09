<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { X } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'
import { extractProblemDetail } from '@/api/client'
import type {
  ClarificationAnswerInput,
  ClarificationProposal,
  ClarificationQuestion,
  ClarificationReviewInput,
} from '../types'
import {
  clarificationProblemCode,
  useClarificationSession,
} from '../composables/useClarificationSession'
import ClarificationQuestionStep from './ClarificationQuestionStep.vue'
import ClarificationReviewStep from './ClarificationReviewStep.vue'
import ClarificationStateViews from './ClarificationStateViews.vue'

const props = defineProps<{ analysisId: number }>()
const emit = defineEmits<{ close: [] }>()

const {
  announcement,
  isSessionLoading,
  isSessionError,
  sessionErrorDetail,
  sessionProblemCode,
  questions,
  totalQuestions,
  answerMutation,
  reviewMutation,
  skipMutation,
  generateMutation,
  isGenerating,
  refetchSession,
} = useClarificationSession(() => props.analysisId)

type ViewKind = 'question' | 'review'

const currentId = ref<number | null>(null)
const explicitView = ref<ViewKind | null>(null)
const completed = ref(false)
const expired = ref(false)
const proposals = ref<Record<number, ClarificationProposal>>({})

function isActionable(question: ClarificationQuestion): boolean {
  if (question.status === 'pending') return true
  if (question.status === 'answered') {
    const proposal = question.answer?.proposal
    if (
      proposal?.status === 'accepted' ||
      proposal?.status === 'rejected' ||
      proposal?.status === 'skipped'
    ) {
      return false
    }
    return true
  }
  return false
}

const actionableQuestions = computed(() => questions.value.filter(isActionable))

const currentQuestion = computed<ClarificationQuestion | null>(() => {
  if (currentId.value === null) return null
  return questions.value.find((question) => question.id === currentId.value) ?? null
})

const effectiveView = computed<ViewKind>(() => {
  if (explicitView.value !== null) return explicitView.value
  const question = currentQuestion.value
  if (question !== null && question.status !== 'pending') return 'review'
  return 'question'
})

const reviewProposal = computed<ClarificationProposal | null>(() => {
  const question = currentQuestion.value
  if (question === null) return null
  return question.answer?.proposal ?? proposals.value[question.id] ?? null
})

const progressText = computed(() => {
  const question = currentQuestion.value
  if (question === null) return ''
  return `Question ${question.question_no} of ${totalQuestions.value}`
})

const emptySession = computed(
  () => !isSessionLoading.value && !isSessionError.value && questions.value.length === 0,
)

const isAnswering = computed(() => answerMutation.isPending.value)
const isReviewing = computed(() => reviewMutation.isPending.value)
const isSkipping = computed(() => skipMutation.isPending.value)
const busy = computed(
  () => isAnswering.value || isReviewing.value || isSkipping.value || isGenerating.value,
)

const actionError = computed(() => {
  const error = answerMutation.error.value ?? reviewMutation.error.value ?? skipMutation.error.value
  return extractProblemDetail(error as never)?.detail ?? null
})

const previewErrorDetail = computed(() => {
  if (reviewMutation.error.value === null) return 'The proposed change could not be prepared.'
  return (
    extractProblemDetail(reviewMutation.error.value as never)?.detail ??
    'The proposed change could not be prepared.'
  )
})

function currentIndex(): number {
  return actionableQuestions.value.findIndex((question) => question.id === currentId.value)
}

function goBack(): void {
  const previous = actionableQuestions.value[currentIndex() - 1]
  if (previous === undefined) return
  currentId.value = previous.id
  explicitView.value = null
}

function advanceFromCurrent(): void {
  const next = actionableQuestions.value[currentIndex() + 1]
  if (next === undefined) {
    completed.value = true
    return
  }
  currentId.value = next.id
  explicitView.value = null
}

function submitAnswer(payload: ClarificationAnswerInput): void {
  const question = currentQuestion.value
  if (question === null || busy.value) return
  answerMutation.mutate(
    { questionId: question.id, input: payload },
    {
      onSuccess: () => {
        explicitView.value = 'review'
      },
    },
  )
}

function submitReview(input: ClarificationReviewInput): void {
  const question = currentQuestion.value
  if (question === null || busy.value) return
  reviewMutation.mutate(
    { questionId: question.id, input },
    {
      onSuccess: () => {
        if (input.decision === null || input.decision === undefined) return
        delete proposals.value[question.id]
        advanceFromCurrent()
      },
    },
  )
}

function previewProposal(question: ClarificationQuestion): void {
  if (proposals.value[question.id] !== undefined) return
  if (question.answer?.proposal !== null && question.answer?.proposal !== undefined) return
  if (reviewMutation.isPending.value) return
  reviewMutation.mutate(
    { questionId: question.id, input: { decision: null } },
    {
      onSuccess: (answer) => {
        if (answer.proposal !== null && answer.proposal !== undefined) {
          proposals.value[answer.question_id] = answer.proposal
          return
        }
        reviewMutation.reset()
        advanceFromCurrent()
      },
      onError: (error) => {
        if (clarificationProblemCode(error) !== 'proposal_not_supported') return
        reviewMutation.reset()
        advanceFromCurrent()
      },
    },
  )
}

function retryAction(): void {
  const question = currentQuestion.value
  if (question !== null && effectiveView.value === 'review') {
    delete proposals.value[question.id]
    previewProposal(question)
    return
  }
  refetchSession()
}

function submitSkip(): void {
  const question = currentQuestion.value
  if (question === null || busy.value) return
  skipMutation.mutate(question.id, {
    onSuccess: () => advanceFromCurrent(),
  })
}

function restart(): void {
  expired.value = false
  completed.value = false
  currentId.value = null
  explicitView.value = null
  proposals.value = {}
  generateQuestions()
}

function generateQuestions(): void {
  if (busy.value) return
  generateMutation.mutate(props.analysisId, {
    onSuccess: () => refetchSession(),
  })
}

function close(): void {
  if (!busy.value) emit('close')
}

watch(
  [actionableQuestions, () => isSessionLoading.value, () => isSessionError.value],
  ([list, loading, error]) => {
    if (loading || error || completed.value) return
    if (list.length === 0) return
    if (currentId.value !== null && list.some((question) => question.id === currentId.value)) {
      return
    }
    const index = list.findIndex((question) => question.id === currentId.value)
    if (index === -1) {
      currentId.value = list[0]?.id ?? null
      explicitView.value = null
      return
    }
    const next = list[index + 1]
    if (next !== undefined) {
      currentId.value = next.id
      explicitView.value = null
    } else {
      completed.value = true
    }
  },
  { immediate: true },
)

watch(
  [effectiveView, currentId],
  () => {
    const question = currentQuestion.value
    if (question === null || effectiveView.value !== 'review') return
    previewProposal(question)
  },
  { immediate: true },
)

watch(
  () => [
    answerMutation.error.value,
    reviewMutation.error.value,
    skipMutation.error.value,
    sessionProblemCode.value,
  ],
  ([answerError, reviewError, skipError, sessionCode]) => {
    const code =
      clarificationProblemCode(answerError) ??
      clarificationProblemCode(reviewError) ??
      clarificationProblemCode(skipError) ??
      sessionCode
    if (code === 'clarification_session_expired') {
      expired.value = true
    }
  },
)
</script>

<template>
  <section
    class="rounded-[var(--radius-xl)] bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised)]"
    aria-label="Clarify your profile"
  >
    <div
      class="flex items-center justify-between gap-3 border-b border-[var(--border-subtle)] p-5 sm:p-6"
    >
      <div>
        <h2 class="text-base font-semibold text-[var(--text-primary)]">Clarify your profile</h2>
        <p v-if="progressText" class="mt-0.5 text-sm text-[var(--text-secondary)]">
          {{ progressText }}
        </p>
      </div>
      <Button
        variant="ghost"
        size="sm"
        :disabled="busy"
        aria-label="Close clarification flow"
        @click="close"
      >
        <X class="size-4" aria-hidden="true" />
      </Button>
    </div>

    <div class="sr-only" aria-live="polite">{{ announcement }}</div>

    <div class="p-5 sm:p-6">
      <ClarificationStateViews v-if="isSessionLoading" kind="loading" />

      <ClarificationStateViews v-else-if="isGenerating" kind="loading" />

      <ClarificationStateViews v-else-if="expired" kind="expired" @restart="restart" />

      <ClarificationStateViews
        v-else-if="isSessionError"
        kind="error"
        :error-detail="sessionErrorDetail"
        @retry="refetchSession"
      />

      <ClarificationStateViews v-else-if="completed" kind="success" @close="close" />

      <ClarificationStateViews
        v-else-if="emptySession"
        kind="empty"
        @generate="generateQuestions"
        @close="close"
      />

      <template v-else-if="currentQuestion !== null">
        <ClarificationQuestionStep
          v-if="effectiveView === 'question'"
          :key="currentQuestion.id"
          :question="currentQuestion"
          :busy="busy"
          :error="actionError"
          :can-go-back="currentIndex() > 0"
          @submit="submitAnswer"
          @skip="submitSkip"
          @back="goBack"
        />
        <template v-else>
          <ClarificationReviewStep
            v-if="reviewProposal !== null"
            :key="currentQuestion.id"
            :question="currentQuestion"
            :proposal="reviewProposal"
            :busy="busy"
            :error="actionError"
            :can-go-back="currentIndex() > 0"
            @accept="submitReview({ decision: 'accept' })"
            @edit="(value: string) => submitReview({ decision: 'edit', edited_value: value })"
            @skip="submitReview({ decision: 'skip' })"
            @back="goBack"
          />
          <ClarificationStateViews v-else-if="!reviewMutation.isError" kind="loading" />
          <ClarificationStateViews
            v-else
            kind="error"
            :error-detail="previewErrorDetail"
            @retry="retryAction"
          />
        </template>
      </template>
    </div>
  </section>
</template>
