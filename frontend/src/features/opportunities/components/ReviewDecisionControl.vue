<script setup lang="ts">
import { Check, Pencil, RotateCcw, X } from '@lucide/vue'
import type { ReviewDecisionValue } from '../types'

withDefaults(
  defineProps<{
    decision: ReviewDecisionValue
    expanded?: boolean
    saving?: boolean
    allowEdit?: boolean
  }>(),
  {
    expanded: false,
    saving: false,
    allowEdit: true,
  },
)

const emit = defineEmits<{
  keep: []
  edit: []
  exclude: []
  restore: []
  change: []
}>()
</script>

<template>
  <div class="decision-panel">
    <div
      v-if="decision === 'pending' || expanded"
      class="decision-actions"
      role="group"
      aria-label="Review decision"
    >
      <button type="button" class="action-btn action-keep" :disabled="saving" @click="emit('keep')">
        <Check class="action-icon" aria-hidden="true" />
        Include
      </button>
      <button
        v-if="allowEdit"
        type="button"
        class="action-btn action-edit"
        :disabled="saving"
        @click="emit('edit')"
      >
        <Pencil class="action-icon" aria-hidden="true" />
        Edit
      </button>
      <button
        type="button"
        class="action-btn action-exclude"
        :disabled="saving"
        @click="emit('exclude')"
      >
        <X class="action-icon" aria-hidden="true" />
        Exclude
      </button>
    </div>

    <div
      v-else-if="decision === 'rejected' || decision === 'keep_blank'"
      class="decision-status decision-excluded-status"
    >
      <span class="status-summary">
        <X class="status-icon" aria-hidden="true" />
        Excluded from opportunity
      </span>
      <button type="button" class="action-link" :disabled="saving" @click="emit('restore')">
        <RotateCcw class="action-icon-sm" aria-hidden="true" />
        Undo
      </button>
    </div>

    <div v-else class="decision-status decision-included-status">
      <span class="status-summary">
        <Check class="status-icon" aria-hidden="true" />
        {{ decision === 'edited' ? 'Edited value will be used' : 'Included in opportunity' }}
      </span>
      <button type="button" class="action-link" :disabled="saving" @click="emit('change')">
        {{ decision === 'edited' ? 'View change' : 'Change' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.decision-panel {
  flex-shrink: 0;
}

.decision-actions {
  display: inline-flex;
  overflow: hidden;
  border: 1px solid var(--border-default);
  border-radius: var(--radius-md);
  background: var(--surface-elevated);
  box-shadow:
    0 1px 0 rgb(255 255 255 / 0.9) inset,
    0 0.25rem 0.75rem rgb(16 24 40 / 0.05);
}

.action-btn {
  display: inline-flex;
  min-height: 2.75rem;
  align-items: center;
  justify-content: center;
  gap: 0.375rem;
  border: 0;
  border-right: 1px solid var(--border-default);
  padding: 0.5rem 0.8125rem;
  background: transparent;
  color: var(--text-primary);
  font-size: 0.8125rem;
  font-weight: 650;
  cursor: pointer;
  transition:
    background-color 120ms ease,
    color 120ms ease;
}

.action-btn:last-child {
  border-right: 0;
}

.action-btn:hover {
  background: var(--surface-inset);
}

.action-keep {
  background: var(--color-primary-50);
  color: var(--color-primary-800);
}

.action-keep:hover {
  background: color-mix(in srgb, var(--color-primary-50) 60%, var(--color-primary-200));
  color: var(--color-primary-800);
}

.action-exclude {
  color: var(--text-muted);
}

.action-exclude:hover {
  background: var(--color-error-50);
  color: var(--color-error-600);
}

.action-icon {
  width: 1rem;
  height: 1rem;
}

.action-icon-sm {
  width: 0.875rem;
  height: 0.875rem;
}

.action-btn:disabled,
.action-link:disabled {
  cursor: wait;
  opacity: 0.55;
}

.action-link {
  display: inline-flex;
  min-height: 2rem;
  align-items: center;
  gap: 0.3rem;
  border: 0;
  border-radius: var(--radius-md);
  padding: 0.375rem 0.625rem;
  background: transparent;
  color: var(--color-primary-800);
  font-size: 0.75rem;
  font-weight: 650;
  cursor: pointer;
  transition: background-color 120ms ease;
}

.action-link:hover {
  background: var(--color-primary-50);
}

.action-btn:focus-visible,
.action-link:focus-visible {
  outline: 2px solid var(--color-primary-600);
  outline-offset: 2px;
}

.decision-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 2.5rem;
  border-radius: var(--radius-md);
  padding: 0.375rem 0.5rem 0.375rem 0.75rem;
}

.decision-included-status {
  background: color-mix(in srgb, var(--color-success-50) 72%, var(--surface-primary));
}

.decision-excluded-status {
  background: color-mix(in srgb, var(--surface-inset) 84%, var(--surface-primary));
}

.status-summary {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.8125rem;
  font-weight: 650;
}

.status-icon {
  width: 1rem;
  height: 1rem;
}

.decision-included-status .status-summary {
  color: var(--color-success-600);
}

.decision-excluded-status .status-summary {
  color: var(--color-error-600);
}

@media (prefers-reduced-motion: reduce) {
  .action-btn,
  .action-link {
    transition: none;
  }
}
</style>
