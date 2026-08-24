<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, CheckCircle2, Loader2, ArrowRight } from '@lucide/vue'
import type { ImportPreview } from '../types'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  preview: ImportPreview | null
  isPending: boolean
  isApplying?: boolean
}>()

const emit = defineEmits<{
  confirm: []
  goBack: []
}>()

const hasConflicts = computed(() => {
  return (props.preview?.conflicts.length ?? 0) > 0
})

const duplicateConflicts = computed(() => {
  return (props.preview?.conflicts ?? []).filter((c) => c.type !== 'possible_duplicate')
})

const possibleDuplicates = computed(() => {
  return (props.preview?.conflicts ?? []).filter((c) => c.type === 'possible_duplicate')
})

const totalBarPercent = computed(() => {
  if (!props.preview) return { accepted: 0, keepExisting: 0, rejected: 0 }
  const t = props.preview.summary.total
  if (t === 0) return { accepted: 0, keepExisting: 0, rejected: 0 }
  return {
    accepted: (props.preview.summary.accepted / t) * 100,
    keepExisting: (props.preview.summary.keep_existing / t) * 100,
    rejected: (props.preview.summary.rejected / t) * 100,
  }
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Import preview</h2>
        <p class="mt-0.5 text-sm text-slate-500">
          Review the summary before applying changes to your profile.
        </p>
      </div>
    </div>

    <div v-if="isPending" class="flex items-center justify-center py-12">
      <Loader2 class="h-6 w-6 animate-spin text-primary-600" aria-hidden="true" />
      <span class="ml-2 text-sm text-slate-500">Preparing preview...</span>
    </div>

    <div v-else-if="!preview" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
      <p class="text-sm text-amber-800">
        Unable to load import preview. Please go back and try again.
      </p>
    </div>

    <div v-else class="space-y-6">
      <!-- Visual summary -->
      <div class="space-y-4">
        <div class="grid grid-cols-3 gap-3">
          <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 text-center">
            <p class="text-2xl font-bold text-emerald-700">{{ preview.summary.accepted }}</p>
            <p class="mt-0.5 text-xs font-medium text-emerald-600">Accepted</p>
          </div>
          <div class="rounded-xl border border-slate-200 bg-white p-4 text-center">
            <p class="text-2xl font-bold text-slate-900">{{ preview.summary.keep_existing }}</p>
            <p class="mt-0.5 text-xs font-medium text-slate-500">Keep existing</p>
          </div>
          <div class="rounded-xl border border-red-200 bg-red-50/50 p-4 text-center">
            <p class="text-2xl font-bold text-red-700">{{ preview.summary.rejected }}</p>
            <p class="mt-0.5 text-xs font-medium text-red-600">Rejected</p>
          </div>
        </div>

        <!-- Visual bar -->
        <div
          class="flex h-3 overflow-hidden rounded-full bg-slate-100"
          role="img"
          aria-label="Import breakdown: {{ preview.summary.accepted }} accepted, {{ preview.summary.keep_existing }} keep existing, {{ preview.summary.rejected }} rejected"
        >
          <div
            v-if="preview.summary.accepted > 0"
            class="h-full bg-emerald-400 transition-all duration-700 ease-out"
            :style="{ width: totalBarPercent.accepted + '%' }"
          />
          <div
            v-if="preview.summary.keep_existing > 0"
            class="h-full bg-slate-300 transition-all duration-700 ease-out"
            :style="{ width: totalBarPercent.keepExisting + '%' }"
          />
          <div
            v-if="preview.summary.rejected > 0"
            class="h-full bg-red-300 transition-all duration-700 ease-out"
            :style="{ width: totalBarPercent.rejected + '%' }"
          />
        </div>

        <p class="text-center text-xs text-slate-400">
          {{ preview.summary.total }} item{{ preview.summary.total !== 1 ? 's' : '' }} in total
        </p>
      </div>

      <!-- Conflicts -->
      <div v-if="duplicateConflicts.length > 0" class="space-y-2">
        <div class="flex items-center gap-2 text-amber-700">
          <AlertCircle class="h-4 w-4" aria-hidden="true" />
          <span class="text-sm font-medium">Conflicts detected</span>
        </div>
        <div
          v-for="(conflict, i) in duplicateConflicts"
          :key="'dup-' + i"
          class="rounded-lg border border-amber-200 bg-amber-50 p-3"
        >
          <p class="text-sm font-medium text-amber-800">{{ conflict.message }}</p>
        </div>
      </div>

      <!-- Possible duplicates -->
      <div v-if="possibleDuplicates.length > 0" class="space-y-2">
        <div class="flex items-center gap-2 text-blue-700">
          <AlertCircle class="h-4 w-4" aria-hidden="true" />
          <span class="text-sm font-medium">Possible duplicates — review required</span>
        </div>
        <div
          v-for="(conflict, i) in possibleDuplicates"
          :key="'pos-' + i"
          class="rounded-lg border border-blue-200 bg-blue-50 p-3"
        >
          <p class="text-sm font-medium text-blue-800">{{ conflict.message }}</p>
          <p v-if="(conflict as Record<string, unknown>).reason" class="text-xs text-blue-600 mt-1">
            Reason: {{ (conflict as Record<string, unknown>).reason as string }}
            <span v-if="(conflict as Record<string, unknown>).similarity"> — {{ Math.round(((conflict as Record<string, unknown>).similarity as number) * 100) }}% similar</span>
          </p>
        </div>
      </div>

      <!-- No conflicts -->
      <div v-if="!hasConflicts" class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
        <div class="flex items-start gap-3">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100"
          >
            <CheckCircle2 class="h-5 w-5 text-emerald-600" aria-hidden="true" />
          </div>
          <div>
            <p class="text-sm font-semibold text-emerald-800">No conflicts detected</p>
            <p class="mt-0.5 text-sm text-emerald-700">
              Your profile is ready to be updated with the accepted changes.
            </p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-between border-t border-slate-100 pt-5">
        <Button variant="outline" @click="emit('goBack')"> Back to review </Button>
        <Button :disabled="isPending || isApplying" :loading="isApplying" @click="emit('confirm')">
          <ArrowRight class="mr-1 h-4 w-4" aria-hidden="true" />
          {{ isApplying ? 'Importing...' : 'Confirm and import' }}
        </Button>
      </div>
    </div>
  </div>
</template>
