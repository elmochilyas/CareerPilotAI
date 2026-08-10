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
          'compact-rail-item-in-progress': item.status === 'in-progress',
          'compact-rail-item-blocked': item.status === 'blocked',
          'compact-rail-item-summary': item.kind === 'summary',
        }"
      >
        <span
          v-if="idx < items.length - 1"
          class="compact-rail-connector"
          :class="{
            'compact-rail-connector-done': item.status === 'complete',
            'compact-rail-connector-in-progress': item.status === 'in-progress',
          }"
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
  padding: 0.75rem 1rem;
  border-radius: var(--radius-xl);
  background: var(--surface-primary);
  box-shadow: var(--shadow-neo-raised-sm);
}

.compact-rail-list {
  display: flex;
  width: 100%;
  margin: 0;
  padding: 0;
  list-style: none;
  align-items: flex-start;
}

.compact-rail-item {
  position: relative;
  flex: 1 1 0;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.compact-rail-connector {
  position: absolute;
  z-index: 0;
  top: 0.9375rem;
  left: calc(50% + 1.25rem);
  width: calc(100% - 2.5rem);
  height: 2px;
  background: var(--surface-inset);
  border-radius: 1px;
  pointer-events: none;
  transition: background-color 200ms ease;
}

.compact-rail-connector-done {
  background: linear-gradient(90deg, var(--color-primary-500), var(--color-primary-600));
}

.compact-rail-connector-in-progress {
  background: linear-gradient(90deg, var(--color-primary-200), var(--color-primary-300));
}

.compact-rail-button {
  display: flex;
  position: relative;
  z-index: 1;
  width: 100%;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--text-muted);
  text-align: center;
  touch-action: manipulation;
  cursor: pointer;
  transition: color 150ms ease;
  -webkit-tap-highlight-color: transparent;
}

.compact-rail-button:hover {
  color: var(--text-primary);
}

.compact-rail-button:focus-visible {
  outline: 2px solid var(--color-primary-600);
  outline-offset: 4px;
  border-radius: var(--radius-md);
}

.compact-rail-marker {
  display: grid;
  width: 1.875rem;
  height: 1.875rem;
  flex: 0 0 1.875rem;
  place-items: center;
  border: 2px solid var(--border-default);
  border-radius: var(--radius-full);
  background: var(--surface-primary);
  color: var(--text-tertiary);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  line-height: 1;
  box-shadow: var(--shadow-neo-raised-sm);
  transition:
    background-color 200ms ease,
    border-color 200ms ease,
    color 200ms ease,
    box-shadow 200ms ease,
    transform 200ms ease;
}

.compact-rail-button:hover .compact-rail-marker {
  transform: scale(1.08);
}

.compact-rail-item-current .compact-rail-marker {
  border-color: var(--color-primary-500);
  background: linear-gradient(135deg, var(--color-primary-500), var(--color-primary-700));
  color: var(--text-inverse);
  box-shadow:
    0 0 0 4px var(--color-primary-100),
    0 4px 12px rgb(80 65 200 / 0.3);
}

.compact-rail-item-current .compact-rail-button {
  color: var(--text-primary);
  font-weight: 650;
}

.compact-rail-item-in-progress .compact-rail-marker {
  border-color: var(--color-primary-300);
  background: var(--color-primary-50);
  color: var(--color-primary-600);
  box-shadow: 0 2px 8px rgb(80 65 200 / 0.12);
}

.compact-rail-item-in-progress .compact-rail-button {
  color: var(--text-primary);
  font-weight: 620;
}

.compact-rail-item-complete .compact-rail-marker {
  border-color: var(--color-success-600);
  background: var(--color-success-500);
  color: var(--text-inverse);
  box-shadow: 0 2px 8px rgb(3 152 85 / 0.25);
}

.compact-rail-item-complete .compact-rail-button {
  color: var(--text-muted);
}

.compact-rail-item-blocked .compact-rail-marker {
  border-color: var(--color-warning-300);
  background: var(--color-warning-50);
  color: var(--color-warning-600);
  box-shadow: 0 2px 8px rgb(181 71 8 / 0.15);
}

.marker-icon {
  width: 0.9375rem;
  height: 0.9375rem;
  stroke-width: 2.5;
}

.marker-warning {
  color: var(--color-warning-600);
}

.compact-rail-item-current .marker-warning {
  color: var(--color-warning-600);
}

.marker-number {
  line-height: 1;
}

.compact-rail-label {
  overflow: hidden;
  max-width: 6.5rem;
  color: currentColor;
  font-size: 0.6875rem;
  font-weight: 560;
  line-height: 1rem;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-wrap: nowrap;
}

.compact-rail-item-current .compact-rail-label {
  font-weight: 700;
}

.compact-rail-item-complete .compact-rail-label {
  color: var(--text-muted);
}

@media (min-width: 48rem) {
  .compact-rail {
    padding: 0.875rem 1.25rem;
  }

  .compact-rail-label {
    max-width: 7.5rem;
    font-size: 0.75rem;
    line-height: 1.125rem;
  }

  .compact-rail-marker {
    width: 2rem;
    height: 2rem;
    flex: 0 0 2rem;
    font-size: 0.8125rem;
  }

  .compact-rail-connector {
    top: 1rem;
    left: calc(50% + 1.375rem);
    width: calc(100% - 2.75rem);
  }
}

@media (min-width: 63.9375rem) {
  .compact-rail {
    display: flex;
  }
}

@media (prefers-reduced-motion: reduce) {
  .compact-rail-button,
  .compact-rail-marker,
  .compact-rail-connector {
    transition: none;
  }

  .compact-rail-button:hover .compact-rail-marker {
    transform: none;
  }
}
</style>
