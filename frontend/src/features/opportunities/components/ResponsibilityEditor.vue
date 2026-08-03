<script setup lang="ts">
import type { JobSuggestion } from '../types'

defineProps<{
  suggestion: JobSuggestion
}>()

const emit = defineEmits<{
  keep: [suggestionId: number]
  remove: [suggestionId: number]
}>()

function extractText(ev: Record<string, unknown>): string {
  return String(ev.text ?? '')
}
</script>

<template>
  <div class="flex items-start justify-between rounded-lg border border-gray-200 p-3">
    <p class="text-sm">{{ extractText(suggestion.extracted_value) }}</p>
    <div class="flex gap-1 flex-shrink-0 ml-2">
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
