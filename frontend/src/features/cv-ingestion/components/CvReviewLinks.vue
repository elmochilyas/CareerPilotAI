<script setup lang="ts">
import { ref } from 'vue'
import { Edit3, X, ExternalLink } from '@lucide/vue'
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

const PLATFORM_META: Record<string, { label: string; icon: unknown }> = {
  linkedin: { label: 'LinkedIn', icon: 'in' },
  github: { label: 'GitHub', icon: 'gh' },
  portfolio: { label: 'Portfolio', icon: 'pf' },
  other: { label: 'Other', icon: 'ot' },
}

function val(s: CvSuggestion<'social_link'>): SocialLinkValue {
  return s.suggested_value as SocialLinkValue
}

function meta(s: CvSuggestion<'social_link'>): { label: string; icon: unknown } {
  return (PLATFORM_META[val(s).type] ?? PLATFORM_META.other) as { label: string; icon: unknown }
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
    <p class="text-sm text-slate-500">Review the professional links extracted from your CV.</p>

    <div class="space-y-3">
      <div
        v-for="s in suggestions as CvSuggestion<'social_link'>[]"
        :key="s.id"
        :class="[
          'rounded-lg border p-4 transition-colors',
          isReviewed(s) ? 'border-green-200 bg-green-50/50' : 'border-slate-200 bg-white',
        ]"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <span
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 uppercase"
              :aria-label="meta(s).label"
            >
              {{ meta(s).icon }}
            </span>
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800">{{ meta(s).label }}</p>
              <a
                v-if="!editingId || editingId !== s.id"
                :href="val(s).url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 text-xs text-primary-600 hover:text-primary-700 truncate max-w-[300px]"
              >
                {{ val(s).url }}
                <ExternalLink class="h-3 w-3 shrink-0" aria-hidden="true" />
              </a>
              <div v-else class="flex items-center gap-2">
                <input
                  v-model="editUrl"
                  type="url"
                  class="w-full max-w-xs rounded border border-slate-300 px-2 py-1 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                  aria-label="Edit URL"
                />
                <Button size="sm" @click="saveEdit(s)">Save</Button>
                <Button size="sm" variant="ghost" @click="cancelEdit()">Cancel</Button>
              </div>
            </div>
          </div>

          <span
            v-if="isReviewed(s) && !readonly && editingId !== s.id"
            class="shrink-0 rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
          >
            {{ decisionLabel(s) }}
          </span>
        </div>

        <div
          v-if="!readonly && !isReviewed(s) && editingId !== s.id"
          class="mt-3 flex flex-wrap gap-2"
        >
          <Button size="sm" @click="emit('decision', s.id, { decision: 'accepted' })">
            Keep
          </Button>
          <Button size="sm" variant="outline" @click="startEdit(s)">
            <Edit3 class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Edit
          </Button>
          <Button
            size="sm"
            variant="ghost"
            class="text-red-600 hover:text-red-700"
            @click="emit('decision', s.id, { decision: 'rejected' })"
          >
            <X class="mr-1 h-3.5 w-3.5" aria-hidden="true" /> Remove
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
