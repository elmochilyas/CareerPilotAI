<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import type { Component } from 'vue'
import SourceBadge from './SourceBadge.vue'
import ReviewDecisionControl from './ReviewDecisionControl.vue'
import type { JobSuggestion } from '../types'

const props = withDefaults(
  defineProps<{
    suggestion: JobSuggestion
    label: string
    value: unknown
    icon?: Component
    sourceLabel?: string
    saving?: boolean
  }>(),
  {
    icon: undefined,
    sourceLabel: 'Original description',
    saving: false,
  },
)

const emit = defineEmits<{
  keep: [suggestionId: number]
  edit: [suggestionId: number, value: string]
  exclude: [suggestionId: number]
  restore: [suggestionId: number]
}>()

const isEditing = shallowRef(false)
const isChanging = shallowRef(false)
const draftValue = shallowRef('')

const displayValue = computed(() => {
  if (props.value === null || props.value === undefined || props.value === '') {
    return 'Not provided'
  }
  if (typeof props.value === 'boolean') {
    return props.value ? 'Yes' : 'No'
  }
  if (typeof props.value === 'object') {
    return Object.values(props.value)
      .filter((value) => ['string', 'number', 'boolean'].includes(typeof value))
      .map(String)
      .join(' · ')
  }
  return String(props.value)
})

const isLongValue = computed(() => displayValue.value.length > 90)

watch(
  displayValue,
  (value) => {
    if (!isEditing.value) {
      draftValue.value = value === 'Not provided' ? '' : value
    }
  },
  { immediate: true },
)

function startEdit(): void {
  isChanging.value = true
  isEditing.value = true
}

function cancelEdit(): void {
  isEditing.value = false
  isChanging.value = false
  draftValue.value = displayValue.value === 'Not provided' ? '' : displayValue.value
}

function saveEdit(): void {
  const value = draftValue.value.trim()
  if (!value) return
  emit('edit', props.suggestion.id, value)
  isEditing.value = false
  isChanging.value = false
}

function keep(): void {
  emit('keep', props.suggestion.id)
  isChanging.value = false
}

function exclude(): void {
  emit('exclude', props.suggestion.id)
  isChanging.value = false
}
</script>

<template>
  <article
    class="scalar-review-item"
    :class="{
      'scalar-review-item-pending': suggestion.review_decision === 'pending',
      'scalar-review-item-complete': suggestion.review_decision !== 'pending',
      'scalar-review-item-included':
        suggestion.review_decision === 'accepted' || suggestion.review_decision === 'resolved',
      'scalar-review-item-edited': suggestion.review_decision === 'edited',
      'scalar-review-item-excluded':
        suggestion.review_decision === 'rejected' || suggestion.review_decision === 'keep_blank',
    }"
  >
    <div class="scalar-main">
      <div class="scalar-label-row">
        <component :is="icon" v-if="icon" class="scalar-icon" aria-hidden="true" />
        <h3 class="scalar-label">{{ label }}</h3>
      </div>

      <form v-if="isEditing" class="scalar-edit-form" @submit.prevent="saveEdit">
        <label :for="`suggestion-${suggestion.id}-edit`">Edit {{ label.toLowerCase() }}</label>
        <textarea
          v-if="isLongValue"
          :id="`suggestion-${suggestion.id}-edit`"
          v-model="draftValue"
          :name="`suggestion-${suggestion.id}-edit`"
          autocomplete="off"
          rows="4"
        />
        <input
          v-else
          :id="`suggestion-${suggestion.id}-edit`"
          v-model="draftValue"
          :name="`suggestion-${suggestion.id}-edit`"
          type="text"
          autocomplete="off"
        />
        <div class="scalar-edit-actions">
          <button type="submit" class="edit-save" :disabled="saving || !draftValue.trim()">
            Save change
          </button>
          <button type="button" class="edit-cancel" :disabled="saving" @click="cancelEdit">
            Cancel
          </button>
        </div>
      </form>

      <template v-else>
        <p class="scalar-value">{{ displayValue }}</p>
        <SourceBadge :label="sourceLabel" :excerpt="suggestion.source_evidence" />
      </template>
    </div>

    <ReviewDecisionControl
      v-if="!isEditing"
      :decision="suggestion.review_decision"
      :expanded="isChanging"
      :saving="saving"
      @keep="keep"
      @edit="startEdit"
      @exclude="exclude"
      @restore="emit('restore', suggestion.id)"
      @change="isChanging = true"
    />
  </article>
