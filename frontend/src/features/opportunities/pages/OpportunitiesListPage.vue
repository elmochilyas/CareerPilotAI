<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { fetchIngestions, fetchOpportunities, opportunityKeys } from '../api'
import { useRouter } from 'vue-router'
import OpportunityCard from '../components/OpportunityCard.vue'

const router = useRouter()

const ingestionsQuery = useQuery({
  queryKey: opportunityKeys.ingestions(),
  queryFn: () => fetchIngestions(),
  refetchInterval: 10000,
  refetchOnMount: 'always',
})

const opportunitiesQuery = useQuery({
  queryKey: opportunityKeys.list(),
  queryFn: () => fetchOpportunities(),
  refetchOnMount: 'always',
})

function getStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    draft: 'Waiting to process',
    queued: 'In queue',
    processing: 'Analyzing',
    review_ready: 'Ready for review',
    confirmed: 'Confirmed',
    failed: 'Analysis failed',
    cancelled: 'Cancelled',
  }
  return labels[status] ?? status
}

function getStatusClass(status: string): string {
  const classes: Record<string, string> = {
    review_ready: 'text-yellow-600 bg-yellow-50 border-yellow-200',
    confirmed: 'text-green-600 bg-green-50 border-green-200',
    failed: 'text-red-600 bg-red-50 border-red-200',
    processing: 'text-blue-600 bg-blue-50 border-blue-200',
    queued: 'text-gray-600 bg-gray-50 border-gray-200',
  }
  return classes[status] ?? 'text-gray-500 bg-gray-50 border-gray-200'
}

function getNextAction(status: string, id: number): { label: string; route: string } | null {
  if (status === 'review_ready')
    return { label: 'Review now', route: `/opportunities/ingestions/${id}/review` }
  if (status === 'processing' || status === 'queued')
    return { label: 'View progress', route: `/opportunities/ingestions/${id}` }
  if (status === 'failed')
    return { label: 'View details', route: `/opportunities/ingestions/${id}` }
  return null
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Opportunities</h1>
      <button
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none"
        @click="router.push('/opportunities/import')"
      >
        Import job description
      </button>
    </div>

    <div class="space-y-8">
      <div>
        <h2 class="mb-3 text-lg font-semibold">Confirmed Opportunities</h2>

        <div v-if="opportunitiesQuery.isPending.value" class="py-8 text-center text-gray-500">
          Loading…
        </div>

        <div
          v-else-if="opportunitiesQuery.isError.value"
          class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
          Failed to load opportunities.
        </div>

        <div
          v-else-if="(opportunitiesQuery.data.value?.data?.length ?? 0) === 0"
          class="py-8 text-center text-gray-500"
        >
          No confirmed opportunities yet.
        </div>

        <div v-else class="space-y-3">
          <button
            v-for="opp in opportunitiesQuery.data.value?.data ?? []"
            :key="opp.id"
            type="button"
            class="block w-full rounded-lg text-left focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none"
            @click="router.push(`/opportunities/${opp.id}`)"
          >
            <OpportunityCard :opportunity="opp" />
          </button>
        </div>
      </div>

      <div>
        <h2 class="mb-3 text-lg font-semibold">Import History</h2>

        <div v-if="ingestionsQuery.isPending.value" class="py-8 text-center text-gray-500">
          Loading…
        </div>

        <div
          v-else-if="ingestionsQuery.isError.value"
          class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
          Failed to load import history.
        </div>

        <div
          v-else-if="(ingestionsQuery.data.value?.data?.length ?? 0) === 0"
          class="py-8 text-center text-gray-500"
        >
          No imports yet.
        </div>

        <div v-else class="space-y-3">
          <button
            v-for="ing in ingestionsQuery.data.value?.data ?? []"
            :key="'ing-' + ing.id"
            type="button"
            class="block w-full rounded-lg border bg-white p-4 text-left shadow-sm hover:shadow-md focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none"
            :class="ing.status === 'confirmed' ? 'border-green-200' : 'border-gray-200'"
            @click="
              ing.status === 'review_ready'
                ? router.push(`/opportunities/ingestions/${ing.id}/review`)
                : router.push(`/opportunities/ingestions/${ing.id}`)
            "
          >
            <div class="flex items-start justify-between">
              <div>
                <p v-if="ing.personal_label" class="font-medium text-gray-900">
                  {{ ing.personal_label }}
                </p>
                <p v-else class="text-sm text-gray-500">Ingestion #{{ ing.id }}</p>
              </div>
              <span
                class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                :class="getStatusClass(ing.status)"
              >
                {{ getStatusLabel(ing.status) }}
              </span>
            </div>
            <div class="mt-2 flex items-center gap-4 text-xs text-gray-400">
              <span>{{ new Date(ing.created_at).toLocaleDateString() }}</span>
            </div>
            <div v-if="getNextAction(ing.status, ing.id)" class="mt-2">
              <span class="text-sm font-medium text-blue-600">
                {{ getNextAction(ing.status, ing.id)?.label }}
              </span>
            </div>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
