<script setup lang="ts">
import { computed } from 'vue'
import {
  Building2,
  CheckCircle2,
  Clock,
  Cpu,
  Lightbulb,
  Package,
  RefreshCw,
  Search,
  Users,
} from '@lucide/vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { useCompanyResearch } from '@/features/opportunities/composables/useCompanyResearch'
import CompanyResearchHeader from './company-research/CompanyResearchHeader.vue'
import ResearchSummaryPanel from './company-research/ResearchSummaryPanel.vue'
import InfoCard from './company-research/InfoCard.vue'
import ClaimBulletList from './company-research/ClaimBulletList.vue'
import TechPillList from './company-research/TechPillList.vue'
import RecentInformationState from './company-research/RecentInformationState.vue'
import SourceProvenanceTable from './company-research/SourceProvenanceTable.vue'

const props = defineProps<{ opportunityId: number | null }>()

const {
  query,
  data,
  isProcessing,
  isFailed,
  isCompleted,
  isNotResearched,
  isBusy,
  start,
  refresh,
  errorDetail,
} = useCompanyResearch(() => props.opportunityId)

const showLimited = computed(
  () => data.value?.status === 'limited' || data.value?.fallback_reason !== null,
)

const hasAbout = computed(() => Boolean(data.value?.overview?.description?.trim()))
const products = computed(() => data.value?.products ?? [])
const technology = computed(() => data.value?.technology_context ?? [])
const roleContext = computed(() => data.value?.role_context ?? [])
const recentInformation = computed(() => data.value?.recent_information ?? [])
const candidatePreparation = computed(() => data.value?.candidate_preparation ?? [])

/**
 * Engineering culture is derived from technology_context inference claims.
 * Backend has no separate field; inference items describe culture/process.
 */
const engineeringCultureClaims = computed(() =>
  technology.value.filter((c) => c.kind === 'inference'),
)
const hasEngineeringCulture = computed(() => engineeringCultureClaims.value.length > 0)

const hasRoleContext = computed(() => roleContext.value.length > 0)
const hasCandidatePrep = computed(() => candidatePreparation.value.length > 0)
</script>

