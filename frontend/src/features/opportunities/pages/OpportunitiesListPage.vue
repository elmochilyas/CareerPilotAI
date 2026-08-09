<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { Clock3, FileSearch, Plus, RefreshCw, SearchX } from '@lucide/vue'
import { useRoute, useRouter } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import PageHeader from '@/components/ui/PageHeader.vue'
import SearchInput from '@/components/ui/SearchInput.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import { fetchIngestions, fetchOpportunities, opportunityKeys } from '../api'
import type { JobIngestion, JobOpportunity } from '../types'
import IngestionRow from '../components/IngestionRow.vue'
import OpportunityCard from '../components/OpportunityCard.vue'
import AddOpportunityModal from '../components/AddOpportunityModal.vue'

type PipelineFilter = 'all' | 'attention' | 'active' | 'saved'

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

interface StatusGroup {
  label: string
  items: WorkspaceItem[]
}

const router = useRouter()
const route = useRoute()

const showAddModal = ref(false)
const searchQuery = ref('')

const currentPage = computed(() => {
  const page = Number(route.query.page)
  return Number.isInteger(page) && page > 0 ? page : 1
})

const ingestionsQuery = useQuery({
  queryKey: computed(() => opportunityKeys.ingestions(currentPage.value)),
  queryFn: () => fetchIngestions({ page: currentPage.value }),
  refetchInterval: (query) => {
    const hasActive = query.state.data?.data?.some((i) =>
      ['draft', 'queued', 'processing'].includes(i.status),
    )
    return hasActive ? 10000 : false
  },
})

const opportunitiesQuery = useQuery({
  queryKey: computed(() => opportunityKeys.list(currentPage.value)),
  queryFn: () => fetchOpportunities({ page: currentPage.value }),
})

const ingestions = computed(() => ingestionsQuery.data.value?.data ?? [])
const opportunities = computed(() => opportunitiesQuery.data.value?.data ?? [])
const lastPage = computed(() =>
  Math.max(
    ingestionsQuery.data.value?.meta?.last_page ?? 1,
    opportunitiesQuery.data.value?.meta?.last_page ?? 1,
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

const searchFiltered = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()
  if (!q) return workspaceItems.value
  return workspaceItems.value.filter((item) => {
    if (item.kind === 'ingestion') {
      const label = item.ingestion.personal_label ?? ''
      return (
        (label.toLowerCase().includes(q) || item.ingestion.source_url?.toLowerCase().includes(q)) ??
        false
      )
    }
    return (
      (item.opportunity.title.toLowerCase().includes(q) ||
        item.opportunity.company_name?.toLowerCase().includes(q)) ??
      false
    )
  })
})

const filteredItems = computed(() => {
  if (activeFilter.value === 'all') return searchFiltered.value
  if (activeFilter.value === 'saved') {
    return searchFiltered.value.filter(
      (item) =>
        item.kind === 'opportunity' ||
        (item.kind === 'ingestion' && item.ingestion.status === 'confirmed'),
    )
  }
  if (activeFilter.value === 'attention') {
    return searchFiltered.value.filter(
      (item) =>
        item.kind === 'ingestion' &&
        (item.ingestion.status === 'review_ready' || item.ingestion.status === 'failed'),
    )
  }
  return searchFiltered.value.filter(
    (item) =>
      item.kind === 'ingestion' &&
      ['draft', 'queued', 'processing'].includes(item.ingestion.status),
  )
})

const statusGroups = computed<StatusGroup[]>(() => {
  const processing: WorkspaceItem[] = []
  const needsReview: WorkspaceItem[] = []
  const saved: WorkspaceItem[] = []

  for (const item of filteredItems.value) {
    if (item.kind === 'ingestion') {
      if (['draft', 'queued', 'processing'].includes(item.ingestion.status)) {
        processing.push(item)
      } else if (item.ingestion.status === 'review_ready' || item.ingestion.status === 'failed') {
        needsReview.push(item)
      }
    } else {
      saved.push(item)
    }
  }

  return [
    { label: 'Processing', items: processing },
    { label: 'Needs Review', items: needsReview },
    { label: 'Saved', items: saved },
  ].filter((g) => g.items.length > 0)
})

const allCount = computed(() => searchFiltered.value.length)
const attentionCount = computed(
  () =>
    searchFiltered.value.filter(
      (item) =>
        item.kind === 'ingestion' &&
        (item.ingestion.status === 'review_ready' || item.ingestion.status === 'failed'),
    ).length,
)
const activeCount = computed(
  () =>
    searchFiltered.value.filter(
      (item) =>
        item.kind === 'ingestion' &&
        ['draft', 'queued', 'processing'].includes(item.ingestion.status),
    ).length,
)
const savedCount = computed(
  () =>
    searchFiltered.value.filter(
      (item) =>
        item.kind === 'opportunity' ||
        (item.kind === 'ingestion' && item.ingestion.status === 'confirmed'),
    ).length,
)

