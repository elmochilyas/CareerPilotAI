<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Check, X, Undo2, Globe } from '@lucide/vue'
import type { CvSuggestion, LanguageValue, ReviewDecision } from '../types'
import CvSkillChip from './CvSkillChip.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  suggestions: CvSuggestion[]
  readonly?: boolean
}>()

const emit = defineEmits<{
  decision: [suggestionId: number, decision: ReviewDecision]
  saveBatch: [decisions: Array<{ id: number; decision: ReviewDecision['decision'] }>]
  unsavedChange: [hasUnsaved: boolean]
}>()

// Track local pending removals (not yet saved)
const pendingRemovals = ref<Set<number>>(new Set())
// Track locally accepted via keep all (avoid re-emitting on rapid clicks + give instant visual feedback)
const pendingAcceptIds = ref<Set<number>>(new Set())
const undoToast = ref<{ suggestionId: number; name: string } | null>(null)
let undoTimeout: ReturnType<typeof setTimeout> | null = null

const skillSuggestions = computed(
  () => props.suggestions.filter((s) => s.type === 'skill') as CvSuggestion<'skill'>[],
)

const languageSuggestions = computed(
  () => props.suggestions.filter((s) => s.type === 'language') as CvSuggestion<'language'>[],
)

const groupedSkills = computed(() => {
  const groups: Record<string, CvSuggestion<'skill'>[]> = {}
  for (const s of skillSuggestions.value) {
    const cat = s.suggested_value.category ?? 'Uncategorized'
    if (!groups[cat]) groups[cat] = []
    groups[cat].push(s)
  }
  return groups
})

const categoryKeys = computed(() => Object.keys(groupedSkills.value).sort())

const hasUnsavedChanges = computed(() => pendingRemovals.value.size > 0)

watch(hasUnsavedChanges, (val) => {
  emit('unsavedChange', val)
})

// Clear optimistic accept IDs when server confirms the review_status changes
watch(
  () => props.suggestions,
  () => {
    if (pendingAcceptIds.value.size > 0) {
      pendingAcceptIds.value = new Set()
    }
  },
  { deep: false },
)

function removeSkill(id: number): void {
  const s = skillSuggestions.value.find((sk) => sk.id === id)
  if (!s) return
  pendingRemovals.value = new Set([...pendingRemovals.value, id])
  const nextAccepts = new Set(pendingAcceptIds.value)
  nextAccepts.delete(id)
  pendingAcceptIds.value = nextAccepts
  const name = s.suggested_value.name
  showUndo(id, name)
}

function undoRemove(id: number): void {
  const next = new Set(pendingRemovals.value)
  next.delete(id)
  pendingRemovals.value = next
  dismissUndo()
}

function showUndo(id: number, name: string): void {
  if (undoTimeout) clearTimeout(undoTimeout)
  undoToast.value = { suggestionId: id, name }
  undoTimeout = setTimeout(() => {
    dismissUndo()
  }, 6000)
}

function dismissUndo(): void {
  undoToast.value = null
  if (undoTimeout) {
    clearTimeout(undoTimeout)
    undoTimeout = null
  }
}

function keepAll(category: string): void {
  const skills = groupedSkills.value[category] ?? []
  const ids = skills.map((s) => s.id)
  const nextRemovals = new Set(pendingRemovals.value)
  const nextAccepts = new Set(pendingAcceptIds.value)
  for (const id of ids) {
    nextRemovals.delete(id)
  }
  pendingRemovals.value = nextRemovals
  dismissUndo()
  for (const s of skills) {
    if (s.review_status === 'pending' && !nextAccepts.has(s.id)) {
      nextAccepts.add(s.id)
      emit('decision', s.id, { decision: 'accepted' })
    }
  }
  pendingAcceptIds.value = nextAccepts
}

function removeAll(category: string): void {
  const ids = groupedSkills.value[category]?.map((s) => s.id) ?? []
  const nextRemovals = new Set([...pendingRemovals.value, ...ids])
  pendingRemovals.value = nextRemovals
  const nextAccepts = new Set(pendingAcceptIds.value)
  for (const id of ids) nextAccepts.delete(id)
  pendingAcceptIds.value = nextAccepts
  const names = groupedSkills.value[category]?.map((s) => s.suggested_value.name).join(', ')
  if (names) showUndo(-1, `All skills in ${category}`)
}

function isRemoved(id: number): boolean {
  return pendingRemovals.value.has(id)
}

function isAccepted(s: CvSuggestion<'skill'>): boolean {
  return pendingAcceptIds.value.has(s.id) || s.review_status === 'accepted'
}

function hasCategoryPending(category: string): boolean {
  return (groupedSkills.value[category] ?? []).some(
    (s) => s.review_status === 'pending' && !pendingAcceptIds.value.has(s.id),
  )
}

function isExisting(s: CvSuggestion<'skill'>): boolean {
  return (
    s.current_value !== null &&
    Object.keys(s.current_value).length > 0 &&
    Boolean(s.current_value?.name)
  )
}

