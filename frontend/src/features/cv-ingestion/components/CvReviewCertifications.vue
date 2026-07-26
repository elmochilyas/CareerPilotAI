<script setup lang="ts">
import { Check, X, FileText, ExternalLink } from '@lucide/vue'
import type { CvSuggestion, CertificationValue, ReviewDecision } from '../types'
import Button from '@/components/ui/Button.vue'

defineProps<{
  suggestions: CvSuggestion[]
  readonly?: boolean
}>()

const emit = defineEmits<{
  decision: [suggestionId: number, decision: ReviewDecision]
}>()

function val(s: CvSuggestion<'certification'>): CertificationValue {
  return s.suggested_value as CertificationValue
}

function isReviewed(s: CvSuggestion): boolean {
  return s.review_status !== 'pending'
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
      We found {{ suggestions.length }} certification{{ suggestions.length !== 1 ? 's' : '' }}.
      Review each one before continuing.
    </p>

    <div v-for="s in suggestions as CvSuggestion<'certification'>[]" :key="s.id" class="space-y-3">
      <div
        :class="[
          'rounded-lg border p-4 transition-colors',
          isReviewed(s) ? 'border-green-200 bg-green-50/50' : 'border-slate-200 bg-white',
        ]"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 space-y-2">
            <p class="font-semibold text-slate-900">{{ val(s).name }}</p>
            <p v-if="val(s).issuer" class="text-sm text-slate-600">{{ val(s).issuer }}</p>

            <div v-if="val(s).date" class="text-xs text-slate-500">
              {{ val(s).date }}
            </div>

            <p v-if="val(s).description" class="text-sm text-slate-600">
              {{ val(s).description }}
            </p>

            <a
              v-if="val(s).url"
              :href="val(s).url ?? undefined"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1 text-xs text-primary-600 hover:text-primary-700"
            >
              <ExternalLink class="h-3 w-3" aria-hidden="true" /> View certification
            </a>
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
            <X class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Reject
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