const tabItems = computed(() => [
  { key: 'all', label: `All (${allCount.value})` },
  { key: 'attention', label: `Needs review (${attentionCount.value})`, icon: FileSearch },
  { key: 'active', label: `Processing (${activeCount.value})`, icon: Clock3 },
  { key: 'saved', label: `Saved (${savedCount.value})` },
])

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

function selectFilter(filter: string): void {
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
  <div class="mx-auto max-w-5xl space-y-6">
    <PageHeader title="Opportunities" description="Manage your job opportunity pipeline.">
      <Button @click="showAddModal = true">
        <Plus class="size-4" aria-hidden="true" />
        Add Opportunity
      </Button>
    </PageHeader>

    <SearchInput v-model="searchQuery" placeholder="Search opportunities..." />

    <Tabs :model-value="activeFilter" :tabs="tabItems" @update:model-value="selectFilter" />

    <!-- Error banner -->
    <div
      v-if="hasError"
      role="alert"
      class="flex items-center justify-between gap-3 rounded-[var(--radius-lg)] border border-[var(--color-error-100)] bg-[var(--color-error-50)] p-4 text-sm text-[var(--color-error-700)]"
    >
      <div>
        <strong>We couldn't refresh your opportunities.</strong>
        <p class="mt-0.5 text-xs text-[var(--color-error-600)]">
          Your existing items are still shown when available.
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-[var(--radius-md)] bg-white px-3 py-1.5 text-xs font-medium text-[var(--color-error-700)] transition-colors hover:bg-[var(--color-error-50)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-error-600)]"
        @click="retryQueries"
      >
        <RefreshCw class="size-3.5" aria-hidden="true" />
        Try again
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="isInitialLoading" class="space-y-3" role="status" aria-live="polite">
      <span class="sr-only">Loading your opportunities</span>
      <div
        v-for="index in 3"
        :key="index"
        class="h-20 animate-pulse rounded-lg border border-slate-200 bg-white"
        aria-hidden="true"
      />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="workspaceItems.length === 0"
      class="rounded-[var(--radius-xl)] border border-dashed border-[var(--border-default)] bg-[var(--surface-secondary)] px-6 py-12 text-center"
    >
      <div
        class="mx-auto flex size-12 items-center justify-center rounded-full bg-[var(--color-primary-50)]"
      >
        <SearchX class="size-5 text-[var(--color-primary-600)]" aria-hidden="true" />
      </div>
      <h3 class="mt-4 text-base font-semibold text-[var(--text-primary)]">Build your shortlist</h3>
      <p class="mt-1 text-sm text-[var(--text-secondary)]">
        Add a job description to review its details before saving it.
      </p>
      <Button class="mt-4" @click="showAddModal = true">
        <Plus class="size-4" aria-hidden="true" />
        Add opportunity
      </Button>
    </div>

    <!-- Filtered empty -->
    <div
      v-else-if="filteredItems.length === 0"
      class="rounded-[var(--radius-xl)] border border-dashed border-[var(--border-default)] bg-[var(--surface-secondary)] px-6 py-8 text-center text-sm text-[var(--text-secondary)]"
    >
      <p>No opportunities match this filter.</p>
      <button
        type="button"
        class="mt-2 font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)]"
        @click="selectFilter('all')"
      >
        Show all
      </button>
    </div>

    <!-- Pipeline groups -->
    <div v-else class="space-y-8" aria-live="polite">
      <section
        v-for="group in statusGroups"
        :key="group.label"
        aria-labelledby="`group-${group.label}`"
      >
        <h2
          :id="`group-${group.label}`"
          class="mb-3 flex items-center gap-2 text-[var(--text-xs)] font-semibold uppercase tracking-wider text-[var(--text-muted)]"
        >
          {{ group.label }}
          <Badge size="sm" variant="default">{{ group.items.length }}</Badge>
        </h2>

        <div
          class="rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] shadow-[var(--shadow-xs)]"
        >
          <div class="divide-y divide-slate-100">
            <template v-for="item in group.items" :key="item.key">
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
        </div>
      </section>
    </div>

    <!-- Pagination -->
    <div v-if="hasPagination" class="flex items-center justify-center gap-4 pt-2">
      <span class="text-sm text-slate-500"> Page {{ currentPage }} of {{ lastPage }} </span>
      <Pagination
        :current-page="currentPage"
        :total-pages="lastPage"
        @update:current-page="selectPage"
      />
    </div>

    <AddOpportunityModal :open="showAddModal" @close="showAddModal = false" />
  </div>
</template>
