<script setup lang="ts">
import { computed, shallowRef } from 'vue'

type Decision = 'pending' | 'included' | 'edited' | 'excluded'

const props = defineProps<{
  index: number
  text: string
}>()

const decision = shallowRef<Decision>('pending')
const isEditing = shallowRef(false)
const draftText = shallowRef(props.text)

const isIncluded = computed(() => decision.value === 'included' || decision.value === 'edited')

const statusLabel = computed(() => {
  const labels: Record<Decision, string> = {
    pending: 'Not reviewed',
    included: 'Included',
    edited: 'Edited',
    excluded: 'Excluded',
  }

  return labels[decision.value]
})

function toggleIncluded(): void {
  decision.value = isIncluded.value ? 'pending' : 'included'
}

function startEditing(event: Event): void {
  closeMenu(event)
  isEditing.value = true
}

function saveEdit(): void {
  const value = draftText.value.trim()
  if (!value) return

  draftText.value = value
  decision.value = 'edited'
  isEditing.value = false
}

function cancelEdit(): void {
  draftText.value = props.text
  isEditing.value = false
}

function exclude(event: Event): void {
  closeMenu(event)
  decision.value = 'excluded'
}

function restore(event: Event): void {
  closeMenu(event)
  decision.value = 'pending'
}

function closeMenu(event: Event): void {
  const details = (event.currentTarget as HTMLElement).closest('details')
  if (details) details.open = false
}
</script>

<template>
  <article
    class="responsibility"
    :class="{
      'responsibility-included': decision === 'included',
      'responsibility-edited': decision === 'edited',
      'responsibility-excluded': decision === 'excluded',
    }"
  >
    <span class="responsibility-number" aria-hidden="true">
      {{ String(index + 1).padStart(2, '0') }}
    </span>

    <div class="responsibility-content">
      <form v-if="isEditing" class="edit-form" @submit.prevent="saveEdit">
        <label :for="`design-lab-responsibility-${index}`">Edit responsibility</label>
        <textarea
          :id="`design-lab-responsibility-${index}`"
          v-model="draftText"
          :name="`design-lab-responsibility-${index}`"
          autocomplete="off"
          rows="6"
        />
        <div class="edit-actions">
          <button
            type="submit"
            class="text-action text-action-primary"
            :disabled="!draftText.trim()"
          >
            Save change
          </button>
          <button type="button" class="text-action" @click="cancelEdit">Cancel</button>
        </div>
      </form>

      <template v-else>
        <p class="responsibility-text">{{ draftText }}</p>

        <details v-if="decision === 'edited'" class="original-disclosure">
          <summary>View original text</summary>
          <p>{{ text }}</p>
        </details>

        <div class="responsibility-meta">
          <span class="decision-status" :class="`decision-status-${decision}`">
            <span class="status-mark" aria-hidden="true">
              {{ isIncluded ? '✓' : decision === 'excluded' ? '×' : '•' }}
            </span>
            {{ statusLabel }}
          </span>
          <span class="source-label">Source: Responsibilities section</span>
        </div>
      </template>
    </div>

    <div v-if="!isEditing" class="responsibility-actions">
      <button
        v-if="decision !== 'excluded'"
        type="button"
        class="include-control"
        :class="{ 'include-control-selected': isIncluded }"
        :aria-pressed="isIncluded"
        @click="toggleIncluded"
      >
        <span aria-hidden="true">{{ isIncluded ? '✓' : '+' }}</span>
        {{ isIncluded ? 'Included' : 'Include' }}
      </button>

      <details class="more-actions">
        <summary :aria-label="`More actions for responsibility ${index + 1}`">
          <span aria-hidden="true">•••</span>
        </summary>
        <div class="action-menu">
          <button v-if="decision !== 'excluded'" type="button" @click="startEditing">
            Edit responsibility
          </button>
          <button
            v-if="decision !== 'excluded'"
            type="button"
            class="action-danger"
            @click="exclude"
          >
            Exclude
          </button>
          <button v-else type="button" @click="restore">Restore responsibility</button>
        </div>
      </details>
    </div>
  </article>
</template>

<style scoped>
.responsibility {
  display: grid;
  position: relative;
  grid-template-columns: 2.5rem minmax(0, 1fr) auto;
  gap: 1.25rem;
  border-bottom: 1px solid var(--lab-rule);
  padding: 2rem 0;
  transition:
    opacity 160ms ease,
    transform 160ms ease;
}

.responsibility:first-child {
  padding-top: 1.5rem;
}

.responsibility:hover {
  transform: translateX(0.125rem);
}

.responsibility-number {
  padding-top: 0.125rem;
  color: var(--lab-faint);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  font-weight: 650;
  letter-spacing: 0.08em;
}

.responsibility-content {
  min-width: 0;
}

.responsibility-text {
  max-width: 46rem;
  overflow-wrap: anywhere;
  color: var(--lab-ink);
  font-size: 1rem;
  font-weight: 450;
  letter-spacing: -0.006em;
  line-height: 1.625rem;
  text-wrap: pretty;
}

.responsibility-excluded .responsibility-text {
  color: var(--lab-muted);
  text-decoration: line-through;
  text-decoration-color: var(--lab-danger-rule);
}

