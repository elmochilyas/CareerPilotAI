<script setup lang="ts">
import { computed } from 'vue'
import type { Component } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import {
  ArrowRight,
  BookmarkCheck,
  BriefcaseBusiness,
  CircleAlert,
  Clock3,
  RefreshCw,
  Rows3,
  SearchX,
} from '@lucide/vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import { fetchIngestions, fetchOpportunities, opportunityKeys } from '../api'
import type { JobIngestion, JobOpportunity } from '../types'
import IngestionRow from '../components/IngestionRow.vue'
import OpportunityCard from '../components/OpportunityCard.vue'
import OpportunityDashboardHero from '../components/OpportunityDashboardHero.vue'

type PipelineFilter = 'all' | 'attention' | 'active' | 'saved'

interface PipelineFilterOption {
  key: PipelineFilter
  label: string
  count: number
  icon: Component
}

type WorkspaceItem =
  | {
      kind: 'ingestion'
      key: string
      updatedAt: string
      ingestion: JobIngestion
    }
  | {
      kind: 'opportunity'
      key: string
      updatedAt: string
      opportunity: JobOpportunity
    }

const router = useRouter()
const route = useRoute()

const currentPage = computed(() => {
  const page = Number(route.query.page)
  return Number.isInteger(page) && page > 0 ? page : 1
})

const ingestionsQuery = useQuery({
  queryKey: computed(() => opportunityKeys.ingestions(currentPage.value)),
  queryFn: () => fetchIngestions({ page: currentPage.value }),
  refetchInterval: 10000,
  refetchOnMount: 'always',
})

const opportunitiesQuery = useQuery({
  queryKey: computed(() => opportunityKeys.list(currentPage.value)),
  queryFn: () => fetchOpportunities({ page: currentPage.value }),
  refetchOnMount: 'always',
})

const ingestions = computed(() => ingestionsQuery.data.value?.data ?? [])
const opportunities = computed(() => opportunitiesQuery.data.value?.data ?? [])
const lastPage = computed(() =>
  Math.max(
    ingestionsQuery.data.value?.meta.last_page ?? 1,
    opportunitiesQuery.data.value?.meta.last_page ?? 1,
  ),
)
const hasPagination = computed(() => lastPage.value > 1)

const activeFilter = computed<PipelineFilter>(() => {
  const view = route.query.view
  return typeof view === 'string' && ['attention', 'active', 'saved'].includes(view)
    ? (view as PipelineFilter)
    : 'all'
})

const displayIngestions = computed(() => {
  return ingestions.value.filter(
    (ingestion) => ingestion.status !== 'confirmed' || ingestion.confirmed_opportunity_id === null,
  )
})

const workspaceItems = computed<WorkspaceItem[]>(() => {
  const ingestionItems: WorkspaceItem[] = displayIngestions.value.map((ingestion) => ({
    kind: 'ingestion',
    key: `ingestion-${ingestion.id}`,
    updatedAt: ingestion.updated_at,
    ingestion,
  }))

  const opportunityItems: WorkspaceItem[] = opportunities.value.map((opportunity) => ({
    kind: 'opportunity',
    key: `opportunity-${opportunity.id}`,
    updatedAt: opportunity.updated_at,
    opportunity,
  }))

  return [...ingestionItems, ...opportunityItems].sort(
    (left, right) => new Date(right.updatedAt).getTime() - new Date(left.updatedAt).getTime(),
  )
})

const attentionCount = computed(
  () =>
    ingestions.value.filter(
      (ingestion) => ingestion.status === 'review_ready' || ingestion.status === 'failed',
    ).length,
)

const activeCount = computed(
  () =>
    ingestions.value.filter((ingestion) =>
      ['draft', 'queued', 'processing'].includes(ingestion.status),
    ).length,
)

const filteredItems = computed(() => {
  if (activeFilter.value === 'all') return workspaceItems.value
  if (activeFilter.value === 'saved') {
    return workspaceItems.value.filter(
      (item) =>
        item.kind === 'opportunity' ||
        (item.kind === 'ingestion' && item.ingestion.status === 'confirmed'),
    )
  }

  if (activeFilter.value === 'attention') {
    return workspaceItems.value.filter(
      (item) =>
        item.kind === 'ingestion' &&
        (item.ingestion.status === 'review_ready' || item.ingestion.status === 'failed'),
    )
  }

  return workspaceItems.value.filter(
    (item) =>
      item.kind === 'ingestion' &&
      ['draft', 'queued', 'processing'].includes(item.ingestion.status),
  )
})

