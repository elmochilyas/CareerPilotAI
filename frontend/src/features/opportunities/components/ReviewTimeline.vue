<script setup lang="ts">
import { computed } from 'vue'
import ReviewTimelineDisclosure from './ReviewTimelineDisclosure.vue'
import ReviewTimelineRail from './ReviewTimelineRail.vue'
import {
  createReviewTimelineItems,
  normalizeReviewTimelineIndex,
  type ReviewTimelineStep as ReviewTimelineStepContract,
} from './reviewTimeline'

export type ReviewTimelineStep = ReviewTimelineStepContract

const props = defineProps<{
  steps: ReviewTimelineStep[]
  currentIndex: number
}>()

const emit = defineEmits<{
  select: [index: number]
}>()

const normalizedCurrentIndex = computed(() =>
  normalizeReviewTimelineIndex(props.steps, props.currentIndex),
)

const items = computed(() => createReviewTimelineItems(props.steps, normalizedCurrentIndex.value))

const currentAnnouncement = computed(() => {
  const currentItem = items.value.find((item) => item.isCurrent)
  if (!currentItem) return ''

  return `Step ${currentItem.index + 1} of ${items.value.length}: ${currentItem.label}`
})

function selectStep(index: number): void {
  emit('select', index)
}
</script>

<template>
  <div v-if="items.length > 0" class="review-timeline">
    <ReviewTimelineRail :items="items" @select="selectStep" />
    <ReviewTimelineDisclosure :items="items" @select="selectStep" />
    <p class="sr-only" aria-live="polite">{{ currentAnnouncement }}</p>
  </div>
</template>

<style scoped>
.review-timeline {
  --timeline-surface: var(--surface-primary);
  --timeline-surface-subtle: var(--surface-secondary);
  --timeline-surface-muted: var(--surface-inset);
  --timeline-border: var(--border-default);
  --timeline-border-strong: var(--border-strong);
  --timeline-ink: var(--text-primary);
  --timeline-text: var(--text-primary);
  --timeline-muted: var(--text-muted);
  --timeline-faint: var(--text-tertiary);
  --timeline-primary: var(--color-primary-600);
  --timeline-primary-strong: var(--color-primary-700);
  --timeline-primary-border: var(--color-primary-200);
  --timeline-primary-soft: var(--color-primary-50);
  --timeline-success: var(--color-success-600);
  --timeline-success-border: var(--color-success-200);
  --timeline-success-soft: var(--color-success-50);
  --timeline-warning: var(--color-warning-600);
  --timeline-warning-border: var(--color-warning-200);
  --timeline-warning-soft: var(--color-warning-50);
  --timeline-spine: var(--border-default);
  --timeline-connector: var(--surface-inset);
  --timeline-pending-surface: var(--surface-inset);
  --timeline-pending-text: var(--text-tertiary);
  --timeline-white: var(--text-inverse);
  --timeline-gradient-primary: linear-gradient(
    135deg,
    var(--color-primary-400),
    var(--color-primary-700)
  );
  --timeline-gradient-success: linear-gradient(
    135deg,
    var(--color-success-200),
    var(--color-success-600)
  );
  --timeline-gradient-warning: linear-gradient(
    135deg,
    var(--color-warning-200),
    var(--color-warning-600)
  );
  --timeline-marker-shadow: var(--shadow-neo-raised-sm);
  --timeline-primary-marker-shadow: 0 8px 18px rgb(80 65 200 / 0.24);
  --timeline-success-marker-shadow: 0 6px 14px rgb(3 152 85 / 0.2);
  --timeline-warning-marker-shadow: 0 6px 14px rgb(181 71 8 / 0.2);
  --timeline-panel-shadow: var(--shadow-neo-raised-sm);
  --timeline-current-shadow: 0 4px 12px rgb(80 65 200 / 0.1);
  --timeline-menu-shadow: 0 12px 32px rgb(16 24 40 / 0.14);

  min-width: 0;
  margin-bottom: 1rem;
}
</style>