<template>
  <section aria-labelledby="company-research-heading" class="space-y-6">
    <h2
      id="company-research-heading"
      class="flex items-center gap-2 text-[var(--text-lg)] font-semibold text-[var(--text-primary)]"
    >
      <Building2 class="size-5 text-[var(--color-primary-600)]" aria-hidden="true" />
      Company Research
    </h2>

    <div aria-live="polite" class="sr-only">
      <span v-if="isProcessing">Researching company…</span>
      <span v-else-if="isCompleted">Company research completed</span>
      <span v-else-if="isFailed">Company research failed</span>
    </div>

    <!-- Loading skeleton -->
    <div
      v-if="query.isPending.value"
      class="space-y-3 rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-6 sm:p-8"
    >
      <Skeleton classes="h-4 w-full" />
      <Skeleton classes="h-4 w-5/6" />
      <Skeleton classes="h-10 w-full rounded-lg" />
    </div>

    <!-- Query error -->
    <div
      v-else-if="query.isError.value"
      role="alert"
      class="rounded-[var(--radius-xl)] border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800"
    >
      <p>We couldn't load company research.</p>
      <p v-if="errorDetail" class="mt-1 text-xs">{{ errorDetail }}</p>
      <Button variant="secondary" class="mt-3" @click="query.refetch()"> Try again </Button>
    </div>

    <!-- Not researched -->
    <div
      v-else-if="isNotResearched"
      class="rounded-[var(--radius-xl)] border border-dashed border-[var(--border-default)] bg-[var(--surface-secondary)] p-8 text-center"
    >
      <div
        class="mx-auto flex size-12 items-center justify-center rounded-full bg-white shadow-[var(--shadow-neo-raised-sm)]"
      >
        <Building2 class="size-6 text-[var(--text-muted)]" aria-hidden="true" />
      </div>
      <p class="mt-3 text-sm font-semibold text-[var(--text-primary)]">No company research yet</p>
      <p class="mx-auto mt-1 max-w-sm text-sm leading-relaxed text-[var(--text-muted)]">
        Get a concise, source-backed brief to prepare for this opportunity.
      </p>
      <Button class="mt-5" :loading="isBusy" :disabled="isBusy" @click="start()">
        <Search class="mr-2 size-4" aria-hidden="true" />
        Research company
      </Button>
    </div>

    <!-- Processing -->
    <div
      v-else-if="isProcessing"
      role="status"
      aria-live="polite"
      class="rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-secondary)] p-6"
    >
      <div class="flex items-center gap-3">
        <span
          class="inline-block size-4 animate-spin rounded-full border-2 border-slate-300 border-t-[var(--color-primary-600)]"
          aria-hidden="true"
        />
        <p class="text-sm font-medium text-[var(--text-primary)]">Researching company…</p>
      </div>
      <p class="mt-2 text-sm text-[var(--text-muted)]">
        This usually takes a few seconds. We'll update automatically.
      </p>
      <div class="mt-4 space-y-2" aria-hidden="true">
        <Skeleton classes="h-4 w-full" />
        <Skeleton classes="h-4 w-2/3" />
      </div>
    </div>

    <!-- Failed -->
    <div
      v-else-if="isFailed"
      role="alert"
      class="rounded-[var(--radius-xl)] border border-[var(--color-error-100)] bg-[var(--color-error-50)] p-6"
    >
      <p class="text-sm font-semibold text-[var(--color-error-700)]">Research failed</p>
      <p class="mt-1 text-sm text-[var(--color-error-600)]">
        {{ errorDetail ?? 'We couldn’t complete company research. Try again.' }}
      </p>
      <p v-if="data?.fallback_reason" class="mt-1 text-xs text-[var(--color-error-600)]">
        Reason: {{ data.fallback_reason }}
      </p>
      <Button class="mt-4" :loading="isBusy" :disabled="isBusy" @click="refresh()">
        <RefreshCw class="mr-2 size-4" aria-hidden="true" />
        Retry research
      </Button>
    </div>

    <!-- Completed / Limited — premium report -->
    <template v-else-if="isCompleted && data">
      <CompanyResearchHeader :overview="data.overview" />

      <div
        v-if="showLimited"
        role="note"
        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        <p class="font-semibold">Limited research</p>
        <p class="mt-1 leading-relaxed">
          This brief is based only on the available opportunity and company information and was not
          externally verified.
          {{ data.fallback_reason ? `Reason: ${data.fallback_reason}` : '' }}
        </p>
      </div>

      <!-- Two-column: main content + summary panel -->
      <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="space-y-6">
          <!-- About Company -->
          <InfoCard v-if="hasAbout" title="About Company" :icon="Building2">
            <p class="whitespace-pre-wrap text-sm leading-relaxed text-[var(--text-secondary)]">
              {{ data.overview?.description }}
            </p>
          </InfoCard>

          <!-- Products & Services -->
          <InfoCard v-if="products.length > 0" title="Products & Services" :icon="Package">
            <ClaimBulletList :claims="products" />
          </InfoCard>

          <!-- Technology & Engineering -->
          <InfoCard v-if="technology.length > 0" title="Technology & Engineering" :icon="Cpu">
            <TechPillList :claims="technology" />
          </InfoCard>
        </div>

        <ResearchSummaryPanel :brief="data" :is-busy="isBusy" @refresh="refresh()" />
      </div>

      <!-- Full-width stacked sections -->
      <div class="space-y-6">
        <!-- Engineering Culture (derived from technology inference) -->
        <InfoCard
          v-if="hasEngineeringCulture"
          title="Engineering Culture"
          :icon="Users"
          :inference="true"
        >
          <p class="mb-3 text-xs leading-relaxed text-[var(--text-muted)]">
            Based on careers information and public signals — interpret with care.
          </p>
          <ul class="space-y-3">
            <li v-for="(claim, idx) in engineeringCultureClaims" :key="idx" class="flex gap-3">
              <span
                class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border border-amber-200 bg-amber-50"
                aria-hidden="true"
              >
                <Users class="size-3.5 text-amber-600" />
              </span>
              <p class="text-sm leading-relaxed text-[var(--text-primary)]">{{ claim.text }}</p>
            </li>
          </ul>
        </InfoCard>

        <!-- Role Context -->
        <InfoCard
          v-if="hasRoleContext"
          title="Role Context"
          :icon="CheckCircle2"
          :inference="roleContext.some((c) => c.kind === 'inference')"
        >
          <p
            v-if="roleContext.some((c) => c.kind === 'inference')"
            class="mb-3 text-xs text-[var(--text-muted)]"
          >
            Explains how this opportunity relates to the company. Interpreted from available
            sources.
          </p>
          <ul class="space-y-3">
            <li v-for="(claim, idx) in roleContext" :key="idx" class="flex gap-2.5">
              <span
                class="mt-1 size-1.5 shrink-0 rounded-full"
                :class="
                  claim.kind === 'inference'
                    ? 'bg-amber-500'
                    : claim.kind === 'fact'
                      ? 'bg-emerald-500'
                      : 'bg-slate-400'
                "
                aria-hidden="true"
              />
              <div class="min-w-0 flex-1">
                <p class="text-sm leading-relaxed text-[var(--text-primary)]">{{ claim.text }}</p>
                <span
                  v-if="claim.kind === 'inference'"
                  class="mt-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 ring-1 ring-amber-200"
                >
                  INFERENCE
                </span>
                <span
                  v-else-if="claim.kind === 'unknown'"
                  class="mt-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 ring-1 ring-slate-200"
                >
                  UNKNOWN
                </span>
              </div>
            </li>
          </ul>
        </InfoCard>

        <!-- Recent Information -->
        <InfoCard title="Recent Information" :icon="Clock">
          <RecentInformationState
            :claims="recentInformation"
            :is-busy="isBusy"
            @refresh="refresh()"
          />
        </InfoCard>

        <!-- Candidate Preparation -->
        <InfoCard v-if="hasCandidatePrep" title="Application Preparation" :icon="Lightbulb">
          <p class="mb-3 text-xs font-medium text-[var(--text-muted)]">
            What to know before applying
          </p>
          <ClaimBulletList :claims="candidatePreparation" />
        </InfoCard>
      </div>

      <!-- Source & provenance -->
      <SourceProvenanceTable :brief="data" />
    </template>
  </section>
</template>
