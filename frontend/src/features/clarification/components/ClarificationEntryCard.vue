<script setup lang="ts">
import { computed } from 'vue'
import { HelpCircle } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { useClarificationSession } from '../composables/useClarificationSession'

const props = defineProps<{ analysisId: number }>()
const emit = defineEmits<{ open: [] }>()

const {
  isSessionLoading,
  isSessionError,
  sessionErrorDetail,
  questions,
  openQuestionCount,
  generableCount,
  refetchSession,
} = useClarificationSession(() => props.analysisId)

type EntryState = 'answer' | 'review' | 'generate' | null

const state = computed<EntryState>(() => {
  if (openQuestionCount.value > 0) return 'answer'
  if (questions.value.length > 0) return 'review'
  if (generableCount.value > 0) return 'generate'
  return null
})

const noun = computed(() => (openQuestionCount.value === 1 ? 'question' : 'questions'))

const heading = computed(() => {
  if (state.value === 'review') return 'Review a profile change'
  return 'Clarify your profile'
})

const detail = computed(() => {
  if (state.value === 'answer') {
    return `Answer ${openQuestionCount.value} quick ${noun.value} to improve this match.`
  }
  if (state.value === 'review') {
    return 'You have a pending profile change to review.'
  }
  return 'Generate a few quick questions to improve this match.'
})

const actionLabel = computed(() => {
  if (state.value === 'review') return 'Continue review'
  if (state.value === 'generate') return 'Generate questions'
  return 'Answer questions'
})
</script>

<template>
  <div
    v-if="isSessionLoading"
    class="rounded-[var(--radius-2xl)] bg-[var(--surface-primary)] p-5 shadow-[var(--shadow-neo-raised)]"
  >
    <div class="flex items-center gap-3">
      <Skeleton classes="size-10 rounded-full" />
      <div class="grid flex-1 gap-2">
        <Skeleton classes="h-3.5 w-40" />
        <Skeleton classes="h-3 w-64 max-w-full" />
      </div>
    </div>
  </div>

  <div
    v-else-if="isSessionError"
    class="flex flex-col gap-3 rounded-[var(--radius-2xl)] bg-[var(--surface-primary)] p-5 shadow-[var(--shadow-neo-raised)] sm:flex-row sm:items-center sm:justify-between"
  >
    <div class="flex items-start gap-3">
      <span
        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 shadow-[var(--shadow-neo-inset)]"
      >
        <HelpCircle class="size-5" aria-hidden="true" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Clarifications unavailable</h2>
        <p class="mt-1 text-sm text-slate-600">
          {{ sessionErrorDetail ?? 'Could not load your clarification questions.' }}
        </p>
      </div>
    </div>
    <Button variant="outline" size="sm" @click="refetchSession">Retry</Button>
  </div>

  <div
    v-else-if="state !== null"
    class="flex flex-col gap-4 rounded-[var(--radius-2xl)] bg-[var(--color-primary-50)] p-5 shadow-[var(--shadow-neo-raised)] sm:flex-row sm:items-center sm:justify-between sm:p-6"
  >
    <div class="flex items-start gap-3">
      <span
        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--color-primary-100)] text-[var(--color-primary-700)] shadow-[var(--shadow-neo-inset)]"
      >
        <HelpCircle class="size-5" aria-hidden="true" />
      </span>
      <div>
        <h2 class="text-sm font-semibold text-slate-900">{{ heading }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ detail }}</p>
      </div>
    </div>
    <Button @click="emit('open')">{{ actionLabel }}</Button>
  </div>
</template>
