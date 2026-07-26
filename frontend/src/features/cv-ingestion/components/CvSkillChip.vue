<script setup lang="ts">
import { Check, X } from '@lucide/vue'
import type { CvSuggestion } from '../types'

defineProps<{
  suggestion: CvSuggestion<'skill'>
  removed?: boolean
  existing?: boolean
  archived?: boolean
  accepted?: boolean
  readonly?: boolean
}>()

const emit = defineEmits<{
  remove: [suggestionId: number]
  undo: [suggestionId: number]
}>()
</script>

<template>
  <div
    :class="[
      'group inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-sm transition-all',
      removed
        ? 'border-red-200 bg-red-50 text-red-400 line-through'
        : accepted
          ? 'border-green-300 bg-green-50 text-green-800'
          : existing
            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
            : archived
              ? 'border-amber-200 bg-amber-50 text-amber-700'
              : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:shadow-sm',
    ]"
  >
    <span class="max-w-[140px] truncate">
      {{ suggestion.suggested_value.name }}
    </span>
    <span
      v-if="accepted && !removed"
      class="ml-0.5 inline-flex items-center gap-0.5 rounded bg-green-100/60 px-1 text-[10px] font-medium text-green-600"
    >
      <Check class="h-3 w-3" aria-hidden="true" />
      Accepted
    </span>
    <span
      v-else-if="existing && !removed"
      class="ml-0.5 rounded bg-emerald-100/60 px-1 text-[10px] font-medium text-emerald-600"
    >
      In profile
    </span>
    <span
      v-else-if="archived && !removed"
      class="ml-0.5 rounded bg-amber-100/60 px-1 text-[10px] font-medium text-amber-600"
    >
      Archived
    </span>
    <button
      v-if="!readonly && !removed && !accepted"
      :aria-label="`Remove ${suggestion.suggested_value.name} from imported skills`"
      class="-mr-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full text-slate-400 hover:bg-red-100 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40 min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0"
      @click="emit('remove', suggestion.id)"
    >
      <X class="h-3.5 w-3.5" aria-hidden="true" />
    </button>
    <button
      v-if="removed"
      class="text-xs font-medium text-red-600 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded px-1"
      @click="emit('undo', suggestion.id)"
    >
      Undo
    </button>
  </div>
</template>
