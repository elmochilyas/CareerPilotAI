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

const TYPE_META: Record<string, { label: string; hint: string }> = {
  headline: { label: 'Headline', hint: 'Your professional headline or title.' },
  summary: { label: 'Professional summary', hint: 'Your professional summary or objective.' },
}

function meta(s: CvSuggestion): { label: string; hint: string } {
  return TYPE_META[s.type] ?? { label: s.type, hint: '' }
}

function isReviewed(s: CvSuggestion): boolean {
  return s.review_status !== 'pending'
}

function displayValue(s: CvSuggestion): string {
  const v = s.suggested_value as { value: string }
  return v?.value ?? ''
}

function currentDisplay(s: CvSuggestion): string {
  return s.current_value?.value ? String(s.current_value.value) : ''
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
      Review how your professional profile information was extracted.
    </p>

    <div class="space-y-4">
      <div
        v-for="s in suggestions"
        :key="s.id"
        :class="[
          'rounded-lg border p-4 transition-colors',
          isReviewed(s) ? 'border-green-200 bg-green-50/50' : 'border-slate-200 bg-white',
        ]"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              {{ meta(s).label }}
            </p>

            <div v-if="currentDisplay(s)" class="space-y-1">
              <p class="text-[10px] font-medium text-slate-400 uppercase">Current</p>
              <p class="rounded bg-slate-50 px-3 py-2 text-sm text-slate-600">
                {{ currentDisplay(s) }}
              </p>
            </div>

            <div class="space-y-1">
              <p class="text-[10px] font-medium text-primary-500 uppercase">Extracted from CV</p>
              <p
                class="rounded bg-primary-50/50 px-3 py-2 text-sm text-slate-800 whitespace-pre-wrap"
              >
                {{ displayValue(s) }}
              </p>
            </div>

            <p v-if="s.source_text" class="text-xs text-slate-400 italic">
              Source: &ldquo;{{ s.source_text.substring(0, 120)
              }}{{ s.source_text.length > 120 ? '...' : '' }}&rdquo;
            </p>
          </div>

          <span
            v-if="isReviewed(s) && !readonly"
            class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
          >
            {{ decisionLabel(s) }}
          </span>
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
            <X class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Skip
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
