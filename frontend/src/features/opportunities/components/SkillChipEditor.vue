<script setup lang="ts">
import type { JobSuggestion } from '../types'

defineProps<{
  suggestion: JobSuggestion
  readonly?: boolean
}>()

const emit = defineEmits<{
  keep: [suggestionId: number]
  remove: [suggestionId: number]
}>()

function extractLabel(ev: Record<string, unknown>): unknown {
  return ev.label
}
</script>

<template>
  <div class="flex items-center justify-between rounded-lg border border-gray-200 p-2">
    <div class="flex items-center gap-2">
      <span class="text-sm">{{ extractLabel(suggestion.extracted_value) }}</span>
      <span
        v-if="suggestion.resolution === 'ambiguous'"
        class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700"
      >
        Resolve
      </span>
      <span
        v-else-if="suggestion.resolution"
        class="rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-700"
      >
        {{ suggestion.resolution }}
      </span>
    </div>
    <div v-if="!readonly" class="flex gap-1">
      <button
        class="rounded px-2 py-1 text-xs hover:bg-gray-100"
        :class="
          suggestion.review_decision === 'accepted'
            ? 'bg-green-100 text-green-700'
            : 'text-gray-500'
        "
        @click="emit('keep', suggestion.id)"
      >
        Keep
      </button>
      <button
        class="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
        @click="emit('remove', suggestion.id)"
      >
        Remove
      </button>
    </div>
  </div>
</template>
