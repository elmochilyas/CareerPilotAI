<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import {
  Check,
  ChevronRight,
  Clock3,
  FileSearch,
  FileText,
  Link2,
  LoaderCircle,
  RotateCcw,
  TriangleAlert,
} from '@lucide/vue'
import type { JobIngestion } from '../types'
import OpportunityStatusBadge from './OpportunityStatusBadge.vue'

const props = defineProps<{
  ingestion: JobIngestion
  to: RouteLocationRaw
}>()

const relativeTimeFormatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })
const dateTimeFormatter = new Intl.DateTimeFormat('en', {
  dateStyle: 'medium',
  timeStyle: 'short',
})

const updatedLabel = computed(() => {
  const differenceInMinutes = Math.round(
    (new Date(props.ingestion.updated_at).getTime() - Date.now()) / 60000,
  )

  if (Math.abs(differenceInMinutes) < 60) {
    return relativeTimeFormatter.format(differenceInMinutes, 'minute')
  }

  const differenceInHours = Math.round(differenceInMinutes / 60)
  if (Math.abs(differenceInHours) < 24) {
    return relativeTimeFormatter.format(differenceInHours, 'hour')
  }

  return relativeTimeFormatter.format(Math.round(differenceInHours / 24), 'day')
})

const exactUpdatedLabel = computed(() =>
  dateTimeFormatter.format(new Date(props.ingestion.updated_at)),
)

const sourceLabel = computed(() => {
  if (!props.ingestion.source_url) return 'Pasted job description'

  try {
    return new URL(props.ingestion.source_url).hostname.replace(/^www\./, '')
  } catch {
    return 'Linked job description'
  }
})

const sourceIcon = computed(() => (props.ingestion.source_url ? Link2 : FileText))

const statusIcon = computed(() => {
  const status = props.ingestion.status
  if (status === 'processing' || status === 'queued') return LoaderCircle
  if (status === 'failed') return TriangleAlert
  if (status === 'confirmed') return Check
  if (status === 'cancelled') return RotateCcw
  return FileSearch
})

const isAnalyzing = computed(
  () => props.ingestion.status === 'processing' || props.ingestion.status === 'queued',
)

const statusContext = computed(() => {
  const status = props.ingestion.status
  if (status === 'review_ready') return 'Analysis complete. Your approval is still required.'
  if (status === 'processing') return 'Extracting the details that matter from this role.'
  if (status === 'queued') return 'Waiting for analysis to begin.'
  if (status === 'failed') return 'Analysis stopped before your review was ready.'
  if (status === 'confirmed') return 'Approved details have been saved to your opportunities.'
  if (status === 'cancelled') return 'This import is paused and can be started again.'
  return 'The job description is saved and ready to analyze.'
})

const actionHint = computed(() => {
  const status = props.ingestion.status
  if (status === 'review_ready') return 'Review extracted details'
  if (status === 'processing' || status === 'queued') return 'Open analysis progress'
  if (status === 'failed') return 'Review failure and retry'
  if (status === 'confirmed') return 'View saved opportunity'
  if (status === 'cancelled') return 'Reanalyze this job'
  return 'Open import'
})
</script>

<template>
  <RouterLink :to="to" class="opportunity-row" :class="`opportunity-row-${ingestion.status}`">
    <span class="row-icon" aria-hidden="true">
      <span class="row-icon-glyph" :class="{ 'row-icon-spinner': isAnalyzing }">
        <component :is="statusIcon" class="row-icon-svg" />
      </span>
    </span>

    <span class="row-content">
      <span class="row-heading">
        <span class="row-title">
          {{ ingestion.personal_label || `Opportunity import #${ingestion.id}` }}
        </span>
        <OpportunityStatusBadge :status="ingestion.status" compact />
      </span>

      <span class="row-meta">
        <span class="row-meta-item">
          <component :is="sourceIcon" class="row-meta-icon" aria-hidden="true" />
          {{ sourceLabel }}
        </span>
        <time class="row-meta-item" :datetime="ingestion.updated_at" :title="exactUpdatedLabel">
          <Clock3 class="row-meta-icon" aria-hidden="true" />
          Updated {{ updatedLabel }}
        </time>
      </span>
      <span class="row-context">{{ statusContext }}</span>
    </span>

    <span class="row-action">
      <span>{{ actionHint }}</span>
      <ChevronRight class="row-chevron" aria-hidden="true" />
    </span>
  </RouterLink>
</template>

<style scoped>
.opportunity-row {
  --row-tone-soft: var(--surface-secondary);
  --row-tone-ink: var(--text-muted);

  display: grid;
  position: relative;
  transform-origin: center;
  min-height: 7rem;
  grid-template-columns: 2.75rem minmax(0, 1fr) auto;
  align-items: center;
  gap: 1rem;
  overflow: hidden;
  border-radius: var(--radius-xl);
  padding: 1rem 1.125rem;
  background: var(--surface-primary);
  box-shadow: var(--shadow-neo-raised);
  color: inherit;
  text-decoration: none;
  transition: box-shadow 180ms ease;
}