const filters = computed<PipelineFilterOption[]>(() => [
  { key: 'all', label: 'All', count: workspaceItems.value.length, icon: Rows3 },
  {
    key: 'attention',
    label: 'Needs review',
    count: attentionCount.value,
    icon: CircleAlert,
  },
  { key: 'active', label: 'In progress', count: activeCount.value, icon: Clock3 },
  { key: 'saved', label: 'Saved', count: opportunities.value.length, icon: BookmarkCheck },
])

const pipelineCountLabel = computed(
  () =>
    `${filteredItems.value.length} ${
      filteredItems.value.length === 1 ? 'opportunity' : 'opportunities'
    }`,
)

const pipelineDescription = computed(() => {
  const descriptions: Record<PipelineFilter, string> = {
    all: 'All opportunities, ordered by latest activity.',
    attention: 'Items waiting for your review.',
    active: 'Imports currently being analyzed.',
    saved: 'Confirmed opportunities in your shortlist.',
  }

  return descriptions[activeFilter.value]
})

const isInitialLoading = computed(
  () =>
    workspaceItems.value.length === 0 &&
    (ingestionsQuery.isPending.value || opportunitiesQuery.isPending.value),
)

const hasError = computed(() => ingestionsQuery.isError.value || opportunitiesQuery.isError.value)

function ingestionRoute(ingestion: JobIngestion): RouteLocationRaw {
  if (ingestion.status === 'confirmed' && ingestion.confirmed_opportunity_id) {
    return {
      name: 'opportunities-detail',
      params: { id: ingestion.confirmed_opportunity_id },
    }
  }

  return {
    name: ingestion.status === 'review_ready' ? 'opportunities-review' : 'opportunities-processing',
    params: { id: ingestion.id },
  }
}

function opportunityRoute(opportunity: JobOpportunity): RouteLocationRaw {
  return { name: 'opportunities-detail', params: { id: opportunity.id } }
}

function selectFilter(filter: PipelineFilter): void {
  void router.replace({
    query: {
      ...route.query,
      view: filter === 'all' ? undefined : filter,
      page: undefined,
    },
  })
}

function selectPage(page: number): void {
  const boundedPage = Math.min(Math.max(page, 1), lastPage.value)

  void router.push({
    query: {
      ...route.query,
      page: boundedPage === 1 ? undefined : String(boundedPage),
    },
  })
}

function retryQueries(): void {
  void Promise.all([ingestionsQuery.refetch(), opportunitiesQuery.refetch()])
}
</script>

<template>
  <div class="opportunities-page">
    <OpportunityDashboardHero
      :saved-count="opportunities.length"
      :active-count="activeCount"
      :attention-count="attentionCount"
      :loading="isInitialLoading"
    />

    <section class="workspace" aria-labelledby="workspace-title">
      <header class="workspace-toolbar">
        <div class="pipeline-heading">
          <span class="pipeline-heading-icon" aria-hidden="true">
            <BriefcaseBusiness />
          </span>
          <div class="pipeline-heading-copy">
            <div class="pipeline-heading-row">
              <h2 id="workspace-title">Pipeline</h2>
              <span class="pipeline-total">{{ pipelineCountLabel }}</span>
            </div>
            <p class="pipeline-description">{{ pipelineDescription }}</p>
          </div>
        </div>

        <div class="pipeline-filters" aria-label="Filter opportunity pipeline">
          <button
            v-for="filter in filters"
            :key="filter.key"
            type="button"
            class="filter-button"
            :class="[
              `filter-tone-${filter.key}`,
              { 'filter-button-active': activeFilter === filter.key },
            ]"
            :aria-pressed="activeFilter === filter.key"
            @click="selectFilter(filter.key)"
          >
            <component :is="filter.icon" class="filter-icon" aria-hidden="true" />
            <span class="filter-label">{{ filter.label }}</span>
            <span class="filter-count">{{ filter.count }}</span>
          </button>
        </div>
      </header>

      <div v-if="hasError" class="query-message" role="alert">
        <div>
          <strong>We couldn’t refresh your opportunities.</strong>
          <p>Your existing items are still shown when available.</p>
        </div>
        <button type="button" @click="retryQueries">
          <RefreshCw class="size-4" aria-hidden="true" />
          Try again
        </button>
      </div>

      <div v-if="isInitialLoading" class="pipeline-loading" aria-live="polite">
        <span class="sr-only">Loading your opportunities</span>
        <div v-for="index in 3" :key="index" class="loading-row" aria-hidden="true">
          <span />
          <div>
            <span />
            <span />
          </div>
        </div>
      </div>

      <div v-else-if="workspaceItems.length === 0" class="empty-state">
        <span class="empty-state-icon" aria-hidden="true">
          <SearchX class="size-5" />
        </span>
        <h3>Build your shortlist</h3>
        <p>Add a job description to review its details before saving it.</p>
        <RouterLink :to="{ name: 'opportunities-import' }" class="empty-state-action">
          Add opportunity
          <ArrowRight class="size-4" aria-hidden="true" />
        </RouterLink>
      </div>

      <div v-else-if="filteredItems.length === 0" class="filtered-empty">
        <p>No opportunities match this filter.</p>
        <button type="button" @click="selectFilter('all')">Show all</button>
      </div>

      <div v-else class="pipeline-list" aria-live="polite">
        <template v-for="item in filteredItems" :key="item.key">
          <IngestionRow
            v-if="item.kind === 'ingestion'"
            :ingestion="item.ingestion"
            :to="ingestionRoute(item.ingestion)"
          />
          <OpportunityCard
            v-else
            :opportunity="item.opportunity"
            :to="opportunityRoute(item.opportunity)"
          />
        </template>
      </div>

      <nav v-if="hasPagination" class="pagination" aria-label="Opportunity pages">
        <button type="button" :disabled="currentPage <= 1" @click="selectPage(currentPage - 1)">
          Previous
        </button>
        <span>Page {{ currentPage }} of {{ lastPage }}</span>
        <button
          type="button"
          :disabled="currentPage >= lastPage"
          @click="selectPage(currentPage + 1)"
        >
          Next
        </button>
      </nav>
    </section>
  </div>
