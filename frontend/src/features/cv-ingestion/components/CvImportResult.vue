<script setup lang="ts">
import { CheckCircle2, AlertCircle, ExternalLink, Upload } from '@lucide/vue'
import type { CvImportBatch } from '../types'
import Button from '@/components/ui/Button.vue'

defineProps<{
  batch: CvImportBatch | null
}>()

const emit = defineEmits<{
  uploadAnother: []
  goToProfile: []
}>()

const isSuccess = (batch: CvImportBatch | null): boolean => {
  if (!batch) return false
  return batch.status === 'applied'
}
</script>

<template>
  <div class="space-y-6">
    <div v-if="isSuccess(batch)" class="space-y-4">
      <div class="rounded-lg border border-green-200 bg-green-50 p-6 text-center">
        <CheckCircle2 class="mx-auto h-12 w-12 text-green-600" aria-hidden="true" />
        <h2 class="mt-3 text-lg font-semibold text-green-800">CV imported successfully!</h2>
        <p class="mt-1 text-sm text-green-700">
          Your profile has been updated with the extracted information.
        </p>
      </div>

      <div v-if="batch?.summary" class="rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-sm font-medium text-slate-700">Import summary</h3>
        <div class="grid grid-cols-3 gap-3">
          <div class="text-center">
            <p class="text-xl font-bold text-slate-900">{{ batch.summary.total_accepted ?? 0 }}</p>
            <p class="text-xs text-slate-500">Items added or updated</p>
          </div>
          <div class="text-center">
            <p class="text-xl font-bold text-slate-900">{{ batch.summary.skipped ?? 0 }}</p>
            <p class="text-xs text-slate-500">Skipped</p>
          </div>
          <div v-if="(batch.summary.errors ?? 0) > 0" class="text-center">
            <p class="text-xl font-bold text-red-700">{{ batch.summary.errors }}</p>
            <p class="text-xs text-red-600">Errors</p>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="space-y-4">
      <div class="rounded-lg border border-red-200 bg-red-50 p-6 text-center">
        <AlertCircle class="mx-auto h-12 w-12 text-red-600" aria-hidden="true" />
        <h2 class="mt-3 text-lg font-semibold text-red-800">Import failed</h2>
        <p class="mt-1 text-sm text-red-700">
          {{ batch?.failure_reason ?? 'An error occurred during import.' }}
        </p>
      </div>
    </div>

    <div class="flex justify-center gap-3">
      <Button variant="outline" @click="emit('uploadAnother')">
        <Upload class="mr-1 h-4 w-4" aria-hidden="true" /> Import another CV
      </Button>
      <Button @click="emit('goToProfile')">
        <ExternalLink class="mr-1 h-4 w-4" aria-hidden="true" /> View profile
      </Button>
    </div>
  </div>
</template>
