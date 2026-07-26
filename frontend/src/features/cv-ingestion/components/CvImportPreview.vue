<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, CheckCircle2, Loader2, ArrowRight } from '@lucide/vue'
import type { ImportPreview } from '../types'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  preview: ImportPreview | null
  isPending: boolean
}>()

const emit = defineEmits<{
  confirm: []
  goBack: []
}>()

const hasConflicts = computed(() => {
  return (props.preview?.conflicts.length ?? 0) > 0
})
</script>

<template>
  <div class="space-y-6">
    <h2 class="text-lg font-semibold text-slate-900">Import preview</h2>

    <div v-if="isPending" class="flex items-center justify-center py-12">
      <Loader2 class="h-6 w-6 animate-spin text-blue-600" aria-hidden="true" />
      <span class="ml-2 text-sm text-slate-500">Preparing preview...</span>
    </div>

    <div v-else-if="!preview" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
      <p class="text-sm text-amber-800">
        Unable to load import preview. Please go back and try again.
      </p>
    </div>

    <div v-else class="space-y-4">
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-3 text-center">
          <p class="text-2xl font-bold text-slate-900">{{ preview.summary.total }}</p>
          <p class="text-xs text-slate-500">Total</p>
        </div>
        <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-center">
          <p class="text-2xl font-bold text-green-700">{{ preview.summary.accepted }}</p>
          <p class="text-xs text-green-600">Accepted</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-3 text-center">
          <p class="text-2xl font-bold text-slate-900">{{ preview.summary.keep_existing }}</p>
          <p class="text-xs text-slate-500">Keep existing</p>
        </div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-center">
          <p class="text-2xl font-bold text-red-700">{{ preview.summary.rejected }}</p>
          <p class="text-xs text-red-600">Rejected</p>
        </div>
      </div>

      <div v-if="hasConflicts" class="space-y-2">
        <div class="flex items-center gap-2 text-amber-700">
          <AlertCircle class="h-4 w-4" aria-hidden="true" />
          <span class="text-sm font-medium">Conflicts detected</span>
        </div>
        <div
          v-for="(conflict, i) in preview.conflicts"
          :key="i"
          class="rounded-lg border border-amber-200 bg-amber-50 p-3"
        >
          <p class="text-sm font-medium text-amber-800">{{ conflict.message }}</p>
        </div>
      </div>

      <div v-if="!hasConflicts" class="rounded-lg border border-green-200 bg-green-50 p-4">
        <div class="flex items-start gap-2">
          <CheckCircle2 class="mt-0.5 h-5 w-5 text-green-600" aria-hidden="true" />
          <div>
            <p class="text-sm font-medium text-green-800">No conflicts detected</p>
            <p class="text-sm text-green-700">
              Your profile is ready to be updated with the accepted changes.
            </p>
          </div>
        </div>
      </div>

      <div class="flex justify-between">
        <Button variant="outline" @click="emit('goBack')"> Back to review </Button>
        <Button :disabled="isPending" @click="emit('confirm')">
          <ArrowRight class="mr-1 h-4 w-4" aria-hidden="true" /> Confirm and import
        </Button>
      </div>
    </div>
  </div>
</template>