function isArchived(s: CvSuggestion<'skill'>): boolean {
  return isExisting(s) && (s.current_value as Record<string, unknown>)?.state === 'archived'
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

function isLanguageReviewed(s: CvSuggestion<'language'>): boolean {
  return s.review_status !== 'pending'
}

function languageProficiencyLabel(p: string | null): string {
  if (!p) return 'Not specified'
  const labels: Record<string, string> = {
    native: 'Native',
    fluent: 'Fluent',
    advanced: 'Advanced',
    intermediate: 'Intermediate',
    basic: 'Basic',
  }
  return labels[p.toLowerCase()] ?? p
}
</script>

<template>
  <div class="space-y-6">
    <!-- Technical skills section -->
    <section aria-labelledby="skills-heading">
      <div class="flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
        <h3 id="skills-heading" class="text-sm font-semibold text-slate-700">Technical skills</h3>
        <span
          class="inline-flex items-center justify-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500"
        >
          {{ skillSuggestions.length }}
        </span>
      </div>

      <p class="text-sm text-slate-500 mb-5">
        Click <strong class="text-slate-700">&times;</strong> to exclude a skill. Removed skills can
        be restored before saving.
      </p>

      <div v-for="cat in categoryKeys" :key="cat" class="mb-5 last:mb-0">
        <div class="flex items-center justify-between mb-3">
          <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ cat }}</h4>
          <div
            v-if="(groupedSkills[cat]?.length ?? 0) > 1 && !readonly && hasCategoryPending(cat)"
            class="flex gap-2"
          >
            <button
              type="button"
              class="text-xs font-medium text-primary-600 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded px-1.5 py-0.5"
              @click="keepAll(cat)"
            >
              Keep all
            </button>
            <button
              type="button"
              class="text-xs font-medium text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded px-1.5 py-0.5"
              @click="removeAll(cat)"
            >
              Remove all
            </button>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <CvSkillChip
            v-for="s in groupedSkills[cat]"
            :key="s.id"
            :suggestion="s"
            :removed="isRemoved(s.id)"
            :accepted="isAccepted(s)"
            :existing="isExisting(s) && !isArchived(s)"
            :archived="isArchived(s)"
            :readonly="readonly"
            @remove="removeSkill(s.id)"
            @undo="undoRemove(s.id)"
          />
        </div>
      </div>

      <div
        v-if="categoryKeys.length === 0"
        class="rounded-lg border border-dashed border-slate-200 p-6 text-center"
      >
        <p class="text-sm text-slate-400 italic">No technical skills extracted from this CV.</p>
      </div>
    </section>

    <!-- Languages section -->
    <section v-if="languageSuggestions.length > 0" aria-labelledby="languages-heading">
      <div class="flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
        <Globe class="h-4 w-4 text-slate-500" aria-hidden="true" />
        <h3 id="languages-heading" class="text-sm font-semibold text-slate-700">Languages</h3>
        <span
          class="inline-flex items-center justify-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500"
        >
          {{ languageSuggestions.length }}
        </span>
      </div>

      <div class="space-y-2">
        <div
          v-for="s in languageSuggestions"
          :key="s.id"
          :class="[
            'flex items-center justify-between gap-3 rounded-xl border p-4 transition-all duration-200',
            isLanguageReviewed(s)
              ? 'border-emerald-200 bg-emerald-50/50'
              : 'border-slate-200 bg-white shadow-sm hover:border-slate-300',
          ]"
        >
          <div class="flex items-center gap-3">
            <span class="font-semibold text-slate-800">{{
              (s.suggested_value as LanguageValue).language
            }}</span>
            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
              {{ languageProficiencyLabel((s.suggested_value as LanguageValue).proficiency) }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <span
              v-if="isLanguageReviewed(s) && !readonly"
              class="inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-medium bg-emerald-50 text-emerald-700 border-emerald-200"
            >
              {{ decisionLabel(s) }}
            </span>
            <div v-if="!readonly && !isLanguageReviewed(s)" class="flex gap-1.5">
              <Button size="sm" @click="emit('decision', s.id, { decision: 'accepted' })">
                <Check class="mr-0.5 h-3.5 w-3.5" aria-hidden="true" /> Keep
              </Button>
              <Button
                size="sm"
                variant="destructive-ghost"
                @click="emit('decision', s.id, { decision: 'rejected' })"
              >
                <X class="mr-0.5 h-3.5 w-3.5" aria-hidden="true" /> Remove
              </Button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Undo toast -->
    <div
      v-if="undoToast && !readonly"
      class="fixed bottom-24 left-1/2 z-30 -translate-x-1/2 rounded-xl border border-slate-200 bg-white px-5 py-3 shadow-xl"
      role="alert"
    >
      <div class="flex items-center gap-3">
        <p class="text-sm text-slate-700">
          <strong>{{ undoToast.name }}</strong> removed
        </p>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
          @click="undoRemove(undoToast.suggestionId)"
        >
          <Undo2 class="h-3.5 w-3.5" aria-hidden="true" /> Undo
        </button>
        <button
          type="button"
          class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 focus:outline-none"
          aria-label="Dismiss"
          @click="dismissUndo()"
        >
          <span class="text-lg leading-none">&times;</span>
        </button>
      </div>
    </div>
  </div>
</template>
