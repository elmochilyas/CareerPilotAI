<script setup lang="ts">
import { computed } from 'vue'
import type { JobSuggestion } from '../types'
import { getSuggestionField, suggestionLabel } from '../utils/suggestionFormatters'

const props = defineProps<{
  suggestion: JobSuggestion
  saving: boolean
}>()

const emit = defineEmits<{
  keep: [id: number]
  edit: [id: number, value: string]
  exclude: [id: number]
  restore: [id: number]
}>()

const displayValue = computed(() =>
  getSuggestionField({
    ...props.suggestion,
    review_decision:
      props.suggestion.review_decision === 'rejected' ||
      props.suggestion.review_decision === 'keep_blank'
        ? 'accepted'
        : props.suggestion.review_decision,
  }),
)

const label = computed(() => suggestionLabel(props.suggestion.type))

const isAccepted = computed(
  () =>
    props.suggestion.review_decision === 'accepted' ||
    props.suggestion.review_decision === 'edited',
)
const isRejected = computed(
  () =>
    props.suggestion.review_decision === 'rejected' ||
    props.suggestion.review_decision === 'keep_blank',
)
const isPending = computed(() => props.suggestion.review_decision === 'pending')

function handleKeep(): void {
  emit('keep', props.suggestion.id)
}

function handleExclude(): void {
  emit('exclude', props.suggestion.id)
}

function handleRestore(): void {
  emit('restore', props.suggestion.id)
}
</script>

<template>
  <div
    class="flex items-center justify-between gap-3 rounded-lg border p-3 transition-colors"
    :class="{
      'border-slate-200 bg-white': isPending,
      'border-emerald-200 bg-emerald-50': isAccepted,
      'border-slate-200 bg-slate-50 opacity-60': isRejected,
    }"
  >
    <div class="min-w-0">
      <span class="text-xs font-medium uppercase tracking-wide text-slate-400">
        {{ label }}
      </span>
      <p class="mt-0.5 truncate text-sm text-slate-800">{{ displayValue }}</p>
    </div>
    <div class="flex shrink-0 items-center gap-1">
      <template v-if="isPending">
        <button
          type="button"
          :disabled="saving"
          class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-not-allowed disabled:opacity-50"
          @click="handleKeep"
        >
          Keep
        </button>
        <button
          type="button"
          :disabled="saving"
          class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-50"
          @click="handleExclude"
        >
          Exclude
        </button>
      </template>
      <template v-else>
        <button
          type="button"
          :disabled="saving"
          class="text-xs font-medium text-primary-600 transition-colors hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-50"
          @click="handleRestore"
        >
          Undo
        </button>
      </template>
    </div>
  </div>
</template>