.opportunity-row::after {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  pointer-events: none;
  content: '';
}

.opportunity-row:hover {
  background: var(--surface-secondary);
  box-shadow: var(--shadow-neo-raised-lg);
}

.opportunity-row:active {
  background: var(--surface-secondary);
  box-shadow: var(--shadow-neo-inset);
}

.opportunity-row:focus-visible {
  outline: 2px solid var(--color-primary-500);
  outline-offset: 3px;
}

.opportunity-row-processing,
.opportunity-row-queued {
  --row-tone-soft: var(--color-info-50);
  --row-tone-ink: var(--color-info-600);
}

.opportunity-row-review_ready {
  --row-tone-soft: var(--color-warning-50);
  --row-tone-ink: var(--color-warning-600);
}

.opportunity-row-failed {
  --row-tone-soft: var(--color-error-50);
  --row-tone-ink: var(--color-error-600);
}

.opportunity-row-confirmed {
  --row-tone-soft: var(--color-success-50);
  --row-tone-ink: var(--color-success-600);
}

.row-icon {
  display: grid;
  width: 2.75rem;
  height: 2.75rem;
  place-items: center;
  border-radius: 0.625rem;
  background: var(--row-tone-soft);
  box-shadow: var(--shadow-neo-raised-sm);
  color: var(--row-tone-ink);
}

.row-icon-glyph {
  display: grid;
  width: 1.125rem;
  height: 1.125rem;
  place-items: center;
}

.row-icon-svg {
  width: 1.125rem;
  height: 1.125rem;
}

.row-content {
  display: grid;
  min-width: 0;
}

.row-heading {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem 0.625rem;
}

.row-title {
  min-width: 0;
  overflow: hidden;
  color: var(--text-primary);
  font-size: 0.9375rem;
  font-weight: 680;
  letter-spacing: -0.012em;
  line-height: 1.25rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem 0.875rem;
  margin-top: 0.4375rem;
  color: var(--text-muted);
  font-size: 0.75rem;
  line-height: 1rem;
}

.row-meta-item {
  display: inline-flex;
  min-width: 0;
  align-items: center;
  gap: 0.3125rem;
  overflow-wrap: anywhere;
}

.row-meta-icon {
  width: 0.8125rem;
  height: 0.8125rem;
  flex: 0 0 auto;
  color: var(--text-faint);
}

.row-context {
  margin-top: 0.4375rem;
  color: var(--text-primary);
  font-size: 0.75rem;
  line-height: 1.125rem;
}

.row-action {
  display: inline-flex;
  min-height: 2.5rem;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  gap: 0.4375rem;
  border-radius: var(--radius-md);
  padding: 0.5rem 0.75rem;
  background: var(--color-primary-50);
  box-shadow: var(--shadow-neo-raised-sm);
  color: var(--color-primary-600);
  font-size: 0.75rem;
  font-weight: 680;
  line-height: 1rem;
}

.row-chevron {
  width: 1rem;
  height: 1rem;
  flex: 0 0 auto;
  color: currentColor;
  transform-origin: center;
  transition: transform 150ms ease;
}

.opportunity-row:hover .row-action {
  background: var(--color-primary-500);
  color: white;
  box-shadow: var(--shadow-neo-button);
}

.opportunity-row:hover .row-chevron,
.opportunity-row:focus-visible .row-chevron {
  transform: translateX(0.125rem);
}

@media (max-width: 39.999rem) {
  .opportunity-row {
    min-height: 0;
    grid-template-columns: 2.5rem minmax(0, 1fr);
    align-items: start;
    gap: 0.75rem;
    padding: 0.875rem;
  }

  .row-icon {
    width: 2.5rem;
    height: 2.5rem;
  }

  .row-action {
    grid-column: 2;
    justify-self: start;
    margin-top: 0.125rem;
  }

  .row-title {
    white-space: normal;
  }
}

@keyframes row-spin {
  to {
    transform: rotate(360deg);
  }
}

.row-icon-spinner {
  transform-origin: center;
  animation: row-spin 1.2s linear infinite;
}

@media (prefers-reduced-motion: reduce) {
  .opportunity-row,
  .row-action,
  .row-chevron {
    transition: none;
  }

  .row-icon-spinner {
    animation: none;
  }

  .opportunity-row:hover .row-chevron,
  .opportunity-row:focus-visible .row-chevron {
    transform: none;
  }

  .opportunity-row:hover,
  .opportunity-row:active {
    transform: none;
  }
}
</style>
