<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import SkillSearchCombobox from '@/features/skills/components/SkillSearchCombobox.vue'
import type { Skill } from '@/features/skills/types'
import type { JobSuggestion } from '../types'

const props = defineProps<{
  suggestion: JobSuggestion
  readonly?: boolean
  saving?: boolean
}>()

const emit = defineEmits<{
  keep: [suggestionId: number]
  remove: [suggestionId: number]
  restore: [suggestionId: number]
  resolve: [suggestionId: number, resolvedSkillId: number | null]
}>()

const isResolving = shallowRef(false)
const selectedSkill = shallowRef<Skill | null>(null)

const label = computed(() => {
  const value =
    props.suggestion.review_decision === 'edited' && props.suggestion.edited_value
      ? props.suggestion.edited_value
      : props.suggestion.extracted_value

  return String(value.label ?? '')
})

const isExcluded = computed(
  () =>
    props.suggestion.review_decision === 'rejected' ||
    props.suggestion.review_decision === 'keep_blank',
)

function resolveWithCanonicalSkill(skill: Skill): void {
  selectedSkill.value = skill
  emit('resolve', props.suggestion.id, skill.id)
  isResolving.value = false
}

function keepOriginalLabel(): void {
  selectedSkill.value = null
  emit('resolve', props.suggestion.id, null)
  isResolving.value = false
}
</script>

<template>
  <div
    class="skill-review-item"
    :class="{ 'skill-review-item-excluded': isExcluded }"
    :aria-busy="saving"
  >
    <div class="skill-review-main">
      <div class="skill-label-row">
        <span class="skill-label">{{ label }}</span>
        <span
          v-if="suggestion.resolution === 'ambiguous'"
          class="resolution-badge resolution-ambiguous"
        >
          Needs resolution
        </span>
        <span v-else-if="suggestion.resolution" class="resolution-badge resolution-complete">
          {{ suggestion.resolution.replaceAll('_', ' ') }}
        </span>
      </div>

      <details v-if="suggestion.source_evidence" class="skill-evidence">
        <summary>View source evidence</summary>
        <p>{{ suggestion.source_evidence }}</p>
      </details>

      <div v-if="isResolving" class="skill-resolution-panel">
        <label :for="`skill-${suggestion.id}-search`">Choose the matching skill</label>
        <SkillSearchCombobox
          v-model="selectedSkill"
          :input-id="`skill-${suggestion.id}-search`"
          :aria-label="`Choose the catalog match for ${label}`"
          @select="resolveWithCanonicalSkill"
        />
        <div class="skill-resolution-actions">
          <button type="button" class="skill-action" :disabled="saving" @click="keepOriginalLabel">
            Keep original label
          </button>
          <button
            type="button"
            class="skill-action"
            :disabled="saving"
            @click="isResolving = false"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>

    <div v-if="!readonly && !isResolving" class="skill-actions">
      <button
        v-if="suggestion.resolution === 'ambiguous' && !isExcluded"
        type="button"
        class="skill-action skill-action-resolve"
        :disabled="saving"
        @click="isResolving = true"
      >
        Resolve
      </button>
      <button
        v-if="!isExcluded"
        type="button"
        class="skill-action"
        :disabled="saving"
        :class="suggestion.review_decision === 'accepted' ? 'skill-action-kept' : ''"
        @click="emit('keep', suggestion.id)"
      >
        Keep
      </button>
      <button
        v-if="!isExcluded"
        type="button"
        class="skill-action skill-action-remove"
        :disabled="saving"
        @click="emit('remove', suggestion.id)"
      >
        Remove
      </button>
      <button
        v-else
        type="button"
        class="skill-action skill-action-restore"
        :disabled="saving"
        @click="emit('restore', suggestion.id)"
      >
        Undo remove
      </button>
    </div>
  </div>
</template>

<style scoped>
.skill-review-item {
  display: grid;
  gap: 0.75rem;
  border: 1px solid var(--cp-border);
  border-radius: 0.5rem;
  padding: 0.875rem;
}

.skill-review-item-excluded .skill-review-main {
  opacity: 0.6;
}

.skill-review-main {
  min-width: 0;
}

.skill-label-row,
.skill-actions,
.skill-resolution-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.skill-label {
  overflow-wrap: anywhere;
  color: var(--cp-ink);
  font-size: 0.875rem;
  font-weight: 620;
}

.resolution-badge {
  border-radius: 999px;
  padding: 0.125rem 0.5rem;
  font-size: 0.6875rem;
  font-weight: 650;
  text-transform: capitalize;
}

.resolution-ambiguous {
  background: var(--cp-warning-soft);
  color: var(--cp-warning);
}

.resolution-complete {
  background: var(--cp-success-soft);
  color: var(--cp-success);
}

.skill-evidence {
  margin-top: 0.5rem;
  color: var(--cp-text-muted);
  font-size: 0.75rem;
}

.skill-evidence summary {
  cursor: pointer;
  font-weight: 620;
}

.skill-evidence p {
  max-width: 70ch;
  margin-top: 0.375rem;
  white-space: pre-wrap;
}

.skill-resolution-panel {
  display: grid;
  gap: 0.625rem;
  margin-top: 0.75rem;
}

.skill-resolution-panel > label {
  color: var(--cp-ink);
  font-size: 0.8125rem;
  font-weight: 650;
}

.skill-action {
  min-height: 2.75rem;
  border: 0;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: transparent;
  color: var(--cp-text-muted);
  font-size: 0.8125rem;
  font-weight: 620;
  cursor: pointer;
}

.skill-action:hover {
  background: var(--cp-surface-muted);
  color: var(--cp-ink);
}

.skill-action:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 2px;
}

.skill-action:disabled {
  cursor: wait;
  opacity: 0.55;
}

.skill-action-resolve {
  background: var(--cp-warning-soft);
  color: var(--cp-warning);
}

.skill-action-kept,
.skill-action-restore {
  color: var(--cp-success);
}

.skill-action-remove {
  color: var(--cp-danger);
}

@media (min-width: 48rem) {
  .skill-review-item {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
  }
}
</style>
