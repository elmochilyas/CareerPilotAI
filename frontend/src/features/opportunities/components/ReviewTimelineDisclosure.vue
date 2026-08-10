<script setup lang="ts">
import { computed } from 'vue'
import type { ReviewTimelineItem } from './reviewTimeline'

const props = defineProps<{
  items: ReviewTimelineItem[]
}>()

const currentItem = computed(
  () => props.items.find((item) => item.isCurrent) ?? props.items[0] ?? null,
)

const stepProgressScale = computed(() => {
  if (!currentItem.value || props.items.length === 0) return 0
  return (currentItem.value.index + 1) / props.items.length
})
</script>

<template>
  <nav v-if="currentItem" class="step-indicator" aria-label="Current review step">
    <div class="step-indicator-row">
      <span class="step-position"> Step {{ currentItem.index + 1 }} of {{ items.length }} </span>
      <span class="step-sep" aria-hidden="true">·</span>
      <strong class="step-label">{{ currentItem.label }}</strong>
    </div>

    <div
      class="step-progress"
      role="progressbar"
      :aria-label="`Review step ${currentItem.index + 1} of ${items.length}`"
      :aria-valuenow="currentItem.index + 1"
      aria-valuemin="1"
      :aria-valuemax="items.length"
    >
      <span :style="{ transform: `scaleX(${stepProgressScale})` }" />
    </div>
  </nav>
</template>

<style scoped>
.step-indicator {
  display: block;
  min-width: 0;
  padding: 0.875rem 1rem;
  border-radius: var(--radius-xl);
  background: var(--surface-primary);
  box-shadow: var(--shadow-neo-raised-sm);
}

.step-indicator-row {
  display: flex;
  align-items: baseline;
  gap: 0.375rem;
}

.step-position {
  color: var(--text-muted);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  line-height: 1.25rem;
}

.step-sep {
  color: var(--text-tertiary);
  font-size: 0.8125rem;
}

.step-label {
  color: var(--text-primary);
  font-size: 0.9375rem;
  font-weight: 680;
  line-height: 1.375rem;
  overflow-wrap: anywhere;
}

.step-progress {
  height: 0.25rem;
  margin-top: 0.75rem;
  overflow: hidden;
  border-radius: var(--radius-full);
  background: var(--surface-inset);
  box-shadow: var(--shadow-neo-inset);
}

.step-progress > span {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--color-primary-400), var(--color-primary-600));
  transform-origin: left center;
  transition: transform 200ms ease;
}

@media (min-width: 63.9375rem) {
  .step-indicator {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .step-progress > span {
    transition: none;
  }
}
</style>
