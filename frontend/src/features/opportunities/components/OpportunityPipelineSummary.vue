<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import { BookmarkCheck, CircleAlert, Clock3 } from '@lucide/vue'

const props = defineProps<{
  savedCount: number
  activeCount: number
  attentionCount: number
  loading?: boolean
}>()

type SummaryTone = 'saved' | 'active' | 'attention'

interface SummaryItem {
  key: SummaryTone
  label: string
  eyebrow: string
  description: string
  status: string
  count: number
  icon: Component
}

const summaryItems = computed<SummaryItem[]>(() => [
  {
    key: 'saved',
    label: 'Saved',
    eyebrow: 'Your shortlist',
    description: 'Approved roles kept in your trusted shortlist.',
    status: props.loading
      ? 'Updating pipeline…'
      : props.savedCount > 0
        ? 'Ready when you want to act'
        : 'No roles saved yet',
    count: props.savedCount,
    icon: BookmarkCheck,
  },
  {
    key: 'active',
    label: 'In progress',
    eyebrow: 'Background work',
    description: 'Descriptions being analyzed or prepared for review.',
    status: props.loading
      ? 'Updating pipeline…'
      : props.activeCount > 0
        ? 'Analysis is running'
        : 'No active analyses',
    count: props.activeCount,
    icon: Clock3,
  },
  {
    key: 'attention',
    label: 'Needs review',
    eyebrow: 'Your attention',
    description: 'Extracted details waiting for your approval.',
    status: props.loading
      ? 'Updating pipeline…'
      : props.attentionCount > 0
        ? 'Review to keep your pipeline moving'
        : 'You’re all caught up',
    count: props.attentionCount,
    icon: CircleAlert,
  },
])
</script>

<template>
  <section class="pipeline-summary" aria-labelledby="pipeline-summary-title">
    <h2 id="pipeline-summary-title" class="sr-only">Opportunity summary</h2>

    <ul class="summary-list" :aria-busy="loading" aria-live="polite">
      <li
        v-for="item in summaryItems"
        :key="item.key"
        class="summary-card"
        :class="[
          `summary-card-${item.key}`,
          { 'summary-card-has-items': item.key === 'attention' && item.count > 0 },
        ]"
      >
        <div class="summary-heading">
          <span class="summary-icon" aria-hidden="true">
            <component :is="item.icon" class="summary-icon-glyph" />
          </span>
          <div class="summary-heading-copy">
            <p class="summary-eyebrow">{{ item.eyebrow }}</p>
            <h3>{{ item.label }}</h3>
          </div>
        </div>

        <p class="summary-metric">
          <span class="summary-count">{{ loading ? '—' : item.count }}</span>
          <span class="summary-unit">{{ item.count === 1 ? 'role' : 'roles' }}</span>
        </p>

        <p class="summary-description">{{ item.description }}</p>

        <p class="summary-status">
          <span class="summary-status-dot" aria-hidden="true" />
          {{ item.status }}
        </p>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.pipeline-summary {
  margin-top: 1.25rem;
}

.summary-list {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.summary-card {
  --summary-accent: var(--cp-text-muted);
  --summary-accent-soft: var(--cp-surface-muted);
  --summary-card-border: var(--cp-border);

  display: grid;
  position: relative;
  min-width: 0;
  grid-template-columns: minmax(0, 1fr) auto;
  grid-template-rows: auto 1fr auto;
  column-gap: 0.75rem;
  overflow: hidden;
  border: 1px solid var(--summary-card-border);
  border-radius: 0.875rem;
  padding: 0.875rem 1rem;
  background: var(--cp-surface);
  box-shadow:
    0 1px 2px rgb(16 24 40 / 0.04),
    0 7px 18px rgb(16 24 40 / 0.05),
    inset 0 1px 0 rgb(255 255 255 / 0.88);
}

.summary-card-saved {
  --summary-accent: var(--cp-success);
  --summary-accent-soft: var(--cp-success-soft);
}

.summary-card-active {
  --summary-accent: var(--cp-info);
  --summary-accent-soft: var(--cp-info-soft);
}

.summary-card-attention {
  --summary-accent: var(--cp-warning);
  --summary-accent-soft: var(--cp-warning-soft);
}

.summary-card-attention.summary-card-has-items {
  --summary-card-border: var(--cp-warning-border);
}

.summary-heading {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.75rem;
}

.summary-icon {
  display: grid;
  width: 2.25rem;
  height: 2.25rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 0.625rem;
  background: var(--summary-accent-soft);
  box-shadow:
    0 1px 2px rgb(16 24 40 / 0.05),
    inset 0 1px 0 rgb(255 255 255 / 0.75);
  color: var(--summary-accent);
}

.summary-icon-glyph {
  width: 0.9375rem;
  height: 0.9375rem;
}

.summary-heading-copy {
  min-width: 0;
}

.summary-eyebrow {
  overflow: hidden;
  color: var(--summary-accent);
  font-size: 0.5625rem;
  font-weight: 720;
  letter-spacing: 0.07em;
  line-height: 0.875rem;
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

.summary-heading-copy h3 {
  margin-top: 0.125rem;
  color: var(--cp-ink);
  font-size: 0.8125rem;
  font-weight: 680;
  letter-spacing: -0.012em;
  line-height: 1.125rem;
  text-wrap: balance;
}

.summary-metric {
  display: flex;
  min-width: 0;
  align-self: center;
  align-items: baseline;
  gap: 0.375rem;
  grid-column: 2;
  grid-row: 1;
  justify-self: end;
}

.summary-count {
  color: var(--cp-ink);
  font-size: 1.625rem;
  font-variant-numeric: tabular-nums;
  font-weight: 740;
  letter-spacing: -0.045em;
  line-height: 1.875rem;
}

.summary-unit {
  color: var(--cp-text-muted);
  font-size: 0.6875rem;
  font-weight: 620;
  line-height: 1rem;
}

.summary-description {
  display: -webkit-box;
  grid-column: 1 / -1;
  margin-top: 0.625rem;
  overflow: hidden;
  color: var(--cp-text-muted);
  font-size: 0.6875rem;
  line-height: 1rem;
  text-wrap: pretty;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.summary-status {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.5rem;
  grid-column: 1 / -1;
  margin-top: 0.625rem;
  border-top: 1px solid var(--cp-border);
  padding-top: 0.5rem;
  color: var(--cp-text);
  font-size: 0.625rem;
  font-weight: 620;
  line-height: 1rem;
}

.summary-status-dot {
  width: 0.375rem;
  height: 0.375rem;
  flex: 0 0 auto;
  border-radius: 999px;
  background: var(--summary-accent);
  box-shadow: 0 0 0 0.1875rem var(--summary-accent-soft);
}

@media (max-width: 39.999rem) {
  .pipeline-summary {
    margin-top: 1rem;
  }

  .summary-list {
    grid-template-columns: 1fr;
  }

  .summary-card {
    padding: 0.875rem;
  }

  .summary-description {
    display: none;
  }

  .summary-status {
    margin-top: 0.5rem;
  }
}
</style>
