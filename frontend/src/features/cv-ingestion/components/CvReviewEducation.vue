<script setup lang="ts">
import { Check, X, FileText, MapPin, Calendar, GraduationCap, BookOpen } from '@lucide/vue'
import type { CvSuggestion, EducationValue, ReviewDecision, ImportPreview } from '../types'
import { fieldOf } from '../utils/suggestionFields'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  suggestions: CvSuggestion[]
  readonly?: boolean
  preview?: ImportPreview | null
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
  return (
    s.current_value !== null && Object.keys(s.current_value).length > 0 && !isPossibleDuplicate(s)
  )
}

function isPossibleDuplicate(s: CvSuggestion<'education'>): boolean {
  if ((s.current_value as Record<string, unknown> | null)?.is_possible_duplicate) return true
  if (!props.preview) return false
  return props.preview.conflicts.some(
    (c) => c.type === 'possible_duplicate' && (c as Record<string, unknown>).suggestion_id === s.id,
  )
}

function possibleCurrent(s: CvSuggestion<'education'>): Record<string, unknown> | null {
  if (s.current_value && (s.current_value as Record<string, unknown>).is_possible_duplicate)
    return s.current_value as Record<string, unknown>
  if (!props.preview) return null
  const conflict = props.preview.conflicts.find(
    (c) => c.type === 'possible_duplicate' && (c as Record<string, unknown>).suggestion_id === s.id,
  ) as Record<string, unknown> | undefined
  return (conflict?.current as Record<string, unknown>) ?? null
}

function possibleReason(s: CvSuggestion<'education'>): string | null {
  const pc = possibleCurrent(s)
  if (pc?.reason) return String(pc.reason)
  const cv = s.current_value as Record<string, unknown> | null
  if (cv?.reason) return String(cv.reason)
  if (!props.preview) return null
  const conflict = props.preview.conflicts.find(
    (c) => c.type === 'possible_duplicate' && (c as Record<string, unknown>).suggestion_id === s.id,
  ) as Record<string, unknown> | undefined
  return conflict?.reason ? String(conflict.reason) : null
}

function possibleSimilarity(s: CvSuggestion<'education'>): number | null {
  const pc = possibleCurrent(s)
  if (typeof pc?.similarity === 'number') return pc.similarity as number
  const cv = s.current_value as Record<string, unknown> | null
  if (typeof cv?.similarity === 'number') return cv.similarity as number
  if (!props.preview) return null
  const conflict = props.preview.conflicts.find(
    (c) => c.type === 'possible_duplicate' && (c as Record<string, unknown>).suggestion_id === s.id,
  ) as Record<string, unknown> | undefined
  return typeof conflict?.similarity === 'number' ? (conflict.similarity as number) : null
}

function decisionLabel(s: CvSuggestion): string {
  const map: Record<string, string> = {
    accepted: 'Accepted',
    edited: 'Edited',
    rejected: 'Skipped',
    keep_existing: 'Kept existing',
    create_new: 'Created separately',
    update_existing: 'Updated existing',
  }
  return map[s.review_status] ?? s.review_status
}

