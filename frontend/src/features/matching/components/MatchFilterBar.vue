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
  { value: 'gap', label: 'Gaps' },
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
  <div
    class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-[var(--radius-xl)] bg-[var(--surface-secondary)] px-4 py-3 shadow-[var(--shadow-neo-inset)]"
  >
    <div role="group" aria-label="Filter by result" class="flex flex-wrap gap-1.5">
      <button
        v-for="option in options"
        :key="option.value"
        type="button"
        :aria-pressed="matchFilter === option.value"
        class="rounded-full px-3 py-1 text-xs font-medium focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:ring-offset-1 focus-visible:outline-none"
        :class="
          matchFilter === option.value
            ? 'bg-[var(--color-primary-600)] text-white shadow-[var(--shadow-neo-button)]'
            : 'bg-[var(--surface-primary)] text-[var(--text-secondary)] shadow-[var(--shadow-neo-raised-sm)] hover:bg-[var(--surface-secondary)]'
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
        class="flex cursor-pointer list-none items-center gap-1 rounded-[var(--radius-md)] bg-[var(--surface-primary)] px-2.5 py-1 text-xs font-medium text-[var(--text-secondary)] shadow-[var(--shadow-neo-raised-sm)] hover:bg-[var(--surface-secondary)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:ring-offset-1 focus-visible:outline-none"
      >
        More filters
        <ChevronDown
          class="size-3.5 text-slate-400 transition-transform group-open:rotate-180"
          aria-hidden="true"
        />
      </summary>
      <div
        class="absolute right-0 z-10 mt-2 w-48 rounded-[var(--radius-lg)] bg-[var(--surface-primary)] p-4 shadow-[var(--shadow-neo-raised-lg)]"
      >
        <fieldset>
          <legend class="text-xs font-medium text-[var(--text-secondary)]">Importance</legend>
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
        <div class="mt-3 border-t border-[var(--border-subtle)] pt-3">
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
