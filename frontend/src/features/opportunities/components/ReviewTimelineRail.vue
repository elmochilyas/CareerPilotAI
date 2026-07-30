<script setup lang="ts">
import { nextTick, useTemplateRef } from 'vue'
import { Check, TriangleAlert } from '@lucide/vue'
import type { ReviewTimelineItem } from './reviewTimeline'

const props = defineProps<{
  items: ReviewTimelineItem[]
}>()

const emit = defineEmits<{
  select: [index: number]
}>()

const stepButtons = useTemplateRef<HTMLButtonElement[]>('stepButtons')

function selectStep(index: number): void {
  emit('select', index)
}

function focusStepButton(index: number): void {
  stepButtons.value?.find((button) => button.dataset.reviewStepIndex === String(index))?.focus()
}

async function handleKeydown(event: KeyboardEvent, index: number): Promise<void> {
  let nextIndex = index

  if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
    nextIndex = Math.min(index + 1, props.items.length - 1)
  } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
    nextIndex = Math.max(index - 1, 0)
  } else if (event.key === 'Home') {
    nextIndex = 0
  } else if (event.key === 'End') {
    nextIndex = props.items.length - 1
  } else {
    return
  }

  event.preventDefault()

  if (nextIndex !== index) {
    emit('select', nextIndex)
  }

  await nextTick()
  focusStepButton(nextIndex)
}
</script>

<template>
  <nav class="compact-rail" aria-label="Opportunity review sections">
    <ol class="compact-rail-list">
      <li
        v-for="(item, idx) in items"
        :key="item.key"
        class="compact-rail-item"
        :class="{
          'compact-rail-item-current': item.isCurrent,
          'compact-rail-item-complete': item.status === 'complete',
          'compact-rail-item-blocked': item.status === 'blocked',
          'compact-rail-item-summary': item.kind === 'summary',
        }"
      >
        <span
          v-if="idx < items.length - 1"
          class="compact-rail-connector"
          :class="{ 'compact-rail-connector-done': item.status === 'complete' }"
          aria-hidden="true"
        />

        <button
          ref="stepButtons"
          type="button"
          class="compact-rail-button"
          :aria-current="item.isCurrent ? 'step' : undefined"
          :aria-label="item.accessibleLabel"
          :data-status="item.status"
          :data-review-step-index="item.index"
          @click="selectStep(item.index)"
          @keydown="handleKeydown($event, item.index)"
        >
          <span class="compact-rail-marker" aria-hidden="true">
            <Check v-if="item.status === 'complete'" class="marker-icon" />
            <TriangleAlert
              v-else-if="item.status === 'blocked'"
              class="marker-icon marker-warning"
            />
            <span v-else class="marker-number">{{ item.index + 1 }}</span>
          </span>

          <span class="compact-rail-label">{{ item.label }}</span>
        </button>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.compact-rail {
  display: none;
  padding: 0.25rem 0;
}

.compact-rail-list {
  display: flex;
  width: 100%;
  margin: 0;
  padding: 0;
  list-style: none;
  align-items: stretch;
}

.compact-rail-item {
  position: relative;
  flex: 1 1 0;
  min-width: 0;
}

.compact-rail-connector {
  position: absolute;
  z-index: 0;
  top: 0.875rem;
  left: calc(50% + 1rem);
  width: calc(100% - 2rem);
  height: 2px;
  background: var(--cp-surface-muted);
  pointer-events: none;
}

.compact-rail-connector-done {
  background: var(--cp-primary);
}

.compact-rail-button {
  display: flex;
  position: relative;
  z-index: 1;
  width: 100%;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  border: 0;
  padding: 0.25rem;
  background: transparent;
  color: var(--cp-text-muted);
  text-align: center;
  touch-action: manipulation;
  cursor: pointer;
  transition: color 120ms ease;
  -webkit-tap-highlight-color: transparent;
}

.compact-rail-button:hover {
  color: var(--cp-ink);
}

.compact-rail-button:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 2px;
}

.compact-rail-marker {
  display: grid;
  width: 1.5rem;
  height: 1.5rem;
  flex: 0 0 1.5rem;
  place-items: center;
  border: 1.5px solid var(--cp-border);
  border-radius: 999px;
  background: var(--cp-surface);
  color: var(--cp-text-faint);
  font-size: 0.6875rem;
  font-variant-numeric: tabular-nums;
  font-weight: 720;
  line-height: 1;
  transition:
    background-color 120ms ease,
    border-color 120ms ease,
    color 120ms ease;
}

.compact-rail-item-current .compact-rail-marker {
  border-color: var(--cp-primary);
  background: var(--cp-primary);
  color: var(--cp-text-inverse);
}

.compact-rail-item-current .compact-rail-button {
  color: var(--cp-ink);
  font-weight: 650;
}

.compact-rail-item-complete .compact-rail-marker {
  border-color: var(--cp-primary);
  background: var(--cp-primary);
  color: var(--cp-text-inverse);
}

.compact-rail-item-complete .compact-rail-button {
  color: var(--cp-text-muted);
}

.compact-rail-item-blocked .compact-rail-marker {
  border-color: var(--cp-warning-border);
  color: var(--cp-warning);
}

.marker-icon {
  width: 0.8125rem;
  height: 0.8125rem;
  stroke-width: 2.5;
}

.marker-warning {
  color: var(--cp-warning);
}

.compact-rail-item-current .marker-warning {
  color: var(--cp-text-inverse);
}

.marker-number {
  line-height: 1;
}

.compact-rail-label {
  overflow: hidden;
  max-width: 6rem;
  color: currentColor;
  font-size: 0.6875rem;
  font-weight: 560;
  line-height: 0.875rem;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-wrap: nowrap;
}

.compact-rail-item-current .compact-rail-label {
  font-weight: 680;
}

@media (min-width: 48rem) {
  .compact-rail-label {
    max-width: 7rem;
    font-size: 0.75rem;
    line-height: 1rem;
  }
}

@media (min-width: 63.9375rem) {
  .compact-rail {
    display: block;
  }
}

@media (prefers-reduced-motion: reduce) {
  .compact-rail-button,
  .compact-rail-marker {
    transition: none;
  }
}
</style>
