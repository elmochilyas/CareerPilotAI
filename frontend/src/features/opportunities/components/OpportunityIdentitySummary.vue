<script setup lang="ts">
import { computed } from 'vue'
import type { JobSuggestion } from '../types'
import { getSuggestionField, isSuggestionDisplayable } from '../utils/suggestionFormatters'

const props = defineProps<{
  suggestions: JobSuggestion[]
  reviewedCount: number
  totalCount: number
}>()

const jobTitle = computed(() => findDisplayableValue('job_title'))
const companyName = computed(() => findDisplayableValue('company'))
const city = computed(() => findDisplayableValue('city'))
const region = computed(() => findDisplayableValue('region'))
const workMode = computed(() => findDisplayableValue('work_mode'))
const contractType = computed(() => findDisplayableValue('contract_type'))
const compensation = computed(() => findDisplayableValue('compensation'))

const companyInitials = computed(() => {
  const name = companyName.value
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0]!.slice(0, 2).toUpperCase()
  return (parts[0]![0]! + parts[parts.length - 1]![0]!).toUpperCase()
})

const locationParts = computed(() => {
  const parts: string[] = []
  if (city.value) parts.push(city.value)
  if (region.value) parts.push(region.value)
  return parts
})

const metadataItems = computed(() => {
  const items: string[] = []
  if (workMode.value) items.push(workMode.value)
  if (locationParts.value.length > 0) items.push(locationParts.value.join(', '))
  if (contractType.value) items.push(contractType.value)
  if (compensation.value) items.push(compensation.value)
  return items
})

const hasMetadata = computed(() => metadataItems.value.length > 0)

function findDisplayableValue(type: string): string {
  const sug = props.suggestions.find((s) => s.type === type && isSuggestionDisplayable(s))
  if (!sug) return ''
  return getSuggestionField(sug)
}
</script>

<template>
  <section
    v-if="jobTitle || companyName"
    class="opportunity-identity"
    aria-label="Job opportunity summary"
  >
    <div class="identity-layout">
      <div class="company-monogram" aria-hidden="true">
        <span class="monogram-letters">{{ companyInitials }}</span>
      </div>

      <div class="identity-content">
        <h2 class="identity-title">{{ jobTitle || 'Untitled position' }}</h2>
        <p v-if="companyName" class="identity-company">{{ companyName }}</p>

        <div v-if="hasMetadata" class="identity-metadata">
          <span v-for="(item, idx) in metadataItems" :key="idx" class="metadata-item">
            <span v-if="idx > 0" class="metadata-sep" aria-hidden="true">·</span>
            {{ item }}
          </span>
        </div>

        <div class="identity-progress">
          <div class="progress-label" aria-live="polite">
            <strong v-if="totalCount > 0 && reviewedCount === totalCount"> All reviewed </strong>
            <strong v-else-if="totalCount > 0">
              {{ reviewedCount }} of {{ totalCount }} reviewed
            </strong>
            <span v-else>No items to review</span>
          </div>
          <div
            v-if="totalCount > 0"
            class="progress-track"
            role="progressbar"
            :aria-label="`${reviewedCount} of ${totalCount} review items complete`"
            :aria-valuenow="reviewedCount"
            aria-valuemin="0"
            :aria-valuemax="totalCount"
            :aria-valuetext="`${reviewedCount} of ${totalCount}`"
          >
            <span :style="{ transform: `scaleX(${reviewedCount / totalCount})` }" />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.opportunity-identity {
  border-bottom: 1px solid var(--cp-divider);
  padding-bottom: 1.25rem;
}

.identity-layout {
  display: grid;
  grid-template-columns: 3.25rem minmax(0, 1fr);
  gap: 1rem;
  align-items: start;
}

.company-monogram {
  display: grid;
  width: 3.25rem;
  height: 3.25rem;
  place-items: center;
  border-radius: 0.625rem;
  background: var(--cp-primary);
  color: var(--cp-text-inverse);
}

.monogram-letters {
  font-size: 0.9375rem;
  font-weight: 720;
  letter-spacing: -0.02em;
  line-height: 1;
}

.identity-content {
  min-width: 0;
}

.identity-title {
  overflow-wrap: anywhere;
  color: var(--cp-ink);
  font-size: 1.25rem;
  font-weight: 650;
  letter-spacing: -0.022em;
  line-height: 1.625rem;
  text-wrap: pretty;
}

.identity-company {
  margin-top: 0.125rem;
  color: var(--cp-text-muted);
  font-size: 0.875rem;
  line-height: 1.375rem;
}

.identity-metadata {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.625rem;
}

.metadata-item {
  color: var(--cp-text-muted);
  font-size: 0.8125rem;
  font-weight: 540;
  line-height: 1.25rem;
}

.metadata-sep {
  margin-right: 0.25rem;
  color: var(--cp-text-faint);
}

.identity-progress {
  margin-top: 0.875rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--cp-divider);
}

.progress-label {
  color: var(--cp-text-muted);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.125rem;
}

.progress-label strong {
  color: var(--cp-text);
  font-weight: 640;
}

.progress-track {
  width: 100%;
  height: 0.1875rem;
  margin-top: 0.5rem;
  overflow: hidden;
  border-radius: 999px;
  background: var(--cp-surface-muted);
}

.progress-track > span {
  display: block;
  width: 100%;
  height: 100%;
  border-radius: inherit;
  background: var(--cp-primary);
  transform-origin: left center;
  transition: transform 150ms ease;
}

@media (min-width: 40rem) {
  .identity-title {
    font-size: 1.375rem;
    line-height: 1.75rem;
  }

  .identity-layout {
    grid-template-columns: 4rem minmax(0, 1fr);
    gap: 1.25rem;
  }

  .company-monogram {
    width: 4rem;
    height: 4rem;
  }

  .monogram-letters {
    font-size: 1.125rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .progress-track > span {
    transition: none;
  }
}
</style>