</template>

<style scoped>
.opportunities-page {
  --cp-surface: #ffffff;
  --cp-surface-subtle: #fafbfc;
  --cp-surface-muted: #f2f4f7;
  --cp-ink: #172033;
  --cp-text: #344054;
  --cp-text-muted: #667085;
  --cp-text-faint: #98a2b3;
  --cp-border: #e4e7ec;
  --cp-border-strong: #cfd4dc;
  --cp-primary: #4f46e5;
  --cp-primary-deep: #4338ca;
  --cp-primary-hover: #4338ca;
  --cp-primary-soft: #eef2ff;
  --cp-info: #4f46e5;
  --cp-info-soft: #eef2ff;
  --cp-info-border: #c7d2fe;
  --cp-success: #087a5b;
  --cp-success-soft: #ecfdf3;
  --cp-success-border: #abefc6;
  --cp-warning: #b54708;
  --cp-warning-soft: #fffaeb;
  --cp-warning-border: #fedf89;
  --cp-danger: #b42318;
  --cp-danger-soft: #fef3f2;
  --cp-danger-border: #fecdca;
  --cp-radius-control: 0.625rem;
  --cp-radius-surface: 0.75rem;
  --cp-shadow-soft: 0 0.625rem 1.5rem rgb(16 24 40 / 0.06);

  display: grid;
  gap: 2rem;
  color: var(--cp-text);
  font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
}

.opportunities-page :is(button, a) {
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.workspace {
  min-width: 0;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  margin-top: 1.25rem;
  color: var(--cp-text-muted);
  font-size: 0.8125rem;
  font-variant-numeric: tabular-nums;
}

.pagination button {
  min-height: 2.75rem;
  border: 1px solid var(--cp-border);
  border-radius: var(--cp-radius-control);
  background: var(--cp-surface);
  padding: 0.5rem 0.875rem;
  color: var(--cp-text);
  font-weight: 650;
  cursor: pointer;
}

.pagination button:hover:not(:disabled) {
  border-color: var(--cp-primary);
  color: var(--cp-primary);
}

.pagination button:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 2px;
}

.pagination button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.workspace-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
  border-bottom: 1px solid var(--cp-border);
  padding-bottom: 0.625rem;
}

.pipeline-heading {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.625rem;
}

.pipeline-heading-icon {
  display: grid;
  width: 2rem;
  height: 2rem;
  flex: 0 0 auto;
  place-items: center;
  border: 1px solid var(--cp-info-border);
  border-radius: 0.5625rem;
  background: var(--cp-info-soft);
  color: var(--cp-primary-deep);
  box-shadow: 0 1px 2px rgb(16 24 40 / 0.05);
}

