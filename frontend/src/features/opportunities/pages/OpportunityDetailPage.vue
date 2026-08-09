<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import {
  ArrowLeft,
  ArrowRight,
  Banknote,
  BriefcaseBusiness,
  CalendarDays,
  CircleAlert,
  FileCheck2,
  Globe,
  MapPin,
  RefreshCw,
  Sparkles,
} from '@lucide/vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { formatDate } from '@/app/utils/date'
import { fetchOpportunity, opportunityKeys } from '../api'
import { useMatchAnalysis } from '@/features/matching/composables/useMatchAnalysis'
import MatchSummaryHero from '@/features/matching/components/MatchSummaryHero.vue'
import MatchAtAGlance from '@/features/matching/components/MatchAtAGlance.vue'
import MatchStatusPanel from '@/features/matching/components/MatchStatusPanel.vue'
import MatchErrorState from '@/features/matching/components/MatchErrorState.vue'
import StaleNotice from '@/features/matching/components/StaleNotice.vue'
import InsufficientProfileGate from '@/features/matching/components/InsufficientProfileGate.vue'
import ClarificationEntryCard from '@/features/clarification/components/ClarificationEntryCard.vue'

const route = useRoute()
const router = useRouter()

const opportunityId = computed(() => {
  const id = Number(route.params.id)
  return Number.isInteger(id) && id > 0 ? id : null
})

const opportunityQuery = useQuery({
  queryKey: computed(() => opportunityKeys.detail(opportunityId.value ?? 0)),
  queryFn: () => fetchOpportunity(opportunityId.value!),
  enabled: computed(() => opportunityId.value !== null),
  refetchOnMount: 'always',
})

const opportunity = opportunityQuery.data

const {
  announcement: matchAnnouncement,
  completedAnalysis,
  listQuery,
  createProblemCode,
  isStarting,
  isRecalculating,
  recalculateMutation,
  startAnalysis,
} = useMatchAnalysis(opportunityId)

const processing = computed(
  () =>
    completedAnalysis.value === null &&
    (listQuery.data.value?.data?.[0]?.status === 'queued' ||
      listQuery.data.value?.data?.[0]?.status === 'processing' ||
      isStarting.value),
)

const failedAnalysis = computed(() => {
  const list = listQuery.data.value?.data ?? []
  return list[0]?.status === 'failed' ? list[0] : null
})

const noAnalyses = computed(() => (listQuery.data.value?.data?.length ?? 0) === 0)

const insufficientProfile = computed(
  () => noAnalyses.value && createProblemCode.value === 'insufficient_profile',
)

function viewGaps(): void {
  const id = opportunityId.value
  if (id === null) return
  void router.push({
    name: 'opportunities-match',
    params: { id },
    query: { view: 'analysis', filter: 'gaps' },
  })
}

function openClarifications(): void {
  const id = opportunityId.value
  if (id === null) return
  void router.push({ name: 'opportunities-match-clarifications', params: { id } })
}

function recalculate(): void {
  const target = completedAnalysis.value
  if (target !== null && !isRecalculating.value) {
    recalculateMutation.mutate(target.id)
  }
}

const responsibilities = computed(
  () => opportunity.value?.requirements.filter((item) => item.category === 'responsibility') ?? [],
)

const experienceAndEducation = computed(
  () =>
    opportunity.value?.requirements.filter((item) =>
      ['required_experience', 'preferred_experience', 'education'].includes(item.category),
    ) ?? [],
)

const languagesAndCertifications = computed(
  () =>
    opportunity.value?.requirements.filter((item) =>
      ['language', 'certification'].includes(item.category),
    ) ?? [],
)

const requiredSkills = computed(
  () => opportunity.value?.skills.filter((skill) => skill.classification === 'required') ?? [],
)

const preferredSkills = computed(
  () => opportunity.value?.skills.filter((skill) => skill.classification === 'preferred') ?? [],
)

