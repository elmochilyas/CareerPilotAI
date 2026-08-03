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
}

.step-indicator-row {
  display: flex;
  align-items: baseline;
  gap: 0.25rem;
}

.step-position {
  color: var(--cp-text-muted);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  font-weight: 570;
  line-height: 1.125rem;
}

.step-sep {
  color: var(--cp-text-faint);
  font-size: 0.75rem;
}

.step-label {
  color: var(--cp-ink);
  font-size: 0.9375rem;
  font-weight: 650;
  line-height: 1.375rem;
  overflow-wrap: anywhere;
}

.step-progress {
  height: 0.1875rem;
  margin-top: 0.625rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--cp-surface-muted);
}

.step-progress > span {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: inherit;
  background: var(--cp-primary);
  transform-origin: left center;
  transition: transform 150ms ease;
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
