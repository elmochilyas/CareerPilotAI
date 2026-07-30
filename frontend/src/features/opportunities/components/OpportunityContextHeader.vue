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
  border: 1px solid rgb(255 255 255 / 0.12);
  border-radius: var(--cp-radius-panel);
  background: linear-gradient(118deg, var(--cp-context-start), var(--cp-context-end));
  padding: 1.375rem;
  box-shadow: var(--cp-shadow-brand);
  color: var(--cp-text-inverse);
}

.context-header::before {
  position: absolute;
  top: 0;
  right: 1.5rem;
  left: 1.5rem;
  height: 1px;
  background: linear-gradient(
    90deg,
    transparent,
    rgb(255 255 255 / 0.48),
    var(--cp-primary-bright),
    transparent
  );
  content: '';
}

.context-header::after {
  position: absolute;
  top: -7rem;
  right: -5rem;
  width: 17rem;
  height: 17rem;
  border-radius: 999px;
  background: radial-gradient(circle, var(--cp-primary-glow), transparent 68%);
  content: '';
  pointer-events: none;
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
  border: 1px solid rgb(255 255 255 / 0.24);
  border-radius: var(--cp-radius-card);
  background: linear-gradient(145deg, var(--cp-primary-bright), var(--cp-primary-deep));
  box-shadow:
    0 0 0 0.25rem rgb(255 255 255 / 0.07),
    0 0.625rem 1.5rem rgb(5 8 20 / 0.28);
  color: var(--cp-text-inverse);
  font-size: 0.9375rem;
  font-weight: 760;
  letter-spacing: 0.04em;
  text-shadow: 0 1px 1px rgb(5 8 20 / 0.25);
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
  color: var(--cp-text-inverse);
  font-size: 1.375rem;
  font-weight: 690;
  letter-spacing: -0.025em;
  line-height: 1.75rem;
}

.context-company {
  margin-top: 0.25rem;
  color: rgb(255 255 255 / 0.7);
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
  border-top: 1px solid rgb(255 255 255 / 0.12);
  padding-top: 1rem;
}

.progress-heading {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  color: rgb(255 255 255 / 0.62);
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
  color: var(--cp-text-inverse);
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
  background: rgb(255 255 255 / 0.12);
  box-shadow: 0 1px 0 rgb(0 0 0 / 0.18) inset;
}

.progress-track span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(
    90deg,
    var(--cp-context-progress-start),
    var(--cp-context-progress-end)
  );
  box-shadow: 0 0 0.75rem var(--cp-primary-glow);
  transform-origin: left center;
  transition: transform 160ms ease;
}

.context-header :deep(.metadata-chip) {
  border-color: rgb(255 255 255 / 0.14);
  background: rgb(255 255 255 / 0.075);
  color: rgb(255 255 255 / 0.78);
  box-shadow: 0 1px 0 rgb(255 255 255 / 0.06) inset;
}

.context-header :deep(.metadata-chip-primary) {
  border-color: rgb(174 161 255 / 0.34);
  background: rgb(109 93 251 / 0.18);
  color: var(--cp-context-accent);
}

.context-header :deep(.status-badge) {
  border-color: rgb(255 255 255 / 0.15);
  background: rgb(255 255 255 / 0.08);
  color: rgb(255 255 255 / 0.84);
  box-shadow: 0 1px 0 rgb(255 255 255 / 0.06) inset;
}

.context-header :deep(.status-dot) {
  box-shadow: 0 0 0 0.1875rem rgb(255 255 255 / 0.08);
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