.pipeline-heading-icon > svg {
  width: 1rem;
  height: 1rem;
  stroke-width: 2;
}

.pipeline-heading-copy {
  min-width: 0;
}

.pipeline-heading-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pipeline-heading-row h2 {
  scroll-margin-top: 5rem;
  color: var(--cp-ink);
  font-size: 1.125rem;
  font-weight: 680;
  letter-spacing: -0.025em;
  line-height: 1.5rem;
}

.pipeline-total {
  display: inline-flex;
  min-height: 1.375rem;
  flex: 0 0 auto;
  align-items: center;
  border: 1px solid var(--cp-border);
  border-radius: 999px;
  padding: 0.125rem 0.5rem;
  background: var(--cp-surface-subtle);
  color: var(--cp-text);
  font-size: 0.6875rem;
  font-weight: 680;
  font-variant-numeric: tabular-nums;
  line-height: 1rem;
}

.pipeline-description {
  max-width: 16rem;
  margin-top: 0.0625rem;
  overflow: hidden;
  color: var(--cp-text-muted);
  font-size: 0.75rem;
  line-height: 1rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pipeline-filters {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  border: 1px solid var(--cp-border);
  border-radius: 0.75rem;
  padding: 0.25rem;
  overflow-x: auto;
  background: var(--cp-surface-muted);
  box-shadow:
    inset 0 1px 2px rgb(16 24 40 / 0.05),
    0 1px 2px rgb(16 24 40 / 0.02);
  scrollbar-width: thin;
}

.filter-button {
  --filter-accent: var(--cp-primary-deep);
  --filter-soft: var(--cp-primary-soft);

  display: inline-flex;
  min-height: 2.5rem;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.4375rem;
  border: 1px solid transparent;
  border-radius: 0.5rem;
  padding: 0.5rem 0.625rem;
  color: var(--cp-text-muted);
  font-size: 0.75rem;
  font-weight: 620;
  line-height: 1rem;
  white-space: nowrap;
  transition:
    color 150ms ease,
    transform 150ms ease;
}

.filter-tone-attention {
  --filter-accent: var(--cp-warning);
  --filter-soft: var(--cp-warning-soft);
}

.filter-button-active {
  border-color: rgb(255 255 255 / 0.9);
  background: var(--cp-surface);
  color: var(--cp-ink);
  box-shadow:
    0 1px 2px rgb(16 24 40 / 0.08),
    0 0.25rem 0.625rem rgb(16 24 40 / 0.06);
}

.filter-button-active:hover {
  color: var(--cp-ink);
}

.filter-button-active:active {
  transform: translateY(1px);
}

.filter-button-active.filter-tone-attention {
  border-color: var(--cp-warning-border);
}

.filter-tone-active {
  --filter-accent: var(--cp-info);
  --filter-soft: var(--cp-info-soft);
}

.filter-button-active.filter-tone-active {
  border-color: var(--cp-info-border);
}

.filter-tone-saved {
  --filter-accent: var(--cp-success);
  --filter-soft: var(--cp-success-soft);
}

.filter-button-active.filter-tone-saved {
  border-color: var(--cp-success-border);
}

.filter-icon {
  width: 0.9375rem;
  height: 0.9375rem;
  flex: 0 0 auto;
  color: var(--filter-accent);
  stroke-width: 2;
}

.filter-label {
  color: inherit;
}

.filter-count {
  display: grid;
  min-width: 1.25rem;
  height: 1.25rem;
  place-items: center;
  border-radius: 999px;
  padding: 0 0.3125rem;
  background: var(--filter-soft);
  color: var(--filter-accent);
  font-size: 0.625rem;
  font-weight: 720;
  font-variant-numeric: tabular-nums;
}

.filter-button:hover:not(.filter-button-active) {
  background: rgb(255 255 255 / 0.58);
  color: var(--cp-ink);
}

.filter-button:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 1px;
}

.pipeline-list,
.pipeline-loading {
  display: grid;
  gap: 0.625rem;
  padding-top: 1rem;
}

.query-message {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1rem;
  border: 1px solid var(--cp-danger-border);
  border-radius: var(--cp-radius-surface);
  padding: 0.875rem 1rem;
  background: var(--cp-danger-soft);
  color: var(--cp-danger);
}

.query-message strong {
  display: block;
  font-size: 0.8125rem;
  font-weight: 680;
}

.query-message p {
  margin-top: 0.125rem;
  font-size: 0.75rem;
}