const hasWorkDetails = computed(() => {
  const item = opportunity.value
  return Boolean(
    item &&
    [
      item.city,
      item.region,
      item.country,
      item.work_mode,
      item.contract_type,
      item.seniority_level,
      item.working_hours,
      item.travel_required,
      item.relocation_required,
    ].some((value) => value !== null && value !== ''),
  )
})

const hasCompensation = computed(() => {
  const item = opportunity.value
  return Boolean(
    item &&
    (item.salary_min ||
      item.salary_max ||
      item.compensation_text ||
      (item.benefits && item.benefits.length > 0)),
  )
})

const hasDates = computed(() => {
  const item = opportunity.value
  return Boolean(
    item &&
    (item.publication_date ||
      item.application_deadline ||
      item.expected_start_date ||
      item.employment_duration),
  )
})

const location = computed(() =>
  [opportunity.value?.city, opportunity.value?.region, opportunity.value?.country]
    .filter(Boolean)
    .join(', '),
)

const hasOverview = computed(() =>
  Boolean(
    opportunity.value &&
    (opportunity.value.department ||
      opportunity.value.external_reference ||
      opportunity.value.summary ||
      opportunity.value.application_url),
  ),
)

const hasRailContent = computed(
  () =>
    hasWorkDetails.value ||
    hasCompensation.value ||
    hasDates.value ||
    Boolean(opportunity.value?.saved_at) ||
    Boolean(opportunity.value?.source_url),
)

function formatLabel(value: string): string {
  return value.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function formatSalaryValue(value: string | null, currency: string | null): string {
  if (value === null) return '—'

  const amount = Number(value)

  if (!Number.isFinite(amount)) return value

  if (currency && /^[A-Z]{3}$/.test(currency)) {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      maximumFractionDigits: 2,
    }).format(amount)
  }

  return new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(amount)
}
</script>