.responsibility-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
  margin-top: 0.875rem;
  color: var(--lab-muted);
  font-size: 0.75rem;
  line-height: 1.125rem;
}

.decision-status {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-weight: 620;
}

.status-mark {
  display: grid;
  width: 1rem;
  height: 1rem;
  place-items: center;
  border: 1px solid var(--lab-rule-strong);
  border-radius: 999px;
  color: var(--lab-muted);
  font-size: 0.625rem;
  line-height: 1;
}

.decision-status-included {
  color: var(--lab-success);
}

.decision-status-included .status-mark {
  border-color: var(--lab-success-rule);
  background: var(--lab-success-soft);
  color: var(--lab-success);
}

.decision-status-edited {
  color: var(--lab-cobalt);
}

.decision-status-edited .status-mark {
  border-color: var(--lab-cobalt);
  background: var(--lab-cobalt-soft);
  color: var(--lab-cobalt);
}

.decision-status-excluded {
  color: var(--lab-danger);
}

.decision-status-excluded .status-mark {
  border-color: var(--lab-danger-rule);
  background: var(--lab-danger-soft);
  color: var(--lab-danger);
}

.responsibility-actions {
  display: flex;
  align-items: flex-start;
  gap: 0.375rem;
}

.include-control,
.more-actions summary {
  display: inline-flex;
  min-height: 2.75rem;
  align-items: center;
  justify-content: center;
  border-radius: var(--lab-radius-control);
  color: var(--lab-muted);
  font-size: 0.8125rem;
  font-weight: 620;
}

.include-control {
  gap: 0.375rem;
  border: 0;
  padding: 0.5rem 0.75rem;
  background: transparent;
}

.include-control:hover {
  background: var(--lab-cobalt-soft);
  color: var(--lab-cobalt);
}

.include-control-selected {
  color: var(--lab-success);
}

.more-actions {
  position: relative;
}

.more-actions summary {
  width: 2.75rem;
  cursor: pointer;
  list-style: none;
  letter-spacing: 0.08em;
}

.more-actions summary::-webkit-details-marker {
  display: none;
}

.more-actions summary:hover {
  background: var(--lab-hover);
  color: var(--lab-ink);
}

.action-menu {
  display: grid;
  position: absolute;
  z-index: 10;
  top: calc(100% + 0.375rem);
  right: 0;
  min-width: 12rem;
  border: 1px solid var(--lab-rule);
  border-radius: var(--lab-radius-control);
  padding: 0.375rem;
  background: var(--lab-paper);
  box-shadow: var(--lab-menu-shadow);
}

.action-menu button {
  min-height: 2.75rem;
  border: 0;
  border-radius: 0.375rem;
  padding: 0.5rem 0.625rem;
  background: transparent;
  color: var(--lab-text);
  font-size: 0.8125rem;
  font-weight: 560;
  text-align: left;
}

.action-menu button:hover {
  background: var(--lab-hover);
}

.action-menu .action-danger {
  color: var(--lab-danger);
}

.edit-form {
  display: grid;
  gap: 0.625rem;
}

.edit-form label {
  color: var(--lab-text);
  font-size: 0.8125rem;
  font-weight: 620;
}

.edit-form textarea {
  width: 100%;
  min-height: 9rem;
  resize: vertical;
  border: 1px solid var(--lab-rule-strong);
  border-radius: var(--lab-radius-control);
  background: var(--lab-paper);
  padding: 0.875rem;
  color: var(--lab-ink);
  font: inherit;
  font-size: 1rem;
  line-height: 1.625rem;
}

.edit-actions {
  display: flex;
  gap: 0.5rem;
}

.text-action {
  min-height: 2.75rem;
  border: 0;
  border-radius: var(--lab-radius-control);
  padding: 0.5rem 0.75rem;
  background: transparent;
  color: var(--lab-muted);
  font-size: 0.8125rem;
  font-weight: 620;
}

.text-action-primary {
  background: var(--lab-cobalt);
  color: var(--lab-paper);
}

.text-action:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.original-disclosure {
  margin-top: 0.75rem;
  color: var(--lab-muted);
  font-size: 0.75rem;
}

.original-disclosure summary {
  min-height: 2.75rem;
  cursor: pointer;
  color: var(--lab-cobalt);
  font-weight: 620;
}

.original-disclosure p {
  border-left: 2px solid var(--lab-rule-strong);
  padding-left: 0.75rem;
  font-size: 0.8125rem;
  line-height: 1.375rem;
}

.include-control:focus-visible,
.more-actions summary:focus-visible,
.action-menu button:focus-visible,
.edit-form textarea:focus-visible,
.text-action:focus-visible,
.original-disclosure summary:focus-visible {
  outline: 2px solid var(--lab-cobalt);
  outline-offset: 2px;
}

.include-control,
.more-actions summary,
.action-menu button,
.text-action {
  touch-action: manipulation;
}

@media (max-width: 47.999rem) {
  .responsibility {
    grid-template-columns: 2rem minmax(0, 1fr);
    gap: 0.75rem;
    padding: 1.5rem 0;
  }

  .responsibility-actions {
    grid-column: 2;
    justify-content: space-between;
  }
}

@media (prefers-reduced-motion: reduce) {
  .responsibility {
    transition: none;
  }
}
</style>
