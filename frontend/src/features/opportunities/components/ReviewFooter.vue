<script setup lang="ts">
withDefaults(
  defineProps<{
    reviewed: number
    total: number
    showPrevious?: boolean
    primaryLabel: string
    primaryDisabled?: boolean
    pending?: boolean
    pendingLabel?: string
    blockingReason?: string | null
    finalStep?: boolean
  }>(),
  {
    showPrevious: false,
    primaryDisabled: false,
    pending: false,
    pendingLabel: 'Saving…',
    blockingReason: null,
    finalStep: false,
  },
)

const emit = defineEmits<{
  previous: []
  primary: []
}>()
</script>

<template>
  <footer class="review-footer">
    <button
      v-if="showPrevious"
      type="button"
      class="footer-secondary"
      :disabled="pending"
      @click="emit('previous')"
    >
      Previous
    </button>
    <span v-else class="footer-spacer" aria-hidden="true" />

    <div class="footer-status" aria-live="polite">
      <strong v-if="finalStep && reviewed === total">Review complete</strong>
      <strong v-else>{{ reviewed }} of {{ total }} reviewed</strong>
      <span v-if="pending">{{ pendingLabel }}</span>
      <span v-else-if="blockingReason">{{ blockingReason }}</span>
      <span
        class="footer-progress"
        role="progressbar"
        aria-label="Overall review progress"
        :aria-valuenow="reviewed"
        aria-valuemin="0"
        :aria-valuemax="total"
      >
        <span :style="{ transform: `scaleX(${total > 0 ? reviewed / total : 0})` }" />
      </span>
    </div>

    <button
      type="button"
      class="footer-primary"
      :disabled="primaryDisabled || pending"
      :aria-describedby="blockingReason ? 'review-footer-reason' : undefined"
      @click="emit('primary')"
    >
      {{ pending ? pendingLabel : primaryLabel }}
    </button>
    <span v-if="blockingReason" id="review-footer-reason" class="sr-only">
      {{ blockingReason }}
    </span>
  </footer>
</template>

<style scoped>
.review-footer {
  display: grid;
  position: relative;
  z-index: 5;
  grid-template-columns: minmax(0, 1fr);
  gap: 0.625rem;
  border: 1px solid var(--border-default);
  border-radius: var(--radius-xl);
  padding: 0.8125rem;
  background: var(--surface-primary);
}

.footer-primary,
.footer-secondary {
  min-height: 2.75rem;
  border-radius: var(--radius-lg);
  padding: 0.625rem 1rem;
  font-size: 0.875rem;
  font-weight: 650;
  cursor: pointer;
}

.footer-primary {
  border: none;
  background: var(--color-primary-600);
  color: var(--text-inverse);
  transition: all var(--duration-fast) var(--ease-out);
}

.footer-primary:hover:not(:disabled) {
  background: var(--color-primary-700);
}

.footer-secondary {
  border: 1px solid var(--border-default);
  background: var(--surface-primary);
  color: var(--text-primary);
  transition: all var(--duration-fast) var(--ease-out);
}

.footer-secondary:hover:not(:disabled) {
  background: var(--surface-secondary);
}

.footer-primary:focus-visible,
.footer-secondary:focus-visible {
  outline: 2px solid var(--color-primary-600);
  outline-offset: 2px;
}

.footer-primary:disabled,
.footer-secondary:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.footer-status {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  font-size: 0.75rem;
  line-height: 1.125rem;
  text-align: center;
}

.footer-status strong {
  color: var(--text-primary);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  font-weight: 650;
}

.footer-status span {
  margin-top: 0.125rem;
  color: var(--color-warning-600);
}

.footer-progress {
  display: block;
  width: min(8rem, 100%);
  height: 0.1875rem;
  margin-top: 0.375rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--surface-secondary);
}

.footer-progress > span {
  display: block;
  width: 100%;
  height: 100%;
  margin: 0;
  border-radius: inherit;
  background: var(--color-primary-600);
  transform-origin: left center;
  transition: transform var(--duration-fast) var(--ease-out);
}

.footer-spacer {
  display: none;
}

@media (min-width: 40rem) {
  .review-footer {
    grid-template-columns: minmax(8rem, auto) minmax(0, 1fr) minmax(10rem, auto);
    align-items: center;
  }

  .footer-spacer {
    display: block;
  }
}

@media (min-width: 64rem) {
  .review-footer {
    position: sticky;
    bottom: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .footer-primary,
  .footer-secondary,
  .footer-progress > span {
    transition: none;
  }
}
</style>
