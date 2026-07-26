<script setup lang="ts">
import { Check, X, FileText } from '@lucide/vue'
import type { CvSuggestion, ReviewDecision } from '../types'
import Button from '@/components/ui/Button.vue'

defineProps<{
  suggestions: CvSuggestion[]
  readonly?: boolean
}>()

const emit = defineEmits<{
  decision: [suggestionId: number, decision: ReviewDecision]
}>()

const FIELD_LABELS: Record<string, string> = {
  full_name: 'Full name',
  email: 'Email',
  phone: 'Phone',
  city: 'City',
  country: 'Country',
}

function labelFor(s: CvSuggestion): string {
  if (s.field_name && FIELD_LABELS[s.field_name]) return FIELD_LABELS[s.field_name]!
  return s.field_name ? s.field_name.replace(/_/g, ' ') : 'Field'
}

function currentDisplay(s: CvSuggestion): string {
  const v = s.current_value?.value
  return v ? String(v) : 'Not provided'
}

function suggestedDisplay(s: CvSuggestion): string {
  const v = s.suggested_value as { value: string }
  return v?.value ?? ''
}

function isReviewed(s: CvSuggestion): boolean {
  return s.review_status !== 'pending'
}

function isMatch(s: CvSuggestion): boolean {
  const cur = currentDisplay(s)
  const sug = suggestedDisplay(s)
  return cur === sug
}

function decisionLabel(s: CvSuggestion): string {
  const labels: Record<string, string> = {
    accepted: 'Using CV value',
    edited: 'Edited',
    rejected: 'Skipped',
    keep_existing: 'Kept current',
  }
  return labels[s.review_status] ?? s.review_status
}
</script>

<template>
  <div class="space-y-4">
    <p class="text-sm text-slate-500">Review the personal information extracted from your CV.</p>

    <div class="space-y-3">
      <div
        v-for="s in suggestions"
        :key="s.id"
        :class="[
          'rounded-lg border p-4 transition-colors',
          isReviewed(s)
            ? isMatch(s)
              ? 'border-emerald-200 bg-emerald-50/50'
              : 'border-blue-200 bg-blue-50/50'
            : 'border-slate-200 bg-white',
        ]"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              {{ labelFor(s) }}
            </p>

            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <p class="text-[10px] font-medium text-slate-400 uppercase">Current</p>
                <p class="mt-0.5 text-sm text-slate-600">
                  {{ currentDisplay(s) }}
                </p>
              </div>
              <div>
                <p class="text-[10px] font-medium text-primary-500 uppercase">From your CV</p>
                <p class="mt-0.5 text-sm text-slate-800 font-medium">
                  {{ suggestedDisplay(s) }}
                </p>
              </div>
            </div>
          </div>

          <div v-if="isReviewed(s) && !readonly" class="shrink-0">
            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
              {{ decisionLabel(s) }}
            </span>
          </div>
        </div>

        <div v-if="!readonly && !isReviewed(s)" class="mt-3 flex flex-wrap gap-2">
          <Button size="sm" @click="emit('decision', s.id, { decision: 'accepted' })">
            <Check class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Use CV value
          </Button>
          <Button
            size="sm"
            variant="outline"
            @click="emit('decision', s.id, { decision: 'keep_existing' })"
          >
            <FileText class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Keep current
          </Button>
          <Button
            size="sm"
            variant="ghost"
            class="text-red-600 hover:text-red-700"
            @click="emit('decision', s.id, { decision: 'rejected' })"
          >
            <X class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Skip
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
