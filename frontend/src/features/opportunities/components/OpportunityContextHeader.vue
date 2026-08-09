<script setup lang="ts">
import { BriefcaseBusiness, Clock3, MapPin, Monitor, Tag } from '@lucide/vue'
import MetadataChip from './MetadataChip.vue'
import OpportunityStatusBadge from './OpportunityStatusBadge.vue'
import type { IngestionStatus } from '../types'

withDefaults(
  defineProps<{
    title?: string | null
    company?: string | null
    personalLabel?: string | null
    location?: string | null
    workMode?: string | null
    contractType?: string | null
    seniority?: string | null
    status?: IngestionStatus
    updatedAt?: string | null
    reviewed: number
    total: number
  }>(),
  {
    title: null,
    company: null,
    personalLabel: null,
    location: null,
    workMode: null,
    contractType: null,
    seniority: null,
    status: undefined,
    updatedAt: null,
  },
)

function initials(value: string | null | undefined): string {
  const source = value?.trim()
  if (!source) return 'JO'
  return source
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

function formatMetadata(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function formatUpdatedAt(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('en', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(date)
}
</script>

<template>
  <section class="context-header" aria-labelledby="job-context-title">
    <div class="context-identity">
      <div class="company-avatar" aria-hidden="true">{{ initials(company || title) }}</div>
      <div class="context-copy">
        <div class="context-title-row">
          <div>
            <h2 id="job-context-title" class="context-title">{{ title || 'Job opportunity' }}</h2>
            <p v-if="company" class="context-company">{{ company }}</p>
          </div>
          <OpportunityStatusBadge v-if="status" :status="status" />
        </div>

        <div
          v-if="personalLabel || location || workMode || contractType || seniority"
          class="context-metadata"
          aria-label="Job metadata"
        >
          <MetadataChip v-if="personalLabel" :label="personalLabel" :icon="Tag" tone="primary" />
          <MetadataChip v-if="location" :label="location" :icon="MapPin" />
          <MetadataChip v-if="workMode" :label="formatMetadata(workMode)" :icon="Monitor" />
          <MetadataChip
            v-if="contractType"
            :label="formatMetadata(contractType)"
            :icon="BriefcaseBusiness"
          />
          <MetadataChip v-if="seniority" :label="formatMetadata(seniority)" />
        </div>
      </div>
    </div>

    <div class="context-progress">
      <div class="progress-heading">
        <div>
          <span class="progress-label">Review progress</span>
          <strong aria-live="polite">{{ reviewed }} of {{ total }} reviewed</strong>
        </div>
        <span v-if="updatedAt" class="updated-at">
          <Clock3 class="updated-icon" aria-hidden="true" />
          Updated {{ formatUpdatedAt(updatedAt) }}
        </span>
      </div>
      <div
        class="progress-track"
        role="progressbar"
        :aria-label="`${reviewed} of ${total} review items complete`"
        :aria-valuenow="reviewed"
        aria-valuemin="0"
        :aria-valuemax="total"
      >
        <span :style="{ transform: `scaleX(${total > 0 ? reviewed / total : 0})` }" />
      </div>
    </div>
  </section>
</template>

<style scoped>
.context-header {
  position: relative;
  overflow: hidden;
  max-width: 65rem;
  margin: 1.25rem auto 0;
  border-radius: var(--radius-xl);
  background: var(--surface-primary);
  padding: 1.375rem;
  box-shadow: var(--shadow-neo-raised-lg);
  color: var(--text-primary);
}

.context-identity {
  position: relative;
  z-index: 1;
  display: flex;
  min-width: 0;
  align-items: flex-start;
  gap: 1rem;
}

.company-avatar {
  display: grid;
  position: relative;
  width: 3.5rem;
  height: 3.5rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: var(--radius-xl);
  background: var(--color-primary-500);
  box-shadow: var(--shadow-neo-raised);
  color: white;
  font-size: 0.9375rem;
  font-weight: 760;
  letter-spacing: 0.04em;
}

.context-copy {
  min-width: 0;
  flex: 1;
}

.context-title-row {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.75rem;
}

.context-title {
  overflow-wrap: anywhere;
  color: var(--text-primary);
  font-size: 1.375rem;
  font-weight: 690;
  letter-spacing: -0.025em;
  line-height: 1.75rem;
}

.context-company {
  margin-top: 0.25rem;
  color: var(--text-muted);
  font-size: 0.875rem;
  font-weight: 520;
  line-height: 1.25rem;
}

.context-metadata {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.875rem;
}

.context-progress {
  position: relative;
  z-index: 1;
  margin-top: 1.25rem;
  border-top: 1px solid var(--border-subtle);
  padding-top: 1rem;
}

.progress-heading {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  color: var(--text-muted);
  font-size: 0.75rem;
  line-height: 1.125rem;
}

.progress-heading > div {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
}

.progress-label {
  font-weight: 620;
  letter-spacing: 0.01em;
}

.progress-heading strong {
  color: var(--text-primary);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
  font-weight: 670;
}

.updated-at {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
}

.updated-icon {
  width: 0.875rem;
  height: 0.875rem;
}

.progress-track {
  height: 0.4375rem;
  margin-top: 0.625rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--surface-secondary);
  box-shadow: var(--shadow-neo-inset);
}

.progress-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--color-primary-500);
  box-shadow: 0 0 0.75rem var(--color-primary-200);
  transform-origin: left center;
  transition: transform 160ms ease;
}

.context-header :deep(.metadata-chip) {
  background: var(--surface-secondary);
  color: var(--text-muted);
  box-shadow: var(--shadow-neo-raised-sm);
}

.context-header :deep(.metadata-chip-primary) {
  background: var(--color-primary-50);
  color: var(--color-primary-600);
}

.context-header :deep(.status-badge) {
  background: var(--surface-secondary);
  color: var(--text-muted);
  box-shadow: var(--shadow-neo-raised-sm);
}

@media (min-width: 40rem) {
  .context-header {
    padding: 1.5rem 1.625rem;
  }

  .context-title-row,
  .progress-heading {
    flex-direction: row;
    align-items: flex-start;
    justify-content: space-between;
  }
}

@media (prefers-reduced-motion: reduce) {
  .progress-track span {
    transition: none;
  }
}
</style>
