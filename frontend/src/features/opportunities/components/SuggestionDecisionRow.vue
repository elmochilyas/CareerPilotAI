<script setup lang="ts">
import { Check, Quote, X } from '@lucide/vue'
import type { JobSuggestion } from '../types'

defineProps<{
  suggestion: JobSuggestion
  label: string
  value: unknown
}>()

const emit = defineEmits<{
  keep: [suggestionId: number]
  remove: [suggestionId: number]
}>()
</script>

<template>
  <article
    class="suggestion-row"
    :class="{
      'suggestion-row-accepted': suggestion.review_decision === 'accepted',
      'suggestion-row-rejected': suggestion.review_decision === 'rejected',
    }"
  >
    <div class="min-w-0 flex-1">
      <div class="flex flex-wrap items-center gap-2">
        <p class="suggestion-label">{{ label }}</p>
        <span
          v-if="suggestion.review_decision !== 'pending'"
          class="suggestion-state"
          :class="
            suggestion.review_decision === 'rejected'
              ? 'suggestion-state-rejected'
              : 'suggestion-state-included'
          "
        >
          {{ suggestion.review_decision === 'rejected' ? 'Excluded' : 'Included' }}
        </span>
      </div>
      <p class="suggestion-value">
        {{ value ?? 'Not provided' }}
      </p>
      <details v-if="suggestion.source_evidence" class="suggestion-evidence">
        <summary class="evidence-summary">
          <Quote class="size-3.5" aria-hidden="true" />
          View source evidence
        </summary>
        <p class="evidence-copy">
          {{ suggestion.source_evidence }}
        </p>
      </details>
    </div>

    <div class="flex shrink-0 flex-wrap gap-2" aria-label="Review decision">
      <button
        type="button"
        class="decision-button decision-keep"
        :aria-pressed="suggestion.review_decision === 'accepted'"
        @click="emit('keep', suggestion.id)"
      >
        <Check class="size-4" aria-hidden="true" />
        Keep
      </button>
      <button
        type="button"
        class="decision-button decision-remove"
        :aria-pressed="suggestion.review_decision === 'rejected'"
        @click="emit('remove', suggestion.id)"
      >
        <X class="size-4" aria-hidden="true" />
        Remove
      </button>
    </div>
  </article>
</template>

<style scoped>
.suggestion-row {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  border-bottom: 1px solid var(--border-default);
  background: transparent;
  padding: 1rem 0;
}

.suggestion-row-accepted {
  border-bottom-color: var(--color-success-200);
  background: var(--color-success-50);
}

.suggestion-row-rejected {
  border-bottom-color: var(--color-error-200);
  background: var(--color-error-50);
}

.suggestion-label {
  color: var(--text-muted);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.125rem;
  text-transform: capitalize;
}

.suggestion-state {
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.125rem;
}

.suggestion-state-included {
  color: var(--color-success-700);
}

.suggestion-state-rejected {
  color: var(--color-error-700);
}

.suggestion-value {
  margin-top: 0.5rem;
  overflow-wrap: anywhere;
  color: var(--text-primary);
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.5rem;
}

.suggestion-evidence {
  margin-top: 0.75rem;
  color: var(--text-muted);
  font-size: 0.75rem;
}

.evidence-copy {
  margin-top: 0.5rem;
  border-left: 2px solid var(--border-default);
  padding-left: 0.75rem;
  color: var(--text-primary);
  line-height: 1.25rem;
}

.decision-button {
  display: inline-flex;
  min-height: 2.75rem;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-md);
  padding: 0.45rem 0.75rem;
  background: var(--surface-primary);
  color: var(--text-muted);
  font-size: 0.76rem;
  font-weight: 700;
}

.decision-button:focus-visible,
.evidence-summary:focus-visible {
  outline: 2px solid var(--color-primary-600);
  outline-offset: 2px;
}

.decision-keep:hover,
.decision-keep[aria-pressed='true'] {
  border-color: var(--color-success-200);
  background: var(--color-success-50);
  color: var(--color-success-700);
}

.decision-remove:hover,
.decision-remove[aria-pressed='true'] {
  border-color: var(--color-error-200);
  background: var(--color-error-50);
  color: var(--color-error-700);
}

.evidence-summary {
  display: inline-flex;
  cursor: pointer;
  align-items: center;
  gap: 0.35rem;
  border-radius: var(--radius-sm);
  font-weight: 650;
}

@media (min-width: 40rem) {
  .suggestion-row {
    flex-direction: row;
    align-items: flex-start;
    padding: 1.25rem 0;
  }
}
</style>