.query-message button {
  display: inline-flex;
  min-height: 2.5rem;
  flex: 0 0 auto;
  align-items: center;
  gap: 0.375rem;
  border-radius: var(--cp-radius-control);
  padding: 0.5rem 0.75rem;
  background: white;
  font-size: 0.75rem;
  font-weight: 680;
}

.query-message button:hover {
  background: var(--cp-danger-border);
}

.query-message button:focus-visible {
  outline: 2px solid var(--cp-danger);
  outline-offset: 2px;
}

.loading-row {
  display: grid;
  min-height: 6.25rem;
  grid-template-columns: 2.5rem minmax(0, 1fr);
  align-items: center;
  gap: 1rem;
  border: 1px solid var(--cp-border);
  border-radius: var(--cp-radius-surface);
  padding: 1rem 1.125rem;
  background: white;
}

.loading-row > span {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.625rem;
}

.loading-row > div {
  display: grid;
  gap: 0.5rem;
}

.loading-row > div > span:first-child {
  width: min(18rem, 70%);
  height: 0.75rem;
  border-radius: 999px;
}

.loading-row > div > span:last-child {
  width: min(12rem, 50%);
  height: 0.625rem;
  border-radius: 999px;
}

.loading-row span {
  background: linear-gradient(90deg, #edf0f5 25%, #f7f8fb 50%, #edf0f5 75%);
  background-size: 200% 100%;
  animation: opportunity-shimmer 1.4s ease-in-out infinite;
}

.empty-state,
.filtered-empty {
  display: grid;
  justify-items: center;
  margin-top: 1rem;
  border: 1px dashed var(--cp-border-strong);
  border-radius: var(--cp-radius-surface);
  padding: 3.5rem 1.5rem;
  background: var(--cp-surface-subtle);
  text-align: center;
}

.empty-state-icon {
  display: grid;
  width: 2.75rem;
  height: 2.75rem;
  place-items: center;
  border-radius: 0.625rem;
  background: var(--cp-primary-soft);
  color: var(--cp-primary-deep);
}

.empty-state h3 {
  margin-top: 1rem;
  color: var(--cp-ink);
  font-size: 1.125rem;
  font-weight: 680;
  letter-spacing: -0.025em;
}

.empty-state > p {
  max-width: 28rem;
  margin-top: 0.375rem;
  color: var(--cp-text-muted);
  font-size: 0.8125rem;
  line-height: 1.25rem;
}

.empty-state-action {
  display: inline-flex;
  min-height: 2.75rem;
  align-items: center;
  gap: 0.5rem;
  margin-top: 1.25rem;
  border-radius: var(--cp-radius-control);
  padding: 0.625rem 0.875rem;
  background: var(--cp-primary);
  color: white;
  font-size: 0.8125rem;
  font-weight: 680;
}

.empty-state-action:hover {
  background: var(--cp-primary-hover);
}

.empty-state-action:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 3px;
}

.filtered-empty {
  gap: 0.375rem;
  padding-block: 2.5rem;
  color: var(--cp-text-muted);
  font-size: 0.8125rem;
}

.filtered-empty button {
  min-height: 2.75rem;
  padding: 0.625rem 0.75rem;
  color: var(--cp-primary-deep);
  font-weight: 680;
}

.filtered-empty button:hover {
  background: var(--cp-primary-soft);
}

.filtered-empty button:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 2px;
}

@keyframes opportunity-shimmer {
  to {
    background-position: -200% 0;
  }
}

@media (max-width: 47.999rem) {
  .workspace-toolbar {
    display: grid;
    gap: 0.625rem;
  }

  .pipeline-filters {
    width: 100%;
  }
}

@media (max-width: 39.999rem) {
  .opportunities-page {
    gap: 1.5rem;
  }

  .pipeline-filters {
    gap: 0.25rem;
  }

  .filter-button {
    min-height: 2.75rem;
    gap: 0.25rem;
    padding-inline: 0.25rem;
    font-size: 0.6875rem;
  }

  .filter-icon {
    display: none;
  }

  .filter-count {
    min-width: 1.125rem;
    height: 1.125rem;
    padding-inline: 0.25rem;
  }

  .query-message {
    display: grid;
  }

  .query-message button {
    justify-self: start;
  }
}

@media (prefers-reduced-motion: reduce) {
  .opportunities-page *,
  .opportunities-page *::before,
  .opportunities-page *::after {
    scroll-behavior: auto !important;
    animation: none !important;
    transition-duration: 0.01ms !important;
  }
}
</style>
