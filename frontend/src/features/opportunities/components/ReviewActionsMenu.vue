<script setup lang="ts">
import { nextTick, shallowRef, useTemplateRef } from 'vue'
import type { ReviewDecisionValue } from '../types'

withDefaults(
  defineProps<{
    decision: ReviewDecisionValue
    hasEditedValue?: boolean
  }>(),
  {
    hasEditedValue: false,
  },
)

const emit = defineEmits<{
  edit: []
  exclude: []
  restore: []
  viewOriginal: []
}>()

const isOpen = shallowRef(false)
const summaryRef = useTemplateRef<HTMLElement>('summaryRef')

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && isOpen.value) {
    event.preventDefault()
    closeAndFocus()
  }
}

function onToggle(event: Event): void {
  const details = event.currentTarget as HTMLDetailsElement
  isOpen.value = details.open
  if (isOpen.value && details.querySelector('button')) {
    nextTick(() => {
      ;(details.querySelector('button') as HTMLButtonElement | null)?.focus()
    })
  }
}

function onBlur(event: FocusEvent): void {
  const wrapper = event.currentTarget as HTMLElement
  const relatedTarget = event.relatedTarget as Node | null
  if (!relatedTarget || !wrapper.contains(relatedTarget)) {
    closeAndFocus()
  }
}

function closeAndFocus(): void {
  isOpen.value = false
  nextTick(() => summaryRef.value?.focus())
}

function handleEdit(): void {
  closeAndFocus()
  emit('edit')
}

function handleExclude(): void {
  closeAndFocus()
  emit('exclude')
}

function handleRestore(): void {
  closeAndFocus()
  emit('restore')
}

function handleViewOriginal(): void {
  closeAndFocus()
  emit('viewOriginal')
}
</script>

<template>
  <details
    class="review-actions-overflow"
    :open="isOpen"
    @toggle="onToggle"
    @blur="onBlur"
    @keydown="handleKeydown"
  >
    <summary ref="summaryRef" class="overflow-trigger" :aria-label="`More actions`">
      <span aria-hidden="true">•••</span>
    </summary>
    <div class="overflow-panel" role="menu">
      <button v-if="decision === 'pending'" type="button" role="menuitem" @click="handleEdit">
        Edit
      </button>
      <button
        v-if="decision === 'pending'"
        type="button"
        role="menuitem"
        class="action-exclude"
        @click="handleExclude"
      >
        Exclude
      </button>

      <button v-if="decision === 'accepted'" type="button" role="menuitem" @click="handleEdit">
        Edit
      </button>
      <button
        v-if="decision === 'accepted'"
        type="button"
        role="menuitem"
        class="action-exclude"
        @click="handleExclude"
      >
        Exclude
      </button>

      <button
        v-if="decision === 'edited' && hasEditedValue"
        type="button"
        role="menuitem"
        @click="handleViewOriginal"
      >
        View original
      </button>
      <button
        v-if="decision === 'edited'"
        type="button"
        role="menuitem"
        class="action-exclude"
        @click="handleExclude"
      >
        Exclude
      </button>

      <button
        v-if="decision === 'rejected' || decision === 'keep_blank'"
        type="button"
        role="menuitem"
        @click="handleRestore"
      >
        Undo remove
      </button>
    </div>
  </details>
</template>

<style scoped>
.review-actions-overflow {
  position: relative;
}

.overflow-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  min-height: 2.75rem;
  border: 0;
  border-radius: 0.5rem;
  background: transparent;
  color: var(--cp-text-muted);
  font-size: 0.875rem;
  letter-spacing: 0.08em;
  cursor: pointer;
  list-style: none;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.overflow-trigger::-webkit-details-marker {
  display: none;
}

.overflow-trigger:hover {
  background: var(--cp-surface-muted);
  color: var(--cp-ink);
}

.overflow-trigger:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 2px;
}

.overflow-panel {
  position: absolute;
  z-index: 20;
  top: calc(100% + 0.25rem);
  right: 0;
  display: grid;
  min-width: 12rem;
  gap: 0.125rem;
  border: 1px solid var(--cp-border);
  border-radius: 0.5rem;
  padding: 0.375rem;
  background: var(--cp-surface);
  box-shadow: 0 12px 32px rgb(16 24 40 / 0.14);
}

.overflow-panel button {
  display: block;
  width: 100%;
  min-height: 2.75rem;
  border: 0;
  border-radius: 0.375rem;
  padding: 0.5rem 0.75rem;
  background: transparent;
  color: var(--cp-text);
  font-size: 0.8125rem;
  font-weight: 560;
  text-align: left;
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.overflow-panel button:hover {
  background: var(--cp-surface-subtle);
}

.overflow-panel button:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: -2px;
}

.action-exclude {
  color: var(--cp-danger);
}

@media (prefers-reduced-motion: reduce) {
  .overflow-panel {
    transition: none;
  }
}
</style>
