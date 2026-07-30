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
  --timeline-surface: var(--cp-surface, #ffffff);
  --timeline-surface-subtle: var(--cp-surface-subtle, #fafafa);
  --timeline-surface-muted: var(--cp-surface-muted, #f2f4f7);
  --timeline-border: var(--cp-border, #e4e7ec);
  --timeline-border-strong: var(--cp-border-strong, #d0d5dd);
  --timeline-ink: var(--cp-ink, #111827);
  --timeline-text: var(--cp-text, #263244);
  --timeline-muted: var(--cp-text-muted, #667085);
  --timeline-faint: var(--cp-text-faint, #98a2b3);
  --timeline-primary: var(--cp-primary, #4f46e5);
  --timeline-primary-strong: var(--cp-primary-hover, #4338ca);
  --timeline-primary-border: var(--cp-primary-border, #c7d2fe);
  --timeline-primary-soft: var(--cp-primary-soft, #eef2ff);
  --timeline-success: var(--cp-success, #067647);
  --timeline-success-border: var(--cp-success-border, #abefc6);
  --timeline-success-soft: var(--cp-success-soft, #ecfdf3);
  --timeline-warning: var(--cp-warning, #b54708);
  --timeline-warning-border: var(--cp-warning-border, #fedf89);
  --timeline-warning-soft: var(--cp-warning-soft, #fffaeb);
  --timeline-spine: var(--cp-divider, #eaecf0);
  --timeline-connector: var(--cp-surface-muted, #f2f4f7);
  --timeline-pending-surface: var(--cp-surface-muted, #f2f4f7);
  --timeline-pending-text: var(--cp-text-faint, #98a2b3);
  --timeline-white: var(--cp-text-inverse, #ffffff);
  --timeline-gradient-primary: linear-gradient(
    135deg,
    var(--cp-primary-light, #6366f1),
    var(--cp-primary-hover, #4338ca)
  );
  --timeline-gradient-success: linear-gradient(
    135deg,
    var(--cp-success-light, #34d399),
    var(--cp-success-strong, #059669)
  );
  --timeline-gradient-warning: linear-gradient(
    135deg,
    var(--cp-warning-light, #fbbf24),
    var(--cp-warning-strong, #d97706)
  );
  --timeline-marker-shadow: 0 1px 2px rgb(16 24 40 / 0.08);
  --timeline-primary-marker-shadow: 0 8px 18px rgb(79 70 229 / 0.24);
  --timeline-success-marker-shadow: 0 6px 14px rgb(5 150 105 / 0.2);
  --timeline-warning-marker-shadow: 0 6px 14px rgb(217 119 6 / 0.2);
  --timeline-panel-shadow: var(--cp-shadow-review-navigation, 0 1px 2px rgb(16 24 40 / 0.04));
  --timeline-current-shadow: var(--cp-shadow-review-current, 0 4px 12px rgb(79 70 229 / 0.1));
  --timeline-menu-shadow: var(--cp-shadow-review-menu, 0 12px 32px rgb(16 24 40 / 0.14));

  min-width: 0;
}
</style>
