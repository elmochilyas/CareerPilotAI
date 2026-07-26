<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue'
import type { CvSuggestion } from '../types'
import Button from '@/components/ui/Button.vue'

defineProps<{
  suggestion: CvSuggestion
}>()

const emit = defineEmits<{
  decision: [suggestionId: number, decision: { decision: 'rejected' }]
}>()
</script>

<template>
  <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-4">
    <div class="flex items-start gap-3">
      <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
      <div class="flex-1">
        <p class="text-sm font-medium text-amber-800">This extracted item cannot be reviewed yet</p>
        <p class="mt-1 text-xs text-amber-700">
          Category: {{ suggestion.type.replace(/_/g, ' ') }}
        </p>
        <p v-if="suggestion.category" class="text-xs text-amber-600">
          {{ suggestion.category }}
        </p>
        <div class="mt-3">
          <Button
            size="sm"
            variant="outline"
            class="text-amber-700 border-amber-300 hover:bg-amber-100"
            @click="emit('decision', suggestion.id, { decision: 'rejected' })"
          >
            Ignore this item
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