</template>

<style scoped>
.scalar-review-item {
  position: relative;
  margin-top: 0.75rem;
  border: 1px solid transparent;
  border-radius: var(--radius-xl);
  padding: 1.125rem;
  background: var(--surface-primary);
  box-shadow: var(--shadow-neo-raised-sm);
  transition:
    border-color var(--duration-fast) var(--ease-out),
    background-color var(--duration-fast) var(--ease-out),
    opacity var(--duration-fast) var(--ease-out);
}

.scalar-review-item::before {
  position: absolute;
  top: 0.875rem;
  bottom: 0.875rem;
  left: 0;
  width: 3px;
  border-radius: 0 3px 3px 0;
  background: var(--border-default);
  content: '';
}

.scalar-review-item:hover {
  border-color: var(--border-default);
}

.scalar-review-item-complete {
  padding-top: 1rem;
  padding-bottom: 1rem;
}

.scalar-review-item-included {
  background: var(--color-success-50);
}

.scalar-review-item-included::before {
  background: var(--color-success-600);
}

.scalar-review-item-edited {
  background: var(--color-warning-50);
}

.scalar-review-item-edited::before {
  background: var(--color-primary-600);
}

.scalar-review-item-excluded {
  background: var(--color-error-50);
}

.scalar-review-item-excluded::before {
  background: var(--color-error-600);
}

.scalar-review-item-excluded .scalar-main {
  opacity: 0.58;
}

.scalar-main {
  min-width: 0;
}

.scalar-label-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.scalar-icon {
  width: 0.9375rem;
  height: 0.9375rem;
  color: var(--color-primary-600);
}

.scalar-label {
  color: var(--text-primary);
  font-size: 0.8125rem;
  font-weight: 640;
  line-height: 1.25rem;
}

.scalar-value {
  max-width: 42rem;
  margin-top: 0.5rem;
  overflow-wrap: anywhere;
  color: var(--text-primary);
  font-size: 1.0625rem;
  font-weight: 580;
  letter-spacing: -0.008em;
  line-height: 1.625rem;
}

.scalar-edit-form {
  display: grid;
  gap: 0.5rem;
  margin-top: 0.75rem;
}

.scalar-edit-form label {
  color: var(--text-primary);
  font-size: 0.8125rem;
  font-weight: 650;
}

.scalar-edit-form input,
.scalar-edit-form textarea {
  width: 100%;
  min-height: 2.75rem;
  border: 1px solid var(--border-default);
  border-radius: var(--radius-lg);
  background: var(--surface-primary);
  padding: 0.625rem 0.75rem;
  color: var(--text-primary);
  font-size: 0.875rem;
  line-height: 1.375rem;
}

.scalar-edit-form textarea {
  min-height: 7rem;
  resize: vertical;
}

.scalar-edit-form input:focus-visible,
.scalar-edit-form textarea:focus-visible,
.scalar-edit-actions button:focus-visible {
  outline: 2px solid var(--color-primary-600);
  outline-offset: 2px;
}

.scalar-edit-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.scalar-edit-actions button {
  min-height: 2.75rem;
  border-radius: var(--radius-lg);
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  font-weight: 650;
  cursor: pointer;
}

.edit-save {
  border: none;
  background: var(--color-primary-600);
  color: var(--text-inverse);
}

.edit-cancel {
  border: 1px solid var(--border-default);
  background: var(--surface-primary);
  color: var(--text-primary);
}

.edit-save:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

@media (min-width: 48rem) {
  .scalar-review-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 1.5rem;
    padding: 1.25rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .scalar-review-item {
    transition: none;
  }
}
</style>
