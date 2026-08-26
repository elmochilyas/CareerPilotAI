<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import {
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
import BackButton from '@/components/ui/BackButton.vue'
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
import { useClarificationSession } from '@/features/clarification/composables/useClarificationSession'
import CompanyResearchSection from '../components/CompanyResearchSection.vue'

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

const clarificationAnalysisId = computed(() => completedAnalysis.value?.id ?? null)
const { questions: clarificationQuestions, totalQuestions: clarificationTotalQuestions } =
  useClarificationSession(clarificationAnalysisId)
const gapReviewCompleted = computed(
  () => clarificationTotalQuestions.value > 0 && clarificationQuestions.value.length === 0,
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

const hasAtAGlance = computed(() => {
  const item = opportunity.value
  if (!item) return false
  return Boolean(
    item.salary_min ||
    item.salary_max ||
    item.compensation_text ||
    (item.benefits && item.benefits.length > 0) ||
    item.seniority_level ||
    item.working_hours ||
    item.employment_duration ||
    item.publication_date ||
    item.application_deadline ||
    item.expected_start_date ||
    item.travel_required !== null ||
    item.relocation_required !== null,
  )
})

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
    <BackButton :to="{ name: 'opportunities' }" label="Back to opportunities" />

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
      class="mt-10 space-y-8"
    >
      <span class="sr-only">Loading opportunity details</span>
      <div
        class="rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-6 sm:p-8"
        aria-hidden="true"
      >
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
        <div class="mt-5 flex flex-wrap gap-6 border-t border-slate-100 pt-5">
          <Skeleton classes="h-10 w-36 rounded-lg" />
          <Skeleton classes="h-10 w-28 rounded-lg" />
          <Skeleton classes="h-10 w-32 rounded-lg" />
          <Skeleton classes="h-10 w-24 rounded-lg" />
        </div>
        <div class="mt-6 flex gap-2 border-t border-slate-100 pt-4">
          <Skeleton classes="h-6 w-32 rounded-full" />
          <Skeleton classes="h-6 w-36 rounded-full" />
        </div>
      </div>
      <div
        class="rounded-[var(--radius-xl)] border border-slate-200 bg-white p-6 sm:p-8"
        aria-hidden="true"
      >
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
      <div
        class="rounded-[var(--radius-xl)] border border-slate-200 bg-white p-6 sm:p-8"
        aria-hidden="true"
      >
        <Skeleton classes="h-6 w-1/4" />
        <div class="mt-4 space-y-3">
          <Skeleton classes="h-4 w-full" />
          <Skeleton classes="h-4 w-5/6" />
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
      <!-- Unified identity + at-a-glance header — single home for identity & logistics -->
      <header
        class="mt-10 rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-6 sm:p-8"
      >
        <p class="text-xs font-bold uppercase tracking-[0.07em] text-[var(--color-primary-600)]">
          Saved opportunity
        </p>
        <div class="mt-2 flex flex-wrap items-center gap-3">
          <h1
            class="text-[clamp(1.75rem,3.5vw,2.25rem)] font-bold leading-[1.15] tracking-[-0.03em] text-slate-900"
          >
            {{ opportunity.title }}
          </h1>
          <Badge v-if="opportunity.personal_label" variant="primary">
            {{ opportunity.personal_label }}
          </Badge>
        </div>
        <p v-if="opportunity.company_name" class="mt-1 text-lg font-semibold text-slate-800">
          {{ opportunity.company_name }}
        </p>
        <div
          v-if="location || opportunity.work_mode || opportunity.contract_type"
          class="mt-3 flex flex-wrap gap-2"
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

        <!-- At a glance — single consolidated logistics band (natural-width stats, no dead columns) -->
        <dl
          v-if="hasAtAGlance"
          class="mt-5 flex flex-wrap items-start gap-x-10 gap-y-4 border-t border-slate-100 pt-5"
        >
          <div
            v-if="opportunity.salary_min || opportunity.salary_max"
            class="min-w-[10rem] max-w-md"
          >
            <dt
              class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]"
            >
              <Banknote class="size-3.5" aria-hidden="true" />
              Salary
            </dt>
            <dd class="mt-1 text-sm font-semibold text-slate-900">
              {{ formatSalaryValue(opportunity.salary_min, opportunity.salary_currency) }}–{{
                formatSalaryValue(opportunity.salary_max, opportunity.salary_currency)
              }}{{ opportunity.salary_period ? `/${formatLabel(opportunity.salary_period)}` : '' }}
            </dd>
            <dd
              v-if="opportunity.compensation_text"
              class="mt-1 text-xs leading-relaxed text-slate-600"
            >
              {{ opportunity.compensation_text }}
            </dd>
          </div>

          <div v-if="opportunity.seniority_level" class="min-w-[7rem]">
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
              Seniority
            </dt>
            <dd class="mt-1 text-sm font-medium text-slate-800">
              {{ formatLabel(opportunity.seniority_level) }}
            </dd>
          </div>

          <div v-if="opportunity.working_hours" class="min-w-[7rem]">
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
              Working hours
            </dt>
            <dd class="mt-1 text-sm text-slate-700">{{ opportunity.working_hours }}</dd>
          </div>

          <div v-if="opportunity.employment_duration" class="min-w-[8rem]">
            <dt
              class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]"
            >
              <CalendarDays class="size-3.5" aria-hidden="true" />
              Employment duration
            </dt>
            <dd class="mt-1 text-sm text-slate-700">{{ opportunity.employment_duration }}</dd>
          </div>

          <div v-if="opportunity.publication_date" class="min-w-[8rem]">
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
              Published
            </dt>
            <dd class="mt-1 text-sm text-slate-700">
              {{ formatDate(opportunity.publication_date) }}
            </dd>
          </div>

          <div v-if="opportunity.application_deadline" class="min-w-[9rem]">
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
              Application deadline
            </dt>
            <dd class="mt-1 text-sm font-medium text-slate-800">
              {{ formatDate(opportunity.application_deadline) }}
            </dd>
          </div>

          <div v-if="opportunity.expected_start_date" class="min-w-[8rem]">
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
              Expected start
            </dt>
            <dd class="mt-1 text-sm text-slate-700">
              {{ formatDate(opportunity.expected_start_date) }}
            </dd>
          </div>

          <div v-if="opportunity.travel_required !== null" class="min-w-[6.5rem]">
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
              Travel required
            </dt>
            <dd class="mt-1 text-sm text-slate-700">
              {{ opportunity.travel_required ? 'Yes' : 'No' }}
            </dd>
          </div>

          <div v-if="opportunity.relocation_required !== null" class="min-w-[9rem]">
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
              Relocation required
            </dt>
            <dd class="mt-1 text-sm text-slate-700">
              {{ opportunity.relocation_required ? 'Yes' : 'No' }}
            </dd>
          </div>

          <div v-if="opportunity.benefits?.length" class="w-full">
            <dt class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-muted)]">
              Benefits
            </dt>
            <dd class="mt-1.5 flex flex-wrap gap-1.5">
              <span
                v-for="benefit in opportunity.benefits"
                :key="benefit"
                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600"
              >
                {{ benefit }}
              </span>
            </dd>
          </div>
        </dl>

        <!-- Provenance strip — single home for source & saved date -->
        <div
          v-if="opportunity.source_url || opportunity.saved_at"
          class="mt-5 flex flex-wrap items-center gap-2.5 border-t border-slate-100 pt-4 text-xs"
        >
          <span
            v-if="opportunity.saved_at"
            class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1 font-medium text-slate-500 ring-1 ring-slate-200"
          >
            <CalendarDays class="size-3.5 text-slate-400" aria-hidden="true" />
            Saved {{ formatDate(opportunity.saved_at) }}
          </span>
          <a
            v-if="opportunity.source_url"
            :href="opportunity.source_url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 font-medium text-slate-600 transition-colors hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary-600)]"
          >
            <Globe class="size-3.5 text-slate-400" aria-hidden="true" />
            Original posting
            <ArrowRight class="size-3 text-slate-400" aria-hidden="true" />
          </a>
        </div>
      </header>

      <div class="mt-8 space-y-8">
        <!-- 1. The role — source material directly after title -->
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
              About the role
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
                      Quote from posting
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

          <!-- Application CTA — kept with the role; provenance now lives in the summary header -->
          <footer
            v-if="opportunity.application_url"
            class="mt-10 border-t border-[var(--border-subtle)] pt-6"
          >
            <a
              :href="opportunity.application_url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-primary-600)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[var(--color-primary-700)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary-600)]"
            >
              Apply on company site
              <ArrowRight class="size-4" aria-hidden="true" />
            </a>
          </footer>
        </div>

        <!-- 2. Match Brief — interpretation after source -->
        <section aria-labelledby="match-heading">
          <div
            class="rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-6 sm:p-8"
          >
            <div class="flex items-center justify-between gap-3">
              <h2
                id="match-heading"
                class="flex items-center gap-2 text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
              >
                <Sparkles class="size-5 text-[var(--color-primary-500)]" aria-hidden="true" />
                Match Brief
              </h2>
              <RouterLink
                v-if="completedAnalysis"
                :to="{
                  name: 'opportunities-match',
                  params: { id: opportunity.id },
                  query: { view: 'analysis' },
                }"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-primary-600)] transition-colors hover:text-[var(--color-primary-700)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary-600)]"
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
                :gap-review-completed="gapReviewCompleted"
                class="mt-4"
                @view-gaps="viewGaps"
              />

              <ClarificationEntryCard
                class="mt-4"
                :analysis-id="completedAnalysis.id"
                show-completed
                @open="openClarifications"
              />

              <Button
                class="mt-4"
                @click="
                  router.push({ name: 'opportunities-tailor', params: { id: opportunityId } })
                "
              >
                <Sparkles class="mr-2 size-4" aria-hidden="true" />
                Tailor CV
              </Button>
            </template>
          </div>
        </section>

        <!-- 3. Company Research — deep dive last -->
        <CompanyResearchSection :opportunity-id="opportunityId" />
      </div>
    </template>
  </div>
</template>
