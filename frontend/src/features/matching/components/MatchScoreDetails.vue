<script setup lang="ts">
import { computed } from 'vue'
import { ChevronDown } from '@lucide/vue'
import { formatDate } from '@/app/utils/date'
import type { MatchAnalysis } from '../types'
import { categoryLabels } from '../utils/matchPresentation'
import CategoryMeter from './CategoryMeter.vue'

const props = defineProps<{
  analysis: MatchAnalysis
}>()

const components = computed(() => props.analysis.score_components)

const evidenceCoverage = computed(() => props.analysis.evidence_coverage_score)

const updatedAt = computed(() =>
  formatDate(props.analysis.timestamps.completed_at ?? props.analysis.timestamps.updated_at),
)
</script>

<template>
  <details id="score-details" class="group rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
    <summary
      class="flex cursor-pointer list-none items-center justify-between gap-3 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none"
    >
      <span>
        <span class="text-base font-semibold text-slate-900">How this match was calculated</span>
        <span class="mt-0.5 block text-sm text-slate-500">
          The score combines how well your profile covers the job's requirements.
        </span>
      </span>
      <ChevronDown
        class="size-5 shrink-0 text-slate-400 transition-transform group-open:rotate-180"
        aria-hidden="true"
      />
    </summary>

    <div class="mt-6">
      <div v-if="components.length" class="grid gap-5 sm:grid-cols-2">
        <CategoryMeter
          v-for="component in components"
          :key="component.category"
          :label="categoryLabels[component.category]"
          :score="component.score"
          :has-data="component.has_candidate_data"
        />
      </div>
      <p v-else class="text-sm text-slate-500">
        No category scores are available for this analysis.
      </p>

      <p class="mt-6 border-t border-slate-100 pt-4 text-xs text-slate-400">
        Evidence coverage
        <span class="font-semibold text-slate-600">
          {{ evidenceCoverage === null ? 'not measured' : `${evidenceCoverage}%` }}
        </span>
        <template v-if="updatedAt"> · Analysis updated {{ updatedAt }}</template>
      </p>
    </div>
  </details>
</template>
