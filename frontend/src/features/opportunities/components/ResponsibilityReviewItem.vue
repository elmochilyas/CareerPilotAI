<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import type { JobSuggestion, ReviewDecisionValue } from '../types'
import ReviewActionsMenu from './ReviewActionsMenu.vue'
import SourceBadge from './SourceBadge.vue'

const props = withDefaults(
  defineProps<{
    suggestion: JobSuggestion
    index: number
    saving?: boolean
  }>(),
  {
    saving: false,
  },
)

const emit = defineEmits<{
  include: [suggestionId: number]
  edit: [suggestionId: number, editedValue: Record<string, unknown>]
  exclude: [suggestionId: number]
  restore: [suggestionId: number]
  viewOriginal: [suggestionId: number]
}>()

const isEditing = shallowRef(false)
const draftText = shallowRef('')

const itemNumber = computed(() => String(props.index + 1).padStart(2, '0'))

const responsibilityText = computed(() => {
  const value =
    props.suggestion.review_decision === 'edited' && props.suggestion.edited_value
      ? props.suggestion.edited_value
      : props.suggestion.extracted_value

  return String(value.text ?? '')
})

const decision = computed<ReviewDecisionValue>(() => props.suggestion.review_decision)

const isIncluded = computed(() => decision.value === 'accepted')
const isEdited = computed(() => decision.value === 'edited')
const isExcluded = computed(() => decision.value === 'rejected' || decision.value === 'keep_blank')
const isPending = computed(() => decision.value === 'pending')

const hasEditedValue = computed(() => props.suggestion.edited_value !== null)

const statusLabel = computed(() => {
  if (isExcluded.value) return 'Excluded from opportunity'
  if (isEdited.value) return 'Edited value will be used'
  if (isIncluded.value) return 'Included in opportunity'
  return 'Not reviewed'
})

const statusClass = computed(() => {
  if (isExcluded.value) return 'status-excluded'
  if (isEdited.value) return 'status-edited'
  if (isIncluded.value) return 'status-included'
  return 'status-pending'
})

function handleInclude(): void {
  emit('include', props.suggestion.id)
}

function handleEdit(): void {
  draftText.value = responsibilityText.value
  isEditing.value = true
}

function cancelEdit(): void {
  draftText.value = responsibilityText.value
  isEditing.value = false
}

function saveEdit(): void {
  const text = draftText.value.trim()

  if (!text) return

  emit('edit', props.suggestion.id, { text })
  isEditing.value = false
}

function handleExclude(): void {
  emit('exclude', props.suggestion.id)
}

function handleRestore(): void {
  emit('restore', props.suggestion.id)
}

function handleViewOriginal(): void {
  emit('viewOriginal', props.suggestion.id)
}

watch(
  responsibilityText,
  (text) => {
    if (!isEditing.value) {
      draftText.value = text
    }
  },
  { immediate: true },
)
</script>

<template>
  <article
    class="responsibility-item"
    :class="[statusClass]"
    :aria-label="`Responsibility ${itemNumber}: ${statusLabel}`"
    :aria-busy="saving"
  >
    <span class="item-number" aria-hidden="true">{{ itemNumber }}</span>

    <div class="item-content">
      <form v-if="isEditing" class="item-edit-form" @submit.prevent="saveEdit">
        <label :for="`responsibility-${suggestion.id}-edit`">Edit responsibility</label>
        <textarea
          :id="`responsibility-${suggestion.id}-edit`"
          v-model="draftText"
          :name="`responsibility-${suggestion.id}-edit`"
          rows="4"
          maxlength="2000"
          autocomplete="off"
          required
        />
        <div class="item-edit-actions">
          <button type="submit" class="edit-save" :disabled="!draftText.trim()">Save change</button>
          <button type="button" class="edit-cancel" @click="cancelEdit">Cancel</button>
        </div>
      </form>

      <p v-else class="item-text">{{ responsibilityText }}</p>

      <div v-if="!isEditing" class="item-meta">
        <span class="item-status" :class="`item-status-${statusClass}`">
          <span class="status-marker" aria-hidden="true">
            <span v-if="isIncluded" class="marker-check">✓</span>
            <span v-else-if="isEdited" class="marker-edit">✎</span>
            <span v-else-if="isExcluded" class="marker-cross">×</span>
            <span v-else class="marker-dot">•</span>
          </span>
          <span class="status-label">{{ statusLabel }}</span>
        </span>

        <SourceBadge
          v-if="suggestion.source_evidence"
          label="Responsibilities section"
          :excerpt="suggestion.source_evidence"
        />
      </div>
    </div>

    <div v-if="!isEditing" class="item-actions">
      <button
        v-if="isPending || isIncluded"
        type="button"
        class="action-include"
        :class="{ 'action-include-active': isIncluded }"
        :aria-pressed="isIncluded"
        :disabled="saving"
        @click="handleInclude"
      >
        {{ isIncluded ? 'Included' : 'Include' }}
      </button>

      <button
        v-if="isExcluded"
        type="button"
        class="action-restore"
        :disabled="saving"
        @click="handleRestore"
      >
        Undo remove
      </button>

      <ReviewActionsMenu
        v-if="!saving"
        :decision="decision"
        :has-edited-value="hasEditedValue"
        @edit="handleEdit"
        @exclude="handleExclude"
        @restore="handleRestore"
        @view-original="handleViewOriginal"
      />
    </div>
  </article>
