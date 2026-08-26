<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { User, FileText, Briefcase, Sparkles, AlertTriangle, RefreshCw } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { fetchProfile, profileKeys } from '@/features/profile/api'
import { fetchCvDocuments, cvKeys } from '@/features/cv-ingestion/api'
import { fetchIngestions, opportunityKeys } from '@/features/opportunities/api'
import { fetchCandidateSkills, skillKeys } from '@/features/skills/api'
import Skeleton from '@/components/ui/Skeleton.vue'
import Card from '@/components/ui/Card.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Button from '@/components/ui/Button.vue'
import StatCard from '@/features/dashboard/components/StatCard.vue'
import AttentionList from '@/features/dashboard/components/AttentionList.vue'
import QuickActions from '@/features/dashboard/components/QuickActions.vue'

const auth = useAuthStore()

const firstName = computed(() => {
  const name = auth.user?.full_name
  if (!name) return 'there'
  return name.split(' ')[0]
})

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 18) return 'Good afternoon'
  return 'Good evening'
})

const today = computed(() =>
  new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }),
)

const profileQuery = useQuery({
  queryKey: profileKeys.detail(),
  queryFn: fetchProfile,
})

const cvQuery = useQuery({
  queryKey: cvKeys.list(),
  queryFn: fetchCvDocuments,
})

const ingestionsQuery = useQuery({
  queryKey: opportunityKeys.ingestions(),
  queryFn: () => fetchIngestions(),
})

const skillsQuery = useQuery({
  queryKey: skillKeys.candidate(),
  queryFn: () => fetchCandidateSkills(),
})

const isInitialLoading = computed(
  () =>
    profileQuery.isPending.value &&
    cvQuery.isPending.value &&
    ingestionsQuery.isPending.value &&
    skillsQuery.isPending.value,
)

const cvCount = computed(() => cvQuery.data.value?.length ?? 0)
const skillsCount = computed(() => skillsQuery.data.value?.length ?? 0)
const savedJobsCount = computed(() => ingestionsQuery.data.value?.meta?.total ?? 0)

const attentionItems = computed(() => {
  const ingestions = ingestionsQuery.data.value?.data ?? []
  return ingestions.filter((i) => i.status === 'review_ready' || i.status === 'failed')
})
</script>

