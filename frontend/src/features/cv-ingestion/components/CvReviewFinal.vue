<script setup lang="ts">
import { computed } from 'vue'
import { CheckCircle2, AlertTriangle } from '@lucide/vue'
import type { CvSuggestion, ImportPreview } from '../types'
import type { StepDef, StepStatus } from './CvReviewStepper.vue'

export interface FinalStepSummary {
  fieldsToUpdate: { label: string; stepIndex: number }[]
  newItems: { count: number; label: string; stepIndex: number }[]
  skillsToAdd: string[]
  itemsIgnored: { count: number; label: string; stepIndex: number }[]
  conflicts: number
}

const props = defineProps<{
  suggestions: CvSuggestion[]
  preview: ImportPreview | null
  summary: FinalStepSummary
  steps: StepDef[]
  stepStatuses: StepStatus[]
  applyDisabledReason: string | null
  applyPending: boolean
  readonly?: boolean
}>()

const emit = defineEmits<{
  navigateToStep: [index: number]
  apply: []
}>()

const allAccepted = computed(() => {
  return props.suggestions.filter(
    (s) => s.review_status === 'accepted' || s.review_status === 'edited',
  ).length
})
</script>

<template>
  <div class="space-y-6">
    <div v-if="readonly" class="space-y-1">
      <h2 class="text-lg font-semibold text-slate-900">Import summary</h2>
      <p class="text-sm text-slate-500">
        This CV has already been imported. Below is a summary of what was applied.
      </p>
    </div>
    <div v-else class="space-y-1">
      <h2 class="text-lg font-semibold text-slate-900">Final review</h2>
      <p class="text-sm text-slate-500">
        Review your decisions before applying changes to your profile.
      </p>
    </div>

    <div class="space-y-4">
      <!-- Profile fields to update -->
      <div v-if="summary.fieldsToUpdate.length > 0" class="rounded-lg border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-semibold text-slate-700">Profile fields to update</h3>
          <button
            v-if="!readonly"
            type="button"
            class="text-xs text-primary-600 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded px-1.5 py-0.5"
            @click="emit('navigateToStep', summary.fieldsToUpdate[0]!.stepIndex)"
          >
            Edit
          </button>
        </div>
        <ul class="space-y-1">
          <li
            v-for="field in summary.fieldsToUpdate"
            :key="field.label"
            class="text-sm text-slate-600 flex items-center gap-2"
          >
            <CheckCircle2 class="h-3.5 w-3.5 text-emerald-500 shrink-0" aria-hidden="true" />
            {{ field.label }}
          </li>
        </ul>
      </div>

      <!-- New profile items -->
      <div
        v-for="item in summary.newItems"
        :key="item.label"
        class="rounded-lg border border-slate-200 p-4"
      >
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-semibold text-slate-700">New {{ item.label }}</h3>
          <button
            v-if="!readonly"
            type="button"
            class="text-xs text-primary-600 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded px-1.5 py-0.5"
            @click="emit('navigateToStep', item.stepIndex)"
          >
            Edit
          </button>
        </div>
        <p class="text-sm text-slate-600">
          {{ item.count }} {{ item.label }} will be added to your profile.
        </p>
      </div>

      <!-- Skills to add -->
      <div v-if="summary.skillsToAdd.length > 0" class="rounded-lg border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-semibold text-slate-700">Skills to add</h3>
          <button
            v-if="!readonly"
            type="button"
            class="text-xs text-primary-600 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded px-1.5 py-0.5"
          >
            Edit
          </button>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="skill in summary.skillsToAdd"
            :key="skill"
            class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600"
          >
            {{ skill }}
          </span>
        </div>
      </div>

      <!-- Items ignored -->
      <div
        v-for="item in summary.itemsIgnored"
        :key="item.label"
        class="rounded-lg border border-slate-200 p-4 bg-slate-50/50"
      >
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-sm font-medium text-slate-500">Items to ignore</h3>
        </div>
        <p class="text-sm text-slate-400">
          {{ item.count }} {{ item.label }} will not be imported.
        </p>
      </div>

      <!-- Conflicts -->
      <div v-if="summary.conflicts > 0" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
        <div class="flex items-start gap-3">
          <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
          <div>
            <h3 class="text-sm font-semibold text-amber-800">
              {{ summary.conflicts }} conflict{{ summary.conflicts > 1 ? 's' : '' }} remaining
            </h3>
            <p class="text-sm text-amber-700 mt-1">
              Conflicts must be resolved before applying changes.
            </p>
          </div>
        </div>
      </div>

      <!-- No conflicts -->
      <div
        v-if="summary.conflicts === 0 && !readonly"
        class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"
      >
        <div class="flex items-center gap-3">
          <CheckCircle2 class="h-5 w-5 text-emerald-600 shrink-0" aria-hidden="true" />
          <p class="text-sm font-medium text-emerald-800">No conflicts detected</p>
        </div>
      </div>

      <!-- All reviewed message -->
      <div v-if="!readonly" class="text-center text-sm text-slate-500 pt-2">
        {{ allAccepted }} item{{ allAccepted !== 1 ? 's' : '' }} accepted for import.
      </div>
    </div>
  </div>
</template>