<template>
  <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <RouterLink
      :to="{ name: 'opportunities' }"
      class="inline-flex items-center gap-1 rounded-[var(--radius-md)] px-3 py-1.5 text-sm font-medium text-[var(--color-primary-700)] shadow-[var(--shadow-neo-raised-sm)] transition-all hover:text-[var(--color-primary-800)] hover:shadow-[var(--shadow-neo-button)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
    >
      <ArrowLeft class="size-4" aria-hidden="true" />
      Back to opportunities
    </RouterLink>

    <div
      v-if="opportunityId === null"
      role="alert"
      class="mx-auto mt-16 max-w-md rounded-[var(--radius-xl)] border border-[var(--color-error-100)] bg-[var(--color-error-50)] p-6 text-center"
    >
      <CircleAlert class="mx-auto size-8 text-[var(--color-error-500)]" aria-hidden="true" />
      <h2 class="mt-3 text-sm font-semibold text-[var(--color-error-700)]">
        Invalid opportunity identifier
      </h2>
      <p class="mt-1 text-sm text-[var(--color-error-600)]">This opportunity link isn't valid.</p>
    </div>

    <div
      v-else-if="opportunityQuery.isPending.value"
      role="status"
      aria-live="polite"
      class="mt-10 space-y-10"
    >
      <span class="sr-only">Loading opportunity details</span>
      <div aria-hidden="true">
        <Skeleton classes="h-3 w-24" />
        <div class="mt-3 space-y-2">
          <Skeleton classes="h-9 w-2/3" />
          <Skeleton classes="h-5 w-1/3" />
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <Skeleton classes="h-7 w-32 rounded-lg" />
          <Skeleton classes="h-7 w-24 rounded-lg" />
          <Skeleton classes="h-7 w-28 rounded-lg" />
        </div>
      </div>
      <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div aria-hidden="true">
          <div class="rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
            <Skeleton classes="h-6 w-1/4" />
            <div class="mt-4 space-y-3">
              <Skeleton classes="h-4 w-full" />
              <Skeleton classes="h-4 w-5/6" />
            </div>
            <div class="mt-8">
              <Skeleton classes="h-6 w-1/3" />
              <div class="mt-4 space-y-3">
                <Skeleton classes="h-4 w-full" />
                <Skeleton classes="h-4 w-4/6" />
              </div>
            </div>
          </div>
        </div>
        <div class="hidden lg:block" aria-hidden="true">
          <div
            class="rounded-[var(--radius-lg)] border border-[var(--border-default)] bg-[var(--surface-secondary)] p-5"
          >
            <Skeleton classes="h-11 w-full rounded-lg" />
            <div class="mt-5 space-y-4">
              <Skeleton classes="h-4 w-3/4" />
              <Skeleton classes="h-4 w-full" />
              <Skeleton classes="h-4 w-2/3" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      v-else-if="opportunityQuery.isError.value || !opportunity"
      role="alert"
      class="mx-auto mt-16 max-w-md rounded-[var(--radius-xl)] border border-[var(--color-error-100)] bg-[var(--color-error-50)] p-6 text-center"
    >
      <CircleAlert class="mx-auto size-8 text-[var(--color-error-500)]" aria-hidden="true" />
      <h2 class="mt-3 text-sm font-semibold text-[var(--color-error-700)]">
        We couldn't load this opportunity
      </h2>
      <p class="mt-1 text-sm text-[var(--color-error-600)]">Check your connection and try again.</p>
      <button
        type="button"
        class="mt-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-[var(--radius-md)] border border-[var(--color-error-300)] bg-white px-4 text-sm font-medium text-[var(--color-error-700)] transition-colors hover:bg-[var(--color-error-50)] focus:outline-none focus:ring-2 focus:ring-[var(--color-error-500)]/40 focus:ring-offset-1"
        @click="opportunityQuery.refetch()"
      >
        <RefreshCw class="size-4" aria-hidden="true" />
        Try again
      </button>
    </div>

    <template v-else>
      <header class="mb-12 mt-10">
        <p class="text-xs font-bold uppercase tracking-[0.07em] text-primary-600">
          Saved opportunity
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-3">
          <h1
            class="text-[clamp(1.875rem,4vw,2.5rem)] font-bold leading-[1.1] tracking-[-0.03em] text-slate-900"
          >
            {{ opportunity.title }}
          </h1>
          <Badge v-if="opportunity.personal_label" variant="primary">
            {{ opportunity.personal_label }}
          </Badge>
        </div>
        <p v-if="opportunity.company_name" class="mt-2 text-lg font-semibold text-slate-800">
          {{ opportunity.company_name }}
        </p>
        <div
          v-if="location || opportunity.work_mode || opportunity.contract_type"
          class="mt-4 flex flex-wrap gap-2"
        >
          <span
            v-if="location"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600"
          >
            <MapPin class="size-3.5 text-slate-400" aria-hidden="true" />
            {{ location }}
          </span>
          <span
            v-if="opportunity.work_mode"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600"
          >
            <BriefcaseBusiness class="size-3.5 text-slate-400" aria-hidden="true" />
            {{ formatLabel(opportunity.work_mode) }}
          </span>
          <span
            v-if="opportunity.contract_type"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600"
          >
            <FileCheck2 class="size-3.5 text-slate-400" aria-hidden="true" />
            {{ formatLabel(opportunity.contract_type) }}
          </span>
        </div>
      </header>

      <div class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-8">
          <!-- Match Brief (concise summary — shown first) -->
          <section aria-labelledby="match-heading">
            <div
              class="rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-6 sm:p-8"
            >
              <div class="flex items-center justify-between gap-3">
                <h2
                  id="match-heading"
                  class="flex items-center gap-2 text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
                >
                  <Sparkles class="size-5 text-primary-500" aria-hidden="true" />
                  Match Brief
                </h2>
                <RouterLink
                  v-if="completedAnalysis"
                  :to="{
                    name: 'opportunities-match',
                    params: { id: opportunity.id },
                    query: { view: 'analysis' },
                  }"
                  class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 transition-colors hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                >
                  View full analysis
                  <ArrowRight class="size-3.5" aria-hidden="true" />
                </RouterLink>
              </div>

              <div class="sr-only" aria-live="polite">{{ matchAnnouncement }}</div>

              <!-- Loading -->
              <div v-if="listQuery.isLoading.value" class="mt-6 space-y-4">
                <div class="h-28 animate-pulse rounded-xl border border-slate-200 bg-slate-50" />
                <div class="h-20 animate-pulse rounded-xl border border-slate-200 bg-slate-50" />
              </div>

              <!-- Processing -->
              <MatchStatusPanel
                v-else-if="processing && !completedAnalysis"
                class="mt-6"
                :status="
                  listQuery.data.value?.data?.[0]?.status === 'processing' ? 'processing' : 'queued'
                "
              />

              <!-- Failed -->
              <MatchErrorState
                v-else-if="failedAnalysis && !completedAnalysis"
                class="mt-6"
                title="Match analysis failed"
                :detail="failedAnalysis.failure?.reason"
                :busy="isStarting"
                @retry="startAnalysis"
              />

              <!-- Insufficient profile -->
              <InsufficientProfileGate v-else-if="insufficientProfile" class="mt-6" />

              <!-- No analyses -->
              <div v-else-if="noAnalyses" class="mt-6 text-center">
                <p class="text-sm text-slate-500">
                  Start a match analysis to see how this opportunity aligns with your profile.
                </p>
                <Button class="mt-4" :loading="isStarting" @click="startAnalysis">
                  {{ isStarting ? 'Starting...' : 'Start analysis' }}
                </Button>
              </div>

              <!-- Completed analysis -->
              <template v-else-if="completedAnalysis">
                <div
                  v-if="processing"
                  role="status"
                  aria-live="polite"
                  class="mt-6 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600"
                >
                  A new analysis is running. The previous result stays visible below.
                </div>

                <StaleNotice
                  v-if="completedAnalysis.stale"
                  class="mt-6"
                  :busy="isRecalculating"
                  @recalculate="recalculate"
                />

                <MatchSummaryHero :analysis="completedAnalysis" class="mt-6" />

                <MatchAtAGlance
                  :findings="completedAnalysis.findings"
                  class="mt-4"
                  @view-gaps="viewGaps"
                />

                <ClarificationEntryCard
                  class="mt-4"
                  :analysis-id="completedAnalysis.id"
                  @open="openClarifications"
                />
              </template>
            </div>
          </section>

          <!-- Job details -->
          <div
            class="rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-6 sm:p-8"
          >
            <section
              v-if="hasOverview"
              aria-labelledby="overview-heading"
              class="border-t border-[var(--border-subtle)] pt-10 first:border-t-0 first:pt-0"
            >
              <h2
                id="overview-heading"
                class="text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
              >
                Overview
              </h2>
              <p
                v-if="opportunity.summary"
                class="mt-4 max-w-prose whitespace-pre-wrap text-base leading-7 text-slate-600"
              >
                {{ opportunity.summary }}
              </p>
              <dl class="mt-5 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                <div v-if="opportunity.department">
                  <dt class="text-xs font-medium text-slate-500">Department</dt>
                  <dd class="mt-1 text-sm text-slate-800">{{ opportunity.department }}</dd>
                </div>
                <div v-if="opportunity.external_reference">
                  <dt class="text-xs font-medium text-slate-500">Reference</dt>
                  <dd class="mt-1 text-sm text-slate-800">{{ opportunity.external_reference }}</dd>
                </div>
                <div v-if="opportunity.application_url" class="sm:col-span-2">
                  <dt class="text-xs font-medium text-slate-500">Application</dt>
                  <dd class="mt-1 text-sm">
                    <a
                      :href="opportunity.application_url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="font-medium text-primary-700 underline decoration-primary-300 underline-offset-2 hover:text-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                    >
                      Open application page
                    </a>
                  </dd>
                </div>
              </dl>
            </section>

            <section
              v-if="responsibilities.length"
              aria-labelledby="responsibilities-heading"
              class="border-t border-[var(--border-subtle)] pt-10 first:border-t-0 first:pt-0"
            >
              <h2
                id="responsibilities-heading"
                class="text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
              >
                Responsibilities
              </h2>
              <ol class="mt-5 space-y-5">
                <li v-for="(item, index) in responsibilities" :key="item.id" class="flex gap-3">
                  <span
                    class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600"
                  >
                    {{ index + 1 }}
                  </span>
                  <div class="min-w-0">
                    <p class="text-sm leading-relaxed text-slate-700">{{ item.content }}</p>
                    <details v-if="item.source_evidence" class="mt-1.5">
                      <summary
                        class="cursor-pointer select-none text-xs font-medium text-slate-500 transition-colors hover:text-slate-700"
                      >
                        Source evidence
                      </summary>
                      <p class="mt-2 whitespace-pre-wrap text-xs leading-relaxed text-slate-500">
                        {{ item.source_evidence }}
                      </p>
                    </details>
                  </div>
                </li>
              </ol>
            </section>

            <section
              v-if="experienceAndEducation.length"
              aria-labelledby="experience-heading"
              class="border-t border-[var(--border-subtle)] pt-10 first:border-t-0 first:pt-0"
            >
              <h2
                id="experience-heading"
                class="text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
              >
                Experience and education
              </h2>
              <ul class="mt-4 divide-y divide-slate-100">
                <li
                  v-for="item in experienceAndEducation"
                  :key="item.id"
                  class="flex items-baseline justify-between gap-4 py-3 first:pt-0 last:pb-0"
                >
                  <span class="text-sm text-slate-800">{{ item.content }}</span>
                  <span class="shrink-0 text-xs font-medium text-slate-400">
                    {{ formatLabel(item.category) }}
                  </span>
                </li>
              </ul>
            </section>

            <section
              v-if="requiredSkills.length"
              aria-labelledby="required-skills-heading"
              class="border-t border-[var(--border-subtle)] pt-10 first:border-t-0 first:pt-0"
            >
              <h2
                id="required-skills-heading"
                class="text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
              >
                Required skills
              </h2>
              <ul class="mt-4 flex flex-wrap gap-2">
                <li v-for="skill in requiredSkills" :key="skill.id">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-primary-50)] text-[var(--color-primary-700)] px-3 py-1 text-[var(--text-xs)] font-medium"
                  >
                    {{ skill.original_label }}
                    <Badge>{{ skill.skill_id ? 'Catalog matched' : 'Original label' }}</Badge>
                  </span>
                </li>
              </ul>
            </section>

            <section
              v-if="preferredSkills.length"
              aria-labelledby="preferred-skills-heading"
              class="border-t border-[var(--border-subtle)] pt-10 first:border-t-0 first:pt-0"
            >
              <h2
                id="preferred-skills-heading"
                class="text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
              >
                Preferred skills
              </h2>
              <ul class="mt-4 flex flex-wrap gap-2">
                <li v-for="skill in preferredSkills" :key="skill.id">
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white py-1 pl-3 pr-1 text-sm font-medium text-slate-600"
                  >
                    {{ skill.original_label }}
                    <Badge>{{ skill.skill_id ? 'Catalog matched' : 'Original label' }}</Badge>
                  </span>
                </li>
              </ul>
            </section>

            <section
              v-if="languagesAndCertifications.length"
              aria-labelledby="languages-heading"
              class="border-t border-[var(--border-subtle)] pt-10 first:border-t-0 first:pt-0"
            >
              <h2
                id="languages-heading"
                class="text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
              >
                Languages and certifications
              </h2>
              <ul class="mt-4 divide-y divide-slate-100">
                <li
                  v-for="item in languagesAndCertifications"
                  :key="item.id"
                  class="flex items-baseline justify-between gap-4 py-3 first:pt-0 last:pb-0"
                >
                  <span class="text-sm text-slate-800">{{ item.content }}</span>
                  <span class="shrink-0 text-xs font-medium text-slate-400">
                    {{ formatLabel(item.category) }}
                    <template v-if="item.language_proficiency">
                      · {{ formatLabel(item.language_proficiency) }}
                    </template>
                  </span>
                </li>
              </ul>
            </section>

            <section
              v-if="opportunity.additional_requirements?.length"
              aria-labelledby="additional-heading"
              class="border-t border-[var(--border-subtle)] pt-10 first:border-t-0 first:pt-0"
            >
              <h2
                id="additional-heading"
                class="text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
              >
                Additional requirements
              </h2>
              <ul class="mt-4 list-disc space-y-2 pl-5">
                <li v-for="item in opportunity.additional_requirements" :key="item.id">
                  <span class="text-sm text-slate-800">{{ item.text }}</span>
                </li>
              </ul>
            </section>
          </div>
        </div>

        <aside class="min-w-0" aria-label="Quick facts">
          <div
            class="rounded-[var(--radius-lg)] border border-[var(--border-default)] bg-[var(--surface-secondary)] p-5 lg:sticky lg:top-6"
          >
            <p
              class="text-[var(--text-xs)] font-medium uppercase tracking-wider text-[var(--text-muted)]"
            >
              Quick facts
            </p>

            <div v-if="hasRailContent" class="mt-5 divide-y divide-slate-100">
              <section
                v-if="hasWorkDetails"
                aria-labelledby="work-heading"
                class="py-4 first:pt-0 last:pb-0"
              >
                <h2
                  id="work-heading"
                  class="flex items-center gap-2 text-sm font-semibold text-slate-900"
                >
                  <MapPin class="size-4 text-primary-600" aria-hidden="true" />
                  Work details
                </h2>
                <dl class="mt-3 space-y-3">
                  <div v-if="location">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Location
                    </dt>
                    <dd class="mt-0.5 text-[var(--text-sm)] text-[var(--text-primary)]">
                      {{ location }}
                    </dd>
                  </div>
                  <div v-if="opportunity.work_mode">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Work mode
                    </dt>
                    <dd class="mt-0.5 text-[var(--text-sm)] text-[var(--text-primary)]">
                      {{ formatLabel(opportunity.work_mode) }}
                    </dd>
                  </div>
                  <div v-if="opportunity.contract_type">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Contract type
                    </dt>
                    <dd class="mt-0.5 text-[var(--text-sm)] text-[var(--text-primary)]">
                      {{ formatLabel(opportunity.contract_type) }}
                    </dd>
                  </div>
                  <div v-if="opportunity.seniority_level">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Seniority
                    </dt>
                    <dd class="mt-0.5 text-[var(--text-sm)] text-[var(--text-primary)]">
                      {{ formatLabel(opportunity.seniority_level) }}
                    </dd>
                  </div>
                  <div v-if="opportunity.working_hours">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Working hours
                    </dt>
                    <dd class="mt-0.5 text-[var(--text-sm)] text-[var(--text-primary)]">
                      {{ opportunity.working_hours }}
                    </dd>
                  </div>
                  <div v-if="opportunity.travel_required !== null">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Travel required
                    </dt>
                    <dd class="mt-0.5 text-[var(--text-sm)] text-[var(--text-primary)]">
                      {{ opportunity.travel_required ? 'Yes' : 'No' }}
                    </dd>
                  </div>
                  <div v-if="opportunity.relocation_required !== null">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Relocation required
                    </dt>
                    <dd class="mt-0.5 text-[var(--text-sm)] text-[var(--text-primary)]">
                      {{ opportunity.relocation_required ? 'Yes' : 'No' }}
                    </dd>
                  </div>
                </dl>
              </section>

              <section
                v-if="hasCompensation"
                aria-labelledby="compensation-heading"
                class="py-4 first:pt-0 last:pb-0"
              >
                <h2
                  id="compensation-heading"
                  class="flex items-center gap-2 text-sm font-semibold text-slate-900"
                >
                  <Banknote class="size-4 text-primary-600" aria-hidden="true" />
                  Compensation
                </h2>
                <dl class="mt-3 space-y-3">
                  <div v-if="opportunity.salary_min || opportunity.salary_max">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Salary
                    </dt>
                    <dd class="mt-0.5 text-lg font-semibold text-slate-900">
                      {{
                        formatSalaryValue(opportunity.salary_min, opportunity.salary_currency)
                      }}–{{
                        formatSalaryValue(opportunity.salary_max, opportunity.salary_currency)
                      }}
                      {{
                        opportunity.salary_period
                          ? `/${formatLabel(opportunity.salary_period)}`
                          : ''
                      }}
                    </dd>
                  </div>
                  <div v-if="opportunity.compensation_text">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Compensation note
                    </dt>
                    <dd class="mt-0.5 text-[var(--text-sm)] text-[var(--text-primary)]">
                      {{ opportunity.compensation_text }}
                    </dd>
                  </div>
                  <div v-if="opportunity.benefits?.length">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Benefits
                    </dt>
                    <dd class="mt-1 text-sm text-slate-700">
                      <ul class="space-y-1">
                        <li v-for="benefit in opportunity.benefits" :key="benefit">
                          <span class="inline-flex items-start gap-1.5">
                            <span class="text-primary-600" aria-hidden="true">·</span>
                            <span>{{ benefit }}</span>
                          </span>
                        </li>
                      </ul>
                    </dd>
                  </div>
                </dl>
              </section>

              <section
                v-if="hasDates"
                aria-labelledby="dates-heading"
                class="py-4 first:pt-0 last:pb-0"
              >
                <h2
                  id="dates-heading"
                  class="flex items-center gap-2 text-sm font-semibold text-slate-900"
                >
                  <CalendarDays class="size-4 text-primary-600" aria-hidden="true" />
                  Dates
                </h2>
                <dl class="mt-3 space-y-3">
                  <div v-if="opportunity.publication_date">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Published
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-700">
                      {{ formatDate(opportunity.publication_date) }}
                    </dd>
                  </div>
                  <div v-if="opportunity.application_deadline">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Application deadline
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-700">
                      {{ formatDate(opportunity.application_deadline) }}
                    </dd>
                  </div>
                  <div v-if="opportunity.expected_start_date">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Expected start
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-700">
                      {{ formatDate(opportunity.expected_start_date) }}
                    </dd>
                  </div>
                  <div v-if="opportunity.employment_duration">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Employment duration
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-700">
                      {{ opportunity.employment_duration }}
                    </dd>
                  </div>
                </dl>
              </section>

              <section aria-labelledby="source-heading" class="py-4 first:pt-0 last:pb-0">
                <h2
                  id="source-heading"
                  class="flex items-center gap-2 text-sm font-semibold text-slate-900"
                >
                  <Globe class="size-4 text-primary-600" aria-hidden="true" />
                  Source
                </h2>
                <dl class="mt-3 space-y-3">
                  <div v-if="formatDate(opportunity.saved_at)">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Saved
                    </dt>
                    <dd class="mt-0.5 text-sm text-slate-700">
                      {{ formatDate(opportunity.saved_at) }}
                    </dd>
                  </div>
                  <div v-if="opportunity.source_url">
                    <dt class="text-[var(--text-xs)] font-medium text-[var(--text-muted)]">
                      Original posting
                    </dt>
                    <dd class="mt-0.5 text-sm">
                      <a
                        :href="opportunity.source_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-medium text-primary-700 underline decoration-primary-300 underline-offset-2 hover:text-primary-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                      >
                        Open source
                      </a>
                    </dd>
                  </div>
                </dl>
              </section>
            </div>
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>
