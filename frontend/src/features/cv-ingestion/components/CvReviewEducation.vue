<script setup lang="ts">
import { Check, X, FileText, MapPin, Calendar } from '@lucide/vue'
import type { CvSuggestion, EducationValue, ReviewDecision } from '../types'
import Button from '@/components/ui/Button.vue'

defineProps<{
  suggestions: CvSuggestion[]
  readonly?: boolean
}>()

const emit = defineEmits<{
  decision: [suggestionId: number, decision: ReviewDecision]
}>()

function val(s: CvSuggestion<'education'>): EducationValue {
  return s.suggested_value as EducationValue
}

function isReviewed(s: CvSuggestion): boolean {
  return s.review_status !== 'pending'
}

function hasCurrent(s: CvSuggestion<'education'>): boolean {
  return s.current_value !== null && Object.keys(s.current_value).length > 0
}

function decisionLabel(s: CvSuggestion): string {
  const labels: Record<string, string> = {
    accepted: 'Accepted',
    edited: 'Edited',
    rejected: 'Skipped',
    keep_existing: 'Kept existing',
  }
  return labels[s.review_status] ?? s.review_status
}
</script>

<template>
  <div class="space-y-4">
    <p class="text-sm text-slate-500">
      We found {{ suggestions.length }} education entr{{ suggestions.length !== 1 ? 'ies' : 'y' }}.
      Review each one before continuing.
    </p>

    <div v-for="s in suggestions as CvSuggestion<'education'>[]" :key="s.id" class="space-y-3">
      <div
        :class="[
          'rounded-lg border p-4 transition-colors',
          isReviewed(s) ? 'border-green-200 bg-green-50/50' : 'border-slate-200 bg-white',
        ]"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 space-y-2">
            <p class="font-semibold text-slate-900">{{ val(s).degree }}</p>
            <p class="text-sm text-slate-600">{{ val(s).institution }}</p>

            <div v-if="val(s).field_of_study" class="text-xs text-slate-500">
              Field: {{ val(s).field_of_study }}
            </div>

            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
              <span v-if="val(s).location" class="inline-flex items-center gap-1">
                <MapPin class="h-3 w-3" aria-hidden="true" /> {{ val(s).location }}
              </span>
              <span v-if="val(s).start_date" class="inline-flex items-center gap-1">
                <Calendar class="h-3 w-3" aria-hidden="true" />
                {{ val(s).start_date }}
                <template v-if="val(s).is_current"> – Present</template>
                <template v-else-if="val(s).end_date"> – {{ val(s).end_date }}</template>
              </span>
            </div>

            <p v-if="val(s).description" class="text-sm text-slate-600 whitespace-pre-wrap">
              {{ val(s).description }}
            </p>

            <p v-if="s.source_text" class="text-xs text-slate-400 italic">
              Source: &ldquo;{{ s.source_text.substring(0, 100)
              }}{{ s.source_text.length > 100 ? '...' : '' }}&rdquo;
            </p>
          </div>

          <span
            v-if="isReviewed(s) && !readonly"
            class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
          >
            {{ decisionLabel(s) }}
          </span>
        </div>

        <div
          v-if="hasCurrent(s) && !isReviewed(s)"
          class="mt-3 rounded bg-amber-50 border border-amber-200 p-3"
        >
          <p class="text-xs font-medium text-amber-700">
            Existing profile entry: {{ (s.current_value as unknown as EducationValue)?.degree }} at
            {{ (s.current_value as unknown as EducationValue)?.institution }}
          </p>
        </div>

        <div v-if="!readonly && !isReviewed(s)" class="mt-3 flex flex-wrap gap-2">
          <Button size="sm" @click="emit('decision', s.id, { decision: 'accepted' })">
            <Check class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Accept
          </Button>
          <Button
            size="sm"
            variant="outline"
            @click="emit('decision', s.id, { decision: 'keep_existing' })"
          >
            <FileText class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Keep existing
          </Button>
          <Button
            size="sm"
            variant="ghost"
            class="text-red-600 hover:text-red-700"
            @click="emit('decision', s.id, { decision: 'rejected' })"
          >
            <X class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Reject
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