const decisionBadgeClasses = (s: CvSuggestion): string => {
  const map: Record<string, string> = {
    accepted: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    edited: 'bg-blue-100 text-blue-700 border-blue-200',
    rejected: 'bg-red-100 text-red-600 border-red-200',
    keep_existing: 'bg-slate-100 text-slate-600 border-slate-200',
    create_new: 'bg-indigo-100 text-indigo-700 border-indigo-200',
    update_existing: 'bg-amber-100 text-amber-700 border-amber-200',
  }
  return map[s.review_status] ?? 'bg-slate-100 text-slate-600 border-slate-200'
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-for="s in suggestions as CvSuggestion<'education'>[]"
      :key="s.id"
      class="rounded-2xl bg-white border border-slate-200 shadow-lg hover:shadow-xl transition-all duration-200"
    >
      <div class="p-6">
        <!-- Header row -->
        <div class="flex items-start justify-between gap-3 mb-4">
          <div class="flex items-center gap-3 min-w-0">
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
              :class="
                isReviewed(s) ? 'bg-white shadow-sm border border-slate-200' : 'bg-primary-50'
              "
            >
              <GraduationCap
                class="h-5 w-5"
                :class="isReviewed(s) ? 'text-slate-500' : 'text-primary-600'"
                aria-hidden="true"
              />
            </div>
            <div class="min-w-0">
              <p class="font-semibold text-slate-900 truncate">{{ val(s).degree }}</p>
              <p class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                <BookOpen class="h-3 w-3" aria-hidden="true" />
                {{ val(s).institution }}
              </p>
            </div>
          </div>

          <span
            v-if="isReviewed(s) && !readonly"
            class="shrink-0 inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold"
            :class="decisionBadgeClasses(s)"
          >
            {{ decisionLabel(s) }}
          </span>
          <span
            v-else-if="!isReviewed(s) && !readonly"
            class="shrink-0 inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-600"
          >
            Pending
          </span>
        </div>

        <!-- Field of study tag -->
        <div v-if="val(s).field_of_study" class="mb-4">
          <span
            class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm"
          >
            {{ val(s).field_of_study }}
          </span>
        </div>

        <!-- Meta strip -->
        <div
          v-if="val(s).location || val(s).start_date"
          class="mb-4 flex flex-wrap items-center gap-1.5 rounded-xl bg-gradient-to-r from-slate-50 to-white border border-slate-100 px-3 py-2 text-xs text-slate-500"
        >
          <span v-if="val(s).location" class="inline-flex items-center gap-1">
            <MapPin class="h-3 w-3" aria-hidden="true" /> {{ val(s).location }}
          </span>
          <span
            v-if="val(s).location && val(s).start_date"
            class="text-slate-300"
            aria-hidden="true"
            >|</span
          >
          <span v-if="val(s).start_date" class="inline-flex items-center gap-1">
            <Calendar class="h-3 w-3" aria-hidden="true" />
            {{ val(s).start_date }}
            <template v-if="val(s).is_current">&ndash; Present</template>
            <template v-else-if="val(s).end_date">&ndash; {{ val(s).end_date }}</template>
          </span>
        </div>

        <!-- Description -->
        <p
          v-if="val(s).description"
          class="mb-4 text-sm text-slate-600 whitespace-pre-wrap leading-relaxed"
        >
          {{ val(s).description }}
        </p>

        <!-- Source text -->
        <p
          v-if="s.source_text"
          class="pl-3 border-l-2 border-slate-200 text-xs text-slate-400 italic"
        >
          Source: &ldquo;{{ s.source_text.substring(0, 100)
          }}{{ s.source_text.length > 100 ? '...' : '' }}&rdquo;
        </p>

        <!-- Existing entry notice -->
        <div
          v-if="hasCurrent(s) && !isReviewed(s)"
          class="mt-4 border-l-4 border-amber-400 bg-amber-50/70 rounded-r-xl p-3"
        >
          <p class="text-xs font-semibold text-amber-700">Existing profile entry:</p>
          <p class="mt-0.5 text-sm text-amber-800">
            {{ fieldOf(s.current_value, ['title', 'degree']) }} at
            {{ fieldOf(s.current_value, ['organization', 'institution']) }}
          </p>
        </div>

        <!-- Possible duplicate notice -->
        <div
          v-if="isPossibleDuplicate(s) && !isReviewed(s)"
          class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4"
        >
          <p class="text-xs font-semibold text-blue-700">Possible duplicate found</p>
          <p class="mt-1 text-xs text-blue-600">
            This looks similar to an existing entry. Please choose how to handle it.
            <span v-if="possibleReason(s)" class="font-medium"> — {{ possibleReason(s) }}</span>
            <span v-if="possibleSimilarity(s)" class="ml-1"
              >({{ Math.round((possibleSimilarity(s) as number) * 100) }}% similar)</span
            >
          </p>
          <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-3">
              <p class="text-xs font-medium text-slate-500">Existing profile</p>
              <p class="mt-1 text-sm font-semibold text-slate-900 truncate">
                {{ fieldOf(possibleCurrent(s), ['title', 'degree']) }}
              </p>
              <p class="text-xs text-slate-600 flex items-center gap-1 mt-0.5">
                <BookOpen class="h-3 w-3" aria-hidden="true" />
                {{ fieldOf(possibleCurrent(s), ['organization', 'institution']) }}
              </p>
              <p
                v-if="fieldOf(possibleCurrent(s), ['start_date'])"
                class="text-xs text-slate-500 mt-1 flex items-center gap-1"
              >
                <Calendar class="h-3 w-3" aria-hidden="true" />
                {{ fieldOf(possibleCurrent(s), ['start_date']) }}
                <template v-if="fieldOf(possibleCurrent(s), ['end_date'])">
                  – {{ fieldOf(possibleCurrent(s), ['end_date']) }}</template
                >
                <template v-else> – Present</template>
              </p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-white p-3">
              <p class="text-xs font-medium text-blue-600">From this CV</p>
              <p class="mt-1 text-sm font-semibold text-slate-900 truncate">{{ val(s).degree }}</p>
              <p class="text-xs text-slate-600 flex items-center gap-1 mt-0.5">
                <BookOpen class="h-3 w-3" aria-hidden="true" /> {{ val(s).institution }}
              </p>
              <p
                v-if="val(s).start_date"
                class="text-xs text-slate-500 mt-1 flex items-center gap-1"
              >
                <Calendar class="h-3 w-3" aria-hidden="true" />
                {{ val(s).start_date }}
                <template v-if="val(s).is_current"> – Present</template>
                <template v-else-if="val(s).end_date"> – {{ val(s).end_date }}</template>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Action panel -->
      <div
        v-if="!readonly && !isReviewed(s)"
        class="border-t border-slate-200/50 bg-slate-50/80 rounded-b-2xl px-6 py-4"
      >
        <div v-if="isPossibleDuplicate(s)" class="flex flex-wrap gap-2">
          <Button
            size="sm"
            variant="outline"
            class="shadow-sm border-slate-300"
            @click="emit('decision', s.id, { decision: 'keep_existing' })"
          >
            <FileText class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Keep existing
          </Button>
          <Button
            size="sm"
            variant="outline"
            class="shadow-sm border-amber-300 text-amber-700 hover:bg-amber-50"
            @click="emit('decision', s.id, { decision: 'update_existing' })"
          >
            <Check class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Update existing
          </Button>
          <Button
            size="sm"
            class="shadow-md"
            @click="emit('decision', s.id, { decision: 'create_new' })"
          >
            <Check class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Create separately
          </Button>
        </div>
        <div v-else class="flex flex-wrap gap-2">
          <Button
            size="sm"
            class="shadow-md"
            @click="emit('decision', s.id, { decision: 'accepted' })"
          >
            <Check class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Accept
          </Button>
          <Button
            size="sm"
            variant="outline"
            class="shadow-sm border-slate-300"
            @click="emit('decision', s.id, { decision: 'keep_existing' })"
          >
            <FileText class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Keep existing
          </Button>
          <Button
            size="sm"
            variant="ghost"
            class="hover:bg-red-50 hover:text-red-700 text-red-600"
            @click="emit('decision', s.id, { decision: 'rejected' })"
          >
            <X class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Reject
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
