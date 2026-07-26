<script setup lang="ts">
import { Check, X, FileText, User, Mail, Phone, MapPin, Globe, type LucideIcon } from '@lucide/vue'
import type { CvSuggestion, ReviewDecision } from '../types'
import Button from '@/components/ui/Button.vue'

defineProps<{
  suggestions: CvSuggestion[]
  readonly?: boolean
}>()

const emit = defineEmits<{
  decision: [suggestionId: number, decision: ReviewDecision]
}>()

const FIELD_META: Record<string, { label: string; icon: LucideIcon }> = {
  full_name: { label: 'Full name', icon: User },
  email: { label: 'Email', icon: Mail },
  phone: { label: 'Phone', icon: Phone },
  city: { label: 'City', icon: MapPin },
  country: { label: 'Country', icon: Globe },
}

function labelFor(s: CvSuggestion): string {
  if (s.field_name && FIELD_META[s.field_name]) return FIELD_META[s.field_name]!.label
  return s.field_name ? s.field_name.replace(/_/g, ' ') : 'Field'
}

function iconFor(s: CvSuggestion): LucideIcon {
  if (s.field_name && FIELD_META[s.field_name]) return FIELD_META[s.field_name]!.icon
  return FileText
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
  return currentDisplay(s) === suggestedDisplay(s)
}

const decisionBadge = (s: CvSuggestion): { label: string; classes: string } => {
  const map: Record<string, { label: string; classes: string }> = {
    accepted: {
      label: isMatch(s) ? 'Matches current' : 'Using CV value',
      classes: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    },
    edited: { label: 'Edited', classes: 'bg-blue-100 text-blue-700 border-blue-200' },
    rejected: { label: 'Skipped', classes: 'bg-red-100 text-red-600 border-red-200' },
    keep_existing: {
      label: 'Kept current',
      classes: 'bg-slate-100 text-slate-600 border-slate-200',
    },
  }
  return (
    map[s.review_status] ?? {
      label: s.review_status,
      classes: 'bg-slate-100 text-slate-600 border-slate-200',
    }
  )
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-for="s in suggestions"
      :key="s.id"
      class="rounded-2xl bg-white border border-slate-200 shadow-lg hover:shadow-xl transition-all duration-200"
    >
      <div class="p-6">
        <!-- Header row -->
        <div class="flex items-start justify-between gap-3 mb-5">
          <div class="flex items-center gap-3">
            <div
              class="flex h-10 w-10 items-center justify-center rounded-xl"
              :class="
                isReviewed(s) ? 'bg-white shadow-sm border border-slate-200' : 'bg-primary-50'
              "
            >
              <component
                :is="iconFor(s)"
                class="h-5 w-5"
                :class="isReviewed(s) ? 'text-slate-500' : 'text-primary-600'"
                aria-hidden="true"
              />
            </div>
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
              {{ labelFor(s) }}
            </p>
          </div>

          <!-- Decision badge -->
          <span
            v-if="isReviewed(s) && !readonly"
            class="shrink-0 inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold"
            :class="decisionBadge(s).classes"
          >
            {{ decisionBadge(s).label }}
          </span>
          <span
            v-else-if="!isReviewed(s) && !readonly"
            class="shrink-0 inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-600"
          >
            Pending
          </span>
        </div>

        <!-- Comparison panels -->
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
              <FileText class="h-4 w-4 text-slate-400" aria-hidden="true" />
              <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400"
                >Current</span
              >
            </div>
            <p
              class="text-sm"
              :class="
                currentDisplay(s) === 'Not provided' ? 'text-slate-400 italic' : 'text-slate-700'
              "
            >
              {{ currentDisplay(s) }}
            </p>
          </div>
          <div
            class="rounded-xl border-2 border-primary-100 bg-gradient-to-br from-primary-50 to-white p-4 shadow-sm"
          >
            <div class="flex items-center gap-2 mb-2">
              <Check class="h-4 w-4 text-primary-500" aria-hidden="true" />
              <span class="text-[11px] font-bold uppercase tracking-wider text-primary-500"
                >From CV</span
              >
            </div>
            <p class="text-sm font-semibold text-slate-800">
              {{ suggestedDisplay(s) }}
            </p>
          </div>
        </div>

        <!-- Source text -->
        <p
          v-if="s.source_text && !isMatch(s) && isReviewed(s)"
          class="mt-4 pl-3 border-l-2 border-slate-200 text-xs text-slate-400 italic"
        >
          Source: &ldquo;{{ s.source_text.substring(0, 120)
          }}{{ s.source_text.length > 120 ? '...' : '' }}&rdquo;
        </p>
      </div>

      <!-- Action panel -->
      <div
        v-if="!readonly && !isReviewed(s)"
        class="border-t border-slate-200/50 bg-slate-50/80 rounded-b-2xl px-6 py-4"
      >
        <div class="flex flex-wrap gap-2">
          <Button
            size="sm"
            class="shadow-md"
            @click="emit('decision', s.id, { decision: 'accepted' })"
          >
            <Check class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Use CV value
          </Button>
          <Button
            size="sm"
            variant="outline"
            class="shadow-sm border-slate-300"
            @click="emit('decision', s.id, { decision: 'keep_existing' })"
          >
            <FileText class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Keep current
          </Button>
          <Button
            size="sm"
            variant="ghost"
            class="hover:bg-red-50 hover:text-red-700 text-red-600"
            @click="emit('decision', s.id, { decision: 'rejected' })"
          >
            <X class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Skip
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
