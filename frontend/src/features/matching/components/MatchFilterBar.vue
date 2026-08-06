<script setup lang="ts">
import { computed } from 'vue'
import { ChevronDown } from '@lucide/vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import type { MatchFinding, MatchImportance } from '../types'

export type MatchFilterValue = 'all' | 'matched' | 'gap'

const props = defineProps<{
  matchFilter: MatchFilterValue
  importance: MatchImportance[]
  includeUnknown: boolean
  findings?: MatchFinding[]
}>()

const emit = defineEmits<{
  'update:matchFilter': [value: MatchFilterValue]
  'update:importance': [value: MatchImportance[]]
  'update:includeUnknown': [value: boolean]
}>()

const options: Array<{ value: MatchFilterValue; label: string }> = [
  { value: 'all', label: 'All' },
  { value: 'matched', label: 'Matches' },
  { value: 'gap', label: 'Needs attention' },
]

const stateCounts = computed<Record<string, number>>(() => {
  const counts: Record<string, number> = {}
  for (const finding of props.findings ?? []) {
    counts[finding.match_state] = (counts[finding.match_state] ?? 0) + 1
  }
  return counts
})

function countOf(state: MatchFilterValue): number | undefined {
  if (props.findings === undefined || state === 'all') return undefined
  return stateCounts.value[state] ?? 0
}

function toggleImportance(value: MatchImportance, checked: boolean): void {
  const next = new Set(props.importance)
  if (checked) next.add(value)
  else next.delete(value)
  emit('update:importance', [...next])
}

const requiredChecked = computed(() => props.importance.includes('required'))
const preferredChecked = computed(() => props.importance.includes('preferred'))
</script>

<template>
  <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
    <div role="group" aria-label="Filter by result" class="flex flex-wrap gap-1.5">
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        :aria-pressed="matchFilter === option.value"
        class="rounded-full border px-3 py-1 text-xs font-medium focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 focus-visible:outline-none"
        :class="
          matchFilter === option.value
            ? 'border-primary-600 bg-primary-600 text-white ring-1 ring-primary-600'
            : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'
        "
        @click="emit('update:matchFilter', option.value)"
      >
        {{ option.label
        }}<template v-if="countOf(option.value) !== undefined">
          <span class="ml-1 tabular-nums opacity-70">{{ countOf(option.value) }}</span>
        </template>
      </button>
    </div>

    <details class="group relative">
      <summary
        class="flex cursor-pointer list-none items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 focus-visible:outline-none"
      >
        More filters
        <ChevronDown
          class="size-3.5 text-slate-400 transition-transform group-open:rotate-180"
          aria-hidden="true"
        />
      </summary>
      <div
        class="absolute right-0 z-10 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-4 shadow-lg"
      >
        <fieldset>
          <legend class="text-xs font-medium text-slate-500">Importance</legend>
          <div class="mt-2 space-y-2.5">
            <Checkbox
              id="filter-required"
              label="Required"
              :model-value="requiredChecked"
              @update:model-value="(value) => toggleImportance('required', value)"
            />
            <Checkbox
              id="filter-preferred"
              label="Preferred"
              :model-value="preferredChecked"
              @update:model-value="(value) => toggleImportance('preferred', value)"
            />
          </div>
        </fieldset>
        <div class="mt-3 border-t border-slate-100 pt-3">
          <Checkbox
            id="filter-unknown"
            label="Unknown"
            :model-value="includeUnknown"
            @update:model-value="(value) => emit('update:includeUnknown', value)"
          />
        </div>
      </div>
    </details>
  </div>
</template>