</template>

<style scoped>
.responsibility-item {
  display: grid;
  grid-template-columns: 2.5rem minmax(0, 1fr) auto;
  gap: 1rem;
  padding: 1.5rem 0;
  border-bottom: 1px solid var(--cp-divider);
  transition: opacity 160ms ease;
}

.responsibility-item:first-of-type {
  padding-top: 0;
}

.responsibility-item:hover {
  transform: translateX(0.125rem);
}

.item-number {
  padding-top: 0.125rem;
  color: var(--cp-text-faint);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  font-weight: 650;
  letter-spacing: 0.08em;
  line-height: 1.5rem;
}

.item-content {
  min-width: 0;
}

.item-edit-form {
  display: grid;
  gap: 0.625rem;
}

.item-edit-form label {
  color: var(--cp-ink);
  font-size: 0.8125rem;
  font-weight: 650;
}

.item-edit-form textarea {
  width: 100%;
  min-height: 7rem;
  resize: vertical;
  border: 1px solid var(--cp-border-strong);
  border-radius: 0.5rem;
  background: var(--cp-surface);
  padding: 0.75rem;
  color: var(--cp-ink);
  font: inherit;
  line-height: 1.5;
}

.item-edit-form textarea:focus-visible,
.item-edit-actions button:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 2px;
}

.item-edit-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.item-edit-actions button {
  min-height: 2.75rem;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  font-weight: 650;
  cursor: pointer;
}

.edit-save {
  border: 0;
  background: var(--cp-primary);
  color: var(--cp-text-inverse);
}

.edit-save:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.edit-cancel {
  border: 1px solid var(--cp-border);
  background: var(--cp-surface);
  color: var(--cp-text);
}

.item-text {
  max-width: 72ch;
  overflow-wrap: anywhere;
  color: var(--cp-ink);
  font-size: 1rem;
  font-weight: 450;
  letter-spacing: -0.006em;
  line-height: 1.625rem;
  text-wrap: pretty;
}

.status-excluded .item-text {
  color: var(--cp-text-muted);
  text-decoration: line-through;
  text-decoration-color: var(--cp-danger-border);
}

.item-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
  margin-top: 0.625rem;
}

.item-status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  font-weight: 620;
  line-height: 1.125rem;
}

.status-marker {
  display: grid;
  width: 1rem;
  height: 1rem;
  flex: 0 0 auto;
  place-items: center;
  border: 1px solid var(--cp-border-strong);
  border-radius: 999px;
  color: var(--cp-text-muted);
  font-size: 0.5625rem;
  line-height: 1;
}

.item-status-status-included .status-marker {
  border-color: var(--cp-success-border);
  background: var(--cp-success-soft);
  color: var(--cp-success);
}

.item-status-status-included {
  color: var(--cp-success);
}

.item-status-status-edited .status-marker {
  border-color: var(--cp-primary-border);
  background: var(--cp-primary-soft);
  color: var(--cp-primary);
}

.item-status-status-edited {
  color: var(--cp-primary);
}

.item-status-status-excluded .status-marker {
  border-color: var(--cp-danger-border);
  background: var(--cp-danger-soft);
  color: var(--cp-danger);
}

.item-status-status-excluded {
  color: var(--cp-danger);
}

.status-label {
  white-space: nowrap;
}

.item-actions {
  display: flex;
  align-items: flex-start;
  gap: 0.25rem;
}

.action-include {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  border: 0;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: transparent;
  color: var(--cp-text-muted);
  font-size: 0.8125rem;
  font-weight: 620;
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
  white-space: nowrap;
}

.action-include:hover {
  background: var(--cp-primary-soft);
  color: var(--cp-primary);
}

.action-include-active {
  color: var(--cp-success);
}

.action-include-active:hover {
  background: var(--cp-success-soft);
  color: var(--cp-success);
}

.action-restore {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  border: 0;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: transparent;
  color: var(--cp-primary);
  font-size: 0.8125rem;
  font-weight: 620;
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
  white-space: nowrap;
}

.action-restore:hover {
  background: var(--cp-primary-soft);
}

.action-include:focus-visible,
.action-restore:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 2px;
}

.action-include:active,
.action-restore:active {
  opacity: 0.8;
}

.action-include:disabled,
.action-restore:disabled {
  cursor: wait;
  opacity: 0.55;
}

@media (max-width: 47.999rem) {
  .responsibility-item {
    grid-template-columns: 2rem minmax(0, 1fr);
    gap: 0.75rem;
    padding: 1.25rem 0;
  }

  .item-actions {
    grid-column: 2;
    justify-content: space-between;
    width: 100%;
  }

  .action-include,
  .action-restore {
    min-height: 2.75rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .responsibility-item {
    transition: none;
  }

  .responsibility-item:hover {
    transform: none;
  }
}
</style>
