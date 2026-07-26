<script setup lang="ts">
import { computed } from 'vue'
import { CheckCircle2, AlertTriangle, ArrowRight, XCircle } from '@lucide/vue'
import type { CvSuggestion, ImportPreview } from '../types'
import type { StepDef, StepStatus } from './CvReviewStepper.vue'
import Button from '@/components/ui/Button.vue'

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

const totalModified = computed(() => {
  return (
    props.summary.fieldsToUpdate.length +
    props.summary.newItems.reduce((sum, i) => sum + i.count, 0) +
    props.summary.skillsToAdd.length
  )
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
      <!-- Total summary bar -->
      <div v-if="!readonly" class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
        <div class="flex items-center justify-between">
          <p class="text-sm text-slate-600">
            <span class="font-semibold text-slate-900">{{ allAccepted }}</span>
            <span class="mx-1">of</span>
            <span class="font-semibold text-slate-900">{{ props.suggestions.length }}</span>
            suggestions accepted
          </p>
          <div class="flex items-center gap-1.5">
            <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-400" aria-hidden="true" />
            <span class="text-xs text-slate-500">{{ allAccepted }} accepted</span>
          </div>
        </div>
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
          <div
            class="h-full rounded-full bg-emerald-400 transition-all duration-700"
            :style="{
              width: `${props.suggestions.length > 0 ? (allAccepted / props.suggestions.length) * 100 : 0}%`,
            }"
          />
        </div>
      </div>

      <!-- Profile fields to update -->
      <div
        v-if="summary.fieldsToUpdate.length > 0"
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <div class="flex h-7 w-7 items-center justify-center rounded-md bg-primary-50">
              <CheckCircle2 class="h-4 w-4 text-primary-600" aria-hidden="true" />
            </div>
            <h3 class="text-sm font-semibold text-slate-700">Profile fields to update</h3>
          </div>
          <button
            v-if="!readonly"
            type="button"
            class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded px-1.5 py-0.5"
            @click="emit('navigateToStep', summary.fieldsToUpdate[0]!.stepIndex)"
          >
            Edit
            <ArrowRight class="h-3 w-3" aria-hidden="true" />
          </button>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="field in summary.fieldsToUpdate"
            :key="field.label"
            class="inline-flex items-center gap-1.5 rounded-md bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700"
          >
            <CheckCircle2 class="h-3 w-3" aria-hidden="true" />
            {{ field.label }}
          </span>
        </div>
      </div>

      <!-- New profile items -->
      <div
        v-for="item in summary.newItems"
        :key="item.label"
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2">
            <div class="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-50">
              <CheckCircle2 class="h-4 w-4 text-emerald-600" aria-hidden="true" />
            </div>
            <h3 class="text-sm font-semibold text-slate-700 capitalize">New {{ item.label }}</h3>
          </div>
          <button
            v-if="!readonly"
            type="button"
            class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded px-1.5 py-0.5"
            @click="emit('navigateToStep', item.stepIndex)"
          >
            Edit
            <ArrowRight class="h-3 w-3" aria-hidden="true" />
          </button>
        </div>
        <p class="text-sm text-slate-600">
          {{ item.count }} {{ item.label }} will be added to your profile.
        </p>
      </div>

      <!-- Skills to add -->
      <div
        v-if="summary.skillsToAdd.length > 0"
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <div class="flex items-center gap-2 mb-3">
          <div class="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-50">
            <CheckCircle2 class="h-4 w-4 text-emerald-600" aria-hidden="true" />
          </div>
          <h3 class="text-sm font-semibold text-slate-700">
            Skills to add ({{ summary.skillsToAdd.length }})
          </h3>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="skill in summary.skillsToAdd"
            :key="skill"
            class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
          >
            {{ skill }}
          </span>
        </div>
      </div>

      <!-- Items ignored -->
      <div
        v-for="item in summary.itemsIgnored"
        :key="item.label"
        class="rounded-xl border border-slate-200 bg-slate-50/50 p-5"
      >
        <div class="flex items-center gap-2">
          <XCircle class="h-4 w-4 text-slate-400" aria-hidden="true" />
          <h3 class="text-sm font-medium text-slate-500">Items to ignore</h3>
        </div>
        <p class="mt-1 text-sm text-slate-400 ml-6">
          {{ item.count }} {{ item.label }} will not be imported.
        </p>
      </div>

      <!-- Conflicts -->
      <div v-if="summary.conflicts > 0" class="rounded-xl border border-amber-200 bg-amber-50 p-5">
        <div class="flex items-start gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100">
            <AlertTriangle class="h-5 w-5 text-amber-600" aria-hidden="true" />
          </div>
          <div>
            <h3 class="text-sm font-semibold text-amber-800">
              {{ summary.conflicts }} conflict{{ summary.conflicts > 1 ? 's' : '' }} remaining
            </h3>
            <p class="text-sm text-amber-700 mt-0.5">
              Conflicts must be resolved before applying changes.
            </p>
          </div>
        </div>
      </div>

      <!-- No conflicts -->
      <div
        v-if="summary.conflicts === 0 && !readonly"
        class="rounded-xl border border-emerald-200 bg-emerald-50 p-5"
      >
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100"
          >
            <CheckCircle2 class="h-5 w-5 text-emerald-600" aria-hidden="true" />
          </div>
          <div>
            <p class="text-sm font-semibold text-emerald-800">No conflicts detected</p>
            <p class="text-sm text-emerald-700">
              {{ totalModified }} change{{ totalModified !== 1 ? 's' : '' }} ready to apply.
            </p>
          </div>
        </div>
      </div>

      <!-- All reviewed message -->
      <div v-if="!readonly" class="text-center text-sm text-slate-500 pt-2">
        {{ allAccepted }} item{{ allAccepted !== 1 ? 's' : '' }} accepted for import.
      </div>

      <!-- Apply button (when not readonly and not in footer) -->
      <div v-if="!readonly" class="pt-2">
        <Button
          class="w-full"
          size="lg"
          :disabled="applyDisabledReason !== null"
          :loading="applyPending"
          @click="emit('apply')"
        >
          <CheckCircle2 class="mr-2 h-5 w-5" aria-hidden="true" />
          {{ applyPending ? 'Applying...' : 'Apply to profile' }}
        </Button>
        <p v-if="applyDisabledReason" class="mt-2 text-center text-xs text-amber-600">
          {{ applyDisabledReason }}
        </p>
      </div>
    </div>
  </div>
</template>