<template>
  <div class="ds-animate-fade-in mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
    <!-- Header -->
    <header>
      <h1
        class="text-[var(--text-2xl)] font-bold tracking-tight text-[var(--text-primary)] sm:text-[var(--text-3xl)]"
      >
        {{ greeting }}, {{ firstName }}
      </h1>
      <p class="mt-1 text-[var(--text-sm)] text-[var(--text-tertiary)]">{{ today }}</p>
    </header>

    <!-- Initial loading: only when all queries pending on first load -->
    <template v-if="isInitialLoading">
      <div class="ds-stagger-children grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="i in 4"
          :key="i"
          class="rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-5"
          style="box-shadow: var(--shadow-neo-raised)"
        >
          <Skeleton classes="size-10 rounded-[var(--radius-lg)]" />
          <div class="mt-4 space-y-2">
            <Skeleton classes="h-7 w-16" />
            <Skeleton classes="h-4 w-24" />
          </div>
        </div>
      </div>
      <Skeleton classes="h-48 w-full rounded-[var(--radius-xl)]" />
      <Skeleton classes="h-32 w-full rounded-[var(--radius-xl)]" />
    </template>

    <template v-else>
      <!-- Per-query error banners (one failed request must not blank the dashboard) -->
      <div
        v-if="
          profileQuery.isError.value ||
          cvQuery.isError.value ||
          ingestionsQuery.isError.value ||
          skillsQuery.isError.value
        "
        class="space-y-3"
      >
        <Card
          v-if="profileQuery.isError.value"
          class="border border-[var(--color-danger-200)] bg-[var(--color-danger-50)]"
        >
          <div class="flex items-center justify-between gap-4">
            <p class="text-[var(--text-sm)] text-[var(--color-danger-700)]">
              Failed to load profile completion.
            </p>
            <Button
              variant="outline"
              size="sm"
              :loading="profileQuery.isFetching.value"
              @click="profileQuery.refetch()"
            >
              <RefreshCw class="size-4" aria-hidden="true" />
              Retry
            </Button>
          </div>
        </Card>
        <Card
          v-if="cvQuery.isError.value"
          class="border border-[var(--color-danger-200)] bg-[var(--color-danger-50)]"
        >
          <div class="flex items-center justify-between gap-4">
            <p class="text-[var(--text-sm)] text-[var(--color-danger-700)]">
              Failed to load CV documents.
            </p>
            <Button
              variant="outline"
              size="sm"
              :loading="cvQuery.isFetching.value"
              @click="cvQuery.refetch()"
            >
              <RefreshCw class="size-4" aria-hidden="true" />
              Retry
            </Button>
          </div>
        </Card>
        <Card
          v-if="ingestionsQuery.isError.value"
          class="border border-[var(--color-danger-200)] bg-[var(--color-danger-50)]"
        >
          <div class="flex items-center justify-between gap-4">
            <p class="text-[var(--text-sm)] text-[var(--color-danger-700)]">
              Failed to load opportunities.
            </p>
            <Button
              variant="outline"
              size="sm"
              :loading="ingestionsQuery.isFetching.value"
              @click="ingestionsQuery.refetch()"
            >
              <RefreshCw class="size-4" aria-hidden="true" />
              Retry
            </Button>
          </div>
        </Card>
        <Card
          v-if="skillsQuery.isError.value"
          class="border border-[var(--color-danger-200)] bg-[var(--color-danger-50)]"
        >
          <div class="flex items-center justify-between gap-4">
            <p class="text-[var(--text-sm)] text-[var(--color-danger-700)]">
              Failed to load skills.
            </p>
            <Button
              variant="outline"
              size="sm"
              :loading="skillsQuery.isFetching.value"
              @click="skillsQuery.refetch()"
            >
              <RefreshCw class="size-4" aria-hidden="true" />
              Retry
            </Button>
          </div>
        </Card>
      </div>

      <!-- Stat cards (per-query pending: one slow query does not blank others) -->
      <div class="ds-stagger-children grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div v-if="profileQuery.isPending.value" class="rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-5" style="box-shadow: var(--shadow-neo-raised)" role="status"><Skeleton classes="h-16 w-full" /></div>
        <StatCard
          v-else
          :value="`${profileQuery.data.value?.profile_completion ?? 0}%`"
          label="Profile complete"
          :icon="User"
          href="/profile"
        />
        <div v-if="cvQuery.isPending.value" class="rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-5" style="box-shadow: var(--shadow-neo-raised)" role="status"><Skeleton classes="h-16 w-full" /></div>
        <StatCard v-else :value="cvCount" label="CV documents" :icon="FileText" href="/cv" />
        <div v-if="ingestionsQuery.isPending.value" class="rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-5" style="box-shadow: var(--shadow-neo-raised)" role="status"><Skeleton classes="h-16 w-full" /></div>
        <StatCard
          v-else
          :value="savedJobsCount"
          label="Saved opportunities"
          :icon="Briefcase"
          href="/opportunities"
        />
        <div v-if="skillsQuery.isPending.value" class="rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-5" style="box-shadow: var(--shadow-neo-raised)" role="status"><Skeleton classes="h-16 w-full" /></div>
        <StatCard v-else :value="skillsCount" label="Skills added" :icon="Sparkles" href="/profile" />
      </div>

      <!-- Needs Attention -->
      <template v-if="ingestionsQuery.isError.value">
        <Card class="border border-[var(--color-danger-200)]">
          <div class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
              <AlertTriangle class="size-4 text-[var(--color-danger-500)]" aria-hidden="true" />
              <h2 class="text-[var(--text-base)] font-semibold text-[var(--text-primary)]">
                Opportunities
              </h2>
            </div>
            <p class="text-[var(--text-sm)] text-[var(--text-secondary)]">
              We couldn't load your opportunities. Check your connection and try again.
            </p>
            <div>
              <Button
                variant="outline"
                size="sm"
                :loading="ingestionsQuery.isFetching.value"
                @click="ingestionsQuery.refetch()"
              >
                <RefreshCw class="size-4" aria-hidden="true" />
                Retry
              </Button>
            </div>
          </div>
        </Card>
      </template>
      <template v-else-if="ingestionsQuery.isPending.value">
        <Card><Skeleton classes="h-24 w-full" /></Card>
      </template>
      <template v-else-if="attentionItems.length > 0">
        <Card>
          <template #header>
            <div class="flex items-center gap-2">
              <AlertTriangle class="size-4 text-[var(--color-warning-500)]" aria-hidden="true" />
              <h2 class="text-[var(--text-lg)] font-semibold text-[var(--text-primary)]">
                Needs Attention
              </h2>
            </div>
          </template>
          <AttentionList :items="attentionItems" />
        </Card>
      </template>
      <template v-else>
        <Card>
          <EmptyState
            :icon="Briefcase"
            title="All caught up"
            description="No opportunities need your review right now."
          />
        </Card>
      </template>

      <!-- Quick Actions -->
      <div>
        <h2 class="mb-4 text-[var(--text-lg)] font-semibold text-[var(--text-primary)]">
          Quick Actions
        </h2>
        <QuickActions />
      </div>
    </template>
  </div>
</template>
