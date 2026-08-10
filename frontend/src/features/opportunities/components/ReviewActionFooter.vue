<script setup lang="ts">
withDefaults(
  defineProps<{
    showPrevious?: boolean
    currentStep: number
    totalSteps: number
    reviewedCount: number
    totalCount: number
    primaryLabel: string
    primaryDisabled?: boolean
    pending?: boolean
    pendingLabel?: string
    blockingReason?: string | null
  }>(),
  {
    showPrevious: false,
    primaryDisabled: false,
    pending: false,
    pendingLabel: 'Saving…',
    blockingReason: null,
  },
)

const emit = defineEmits<{
  previous: []
  primary: []
}>()
</script>

<template>
  <footer class="action-footer">
    <button
      v-if="showPrevious"
      type="button"
      class="footer-btn footer-btn-secondary"
      :disabled="pending"
      @click="emit('previous')"
    >
      Previous
    </button>
    <span v-else class="footer-spacer" aria-hidden="true" />

    <div class="footer-center" aria-live="polite">
      <span class="footer-step">Step {{ currentStep }} of {{ totalSteps }}</span>
      <span class="footer-sep" aria-hidden="true">·</span>
      <strong class="footer-progress-text">
        {{ reviewedCount }} of {{ totalCount }} reviewed
      </strong>
      <div
        class="footer-progress-bar"
        role="progressbar"
        :aria-label="`${reviewedCount} of ${totalCount} review items complete`"
        :aria-valuenow="reviewedCount"
        aria-valuemin="0"
        :aria-valuemax="totalCount"
      >
        <span
          :style="{
            transform: `scaleX(${totalCount > 0 ? reviewedCount / totalCount : 0})`,
          }"
        />
      </div>
    </div>

    <button
      type="button"
      class="footer-btn footer-btn-primary"
      :disabled="primaryDisabled || pending"
      :aria-describedby="blockingReason ? 'footer-block-reason' : undefined"
      @click="emit('primary')"
    >
      {{ pending ? pendingLabel : primaryLabel }}
    </button>

    <p v-if="blockingReason" id="footer-block-reason" class="sr-only">
      {{ blockingReason }}
    </p>
  </footer>
</template>

<style scoped>
.action-footer {
  display: grid;
  position: relative;
  z-index: 5;
  grid-template-columns: minmax(0, 1fr);
  gap: 0.625rem;
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  padding: 0.8125rem;
  background: var(--surface-primary);
  box-shadow: var(--shadow-neo-raised-sm);
}

.footer-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  border-radius: var(--radius-md);
  padding: 0.625rem 1rem;
  font-size: 0.875rem;
  font-weight: 650;
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.footer-btn-primary {
  border: none;
  background: var(--color-primary-600);
  color: var(--text-inverse);
  transition: background-color var(--duration-fast) var(--ease-default);
}

.footer-btn-primary:hover:not(:disabled) {
  background: var(--color-primary-700);
}

.footer-btn-secondary {
  border: 1px solid var(--border-default);
  background: var(--surface-primary);
  color: var(--text-primary);
  transition: background-color var(--duration-fast) var(--ease-default);
}

.footer-btn-secondary:hover:not(:disabled) {
  background: var(--surface-secondary);
}

.footer-btn:focus-visible {
  outline: 2px solid var(--color-primary-600);
  outline-offset: 2px;
}

.footer-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.footer-center {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  color: var(--text-muted);
  font-size: 0.75rem;
  line-height: 1.125rem;
  text-align: center;
}

.footer-step {
  font-variant-numeric: tabular-nums;
}

.footer-sep {
  color: var(--text-tertiary);
}

.footer-progress-text {
  color: var(--text-primary);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  font-weight: 640;
}

.footer-progress-bar {
  width: 100%;
  flex-basis: 100%;
  height: 0.1875rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--surface-inset);
}

.footer-progress-bar > span {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: inherit;
  background: var(--color-primary-600);
  transform-origin: left center;
  transition: transform 150ms ease;
}

.footer-spacer {
  display: none;
}

@media (min-width: 40rem) {
  .action-footer {
    grid-template-columns: minmax(8rem, auto) minmax(0, 1fr) minmax(10rem, auto);
    align-items: center;
  }

  .footer-center {
    flex-wrap: nowrap;
  }

  .footer-progress-bar {
    flex-basis: auto;
    width: 6rem;
  }

  .footer-spacer {
    display: block;
  }
}

@media (min-width: 64rem) {
  .action-footer {
    position: sticky;
    bottom: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .footer-btn,
  .footer-progress-bar > span {
    transition: none;
  }
}
</style>
