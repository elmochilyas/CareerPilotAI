<script setup lang="ts">
import { AlertCircle, ExternalLink, Upload } from '@lucide/vue'
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
    <template v-if="isSuccess(batch)">
      <!-- Success -->
      <div class="flex flex-col items-center py-4 text-center">
        <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50">
          <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle
              cx="12"
              cy="12"
              r="11"
              stroke="#10b981"
              stroke-width="2"
              class="animate-[draw-circle_0.6s_ease-out_forwards]"
              stroke-dasharray="69.1"
              stroke-dashoffset="69.1"
            />
            <path
              d="M7 12.5l3 3 7-7"
              stroke="#10b981"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-linejoin="round"
              class="animate-[draw-check_0.4s_0.4s_ease-out_forwards]"
              stroke-dasharray="17"
              stroke-dashoffset="17"
            />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-slate-900">CV imported successfully!</h2>
        <p class="mt-1.5 text-sm text-slate-500">
          Your profile has been updated with the extracted information.
        </p>
      </div>

      <!-- Summary grid -->
      <div v-if="batch?.summary" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-500">
          Import summary
        </h3>
        <div class="grid grid-cols-3 gap-4">
          <div class="rounded-lg bg-emerald-50/50 p-3">
            <p class="text-2xl font-bold text-emerald-700">
              {{ batch.summary.total_accepted ?? 0 }}
            </p>
            <p class="text-xs text-emerald-600">Added or updated</p>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="text-2xl font-bold text-slate-700">{{ batch.summary.skipped ?? 0 }}</p>
            <p class="text-xs text-slate-500">Skipped</p>
          </div>
          <div v-if="(batch.summary.errors ?? 0) > 0" class="rounded-lg bg-red-50 p-3">
            <p class="text-2xl font-bold text-red-700">{{ batch.summary.errors }}</p>
            <p class="text-xs text-red-600">Errors</p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-center gap-3 pt-2">
        <Button variant="outline" @click="emit('uploadAnother')">
          <Upload class="mr-1.5 h-4 w-4" aria-hidden="true" /> Import another CV
        </Button>
        <Button @click="emit('goToProfile')">
          <ExternalLink class="mr-1.5 h-4 w-4" aria-hidden="true" /> View profile
        </Button>
      </div>
    </template>

    <template v-else>
      <!-- Failure -->
      <div class="flex flex-col items-center py-4 text-center">
        <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-red-50">
          <AlertCircle class="h-8 w-8 text-red-600" aria-hidden="true" />
        </div>
        <h2 class="text-xl font-bold text-slate-900">Import failed</h2>
        <p class="mt-1.5 text-sm text-slate-500">
          {{ batch?.failure_reason ?? 'An error occurred during import.' }}
        </p>
      </div>

      <!-- Actions -->
      <div class="flex justify-center gap-3 pt-2">
        <Button variant="outline" @click="emit('uploadAnother')">
          <Upload class="mr-1.5 h-4 w-4" aria-hidden="true" /> Try again
        </Button>
        <Button @click="emit('goToProfile')">
          <ExternalLink class="mr-1.5 h-4 w-4" aria-hidden="true" /> View profile
        </Button>
      </div>
    </template>
  </div>
</template>

<style scoped>
@keyframes draw-circle {
  to {
    stroke-dashoffset: 0;
  }
}
@keyframes draw-check {
  to {
    stroke-dashoffset: 0;
  }
}
</style>
