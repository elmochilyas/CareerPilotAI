<script setup lang="ts">
import { ref } from 'vue'
import { Edit3, X, ExternalLink, Check, Link2, Globe, Code2, type LucideIcon } from '@lucide/vue'
import type { CvSuggestion, SocialLinkValue, ReviewDecision } from '../types'
import Button from '@/components/ui/Button.vue'

defineProps<{
  suggestions: CvSuggestion[]
  readonly?: boolean
}>()

const emit = defineEmits<{
  decision: [suggestionId: number, decision: ReviewDecision]
}>()

const editingId = ref<number | null>(null)
const editUrl = ref('')

const PLATFORM_META: Record<string, { label: string; icon: LucideIcon; brandClass: string }> = {
  linkedin: { label: 'LinkedIn', icon: Globe, brandClass: 'bg-[#0A66C2] text-white' },
  github: { label: 'GitHub', icon: Code2, brandClass: 'bg-[#24292F] text-white' },
  portfolio: { label: 'Portfolio', icon: Globe, brandClass: 'bg-primary-500 text-white' },
  other: { label: 'Other', icon: Link2, brandClass: 'bg-slate-500 text-white' },
}

function val(s: CvSuggestion<'social_link'>): SocialLinkValue {
  return s.suggested_value as SocialLinkValue
}

function meta(s: CvSuggestion<'social_link'>): {
  label: string
  icon: LucideIcon
  brandClass: string
} {
  return PLATFORM_META[val(s).type] ?? PLATFORM_META.other!
}

function isReviewed(s: CvSuggestion): boolean {
  return s.review_status !== 'pending'
}

function startEdit(s: CvSuggestion<'social_link'>): void {
  editingId.value = s.id
  editUrl.value = val(s).url
}

function saveEdit(s: CvSuggestion<'social_link'>): void {
  emit('decision', s.id, {
    decision: 'edited',
    edited_value: { type: val(s).type, url: editUrl.value },
  })
  editingId.value = null
}

function cancelEdit(): void {
  editingId.value = null
}

function decisionLabel(s: CvSuggestion): string {
  const map: Record<string, string> = {
    accepted: 'Accepted',
    edited: 'Edited',
    rejected: 'Skipped',
    keep_existing: 'Kept existing',
  }
  return map[s.review_status] ?? s.review_status
}

const decisionBadgeClasses = (s: CvSuggestion): string => {
  const map: Record<string, string> = {
    accepted: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    edited: 'bg-blue-100 text-blue-700 border-blue-200',
    rejected: 'bg-red-100 text-red-600 border-red-200',
    keep_existing: 'bg-slate-100 text-slate-600 border-slate-200',
  }
  return map[s.review_status] ?? 'bg-slate-100 text-slate-600 border-slate-200'
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-for="s in suggestions as CvSuggestion<'social_link'>[]"
      :key="s.id"
      class="rounded-2xl bg-white border border-slate-200 shadow-lg hover:shadow-xl transition-all duration-200"
    >
      <div class="p-6">
        <!-- Header row -->
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold shadow-sm"
              :class="meta(s).brandClass"
              :aria-label="meta(s).label"
            >
              <component :is="meta(s).icon" class="h-5 w-5" aria-hidden="true" />
            </div>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-slate-800">{{ meta(s).label }}</p>
              <a
                v-if="!editingId || editingId !== s.id"
                :href="val(s).url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 text-xs text-primary-600 hover:text-primary-700 truncate max-w-[280px]"
              >
                <span class="truncate">{{ val(s).url }}</span>
                <ExternalLink class="h-3 w-3 shrink-0" aria-hidden="true" />
              </a>
              <div v-else class="flex items-center gap-2 mt-1">
                <input
                  v-model="editUrl"
                  type="url"
                  class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 shadow-sm"
                  aria-label="Edit URL"
                />
                <Button size="sm" @click="saveEdit(s)">Save</Button>
                <Button size="sm" variant="ghost" @click="cancelEdit()">Cancel</Button>
              </div>
            </div>
          </div>

          <span
            v-if="isReviewed(s) && !readonly && editingId !== s.id"
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
      </div>

      <!-- Action panel -->
      <div
        v-if="!readonly && !isReviewed(s) && editingId !== s.id"
        class="border-t border-slate-200/50 bg-slate-50/80 rounded-b-2xl px-6 py-4"
      >
        <div class="flex flex-wrap gap-2">
          <Button
            size="sm"
            class="shadow-md"
            @click="emit('decision', s.id, { decision: 'accepted' })"
          >
            <Check class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Keep
          </Button>
          <Button
            size="sm"
            variant="outline"
            class="shadow-sm border-slate-300"
            @click="startEdit(s)"
          >
            <Edit3 class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Edit
          </Button>
          <Button
            size="sm"
            variant="ghost"
            class="hover:bg-red-50 hover:text-red-700 text-red-600"
            @click="emit('decision', s.id, { decision: 'rejected' })"
          >
            <X class="mr-1.5 h-3.5 w-3.5" aria-hidden="true" /> Remove
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
