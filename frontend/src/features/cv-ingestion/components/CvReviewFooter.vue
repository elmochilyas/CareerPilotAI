<script setup lang="ts">
import { ArrowLeft, ArrowRight, CheckCircle2 } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'

defineProps<{
  reviewed: number
  total: number
  conflicts: number
  isFirstStep: boolean
  isLastStep: boolean
  isFinalStep: boolean
  applyDisabledReason: string | null
  applyPending: boolean
  savePending: boolean
  showSaveAndContinue: boolean
  readonly?: boolean
}>()

const emit = defineEmits<{
  previous: []
  next: []
  saveAndContinue: []
  apply: []
}>()
</script>

<template>
  <div
    class="sticky bottom-0 z-10 rounded-t-lg border-t border-slate-200 bg-white/95 backdrop-blur-sm px-4 py-3 sm:px-6"
  >
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div aria-live="polite" class="text-xs text-slate-500">
        <template v-if="total > 0">
          <span class="font-medium text-slate-700">{{ reviewed }} / {{ total }}</span>
          <span class="mx-1">reviewed</span>
          <span v-if="conflicts > 0" class="text-amber-600">
            &middot; {{ conflicts }} conflict{{ conflicts > 1 ? 's' : '' }} remaining
          </span>
          <span v-else-if="reviewed === total" class="text-emerald-600">
            &middot; All reviewed
          </span>
        </template>
        <span v-else class="text-slate-400">No items to review</span>
      </div>

      <div class="flex items-center gap-2">
        <Button v-if="!isFirstStep" variant="ghost" size="sm" @click="emit('previous')">
          <ArrowLeft class="mr-1 h-3.5 w-3.5" aria-hidden="true" />
          Previous
        </Button>

        <!-- Save and continue for skills step with unsaved changes -->
        <Button
          v-if="showSaveAndContinue && !readonly"
          size="sm"
          :loading="savePending"
          :disabled="savePending"
          @click="emit('saveAndContinue')"
        >
          <template v-if="savePending"> Saving... </template>
          <template v-else>
            Save and continue
            <ArrowRight class="ml-1 h-3.5 w-3.5" aria-hidden="true" />
          </template>
        </Button>

        <!-- Next (non-final steps) -->
        <Button
          v-else-if="!isLastStep && !readonly"
          variant="secondary"
          size="sm"
          :disabled="isFinalStep"
          @click="emit('next')"
        >
          Continue
          <ArrowRight class="ml-1 h-3.5 w-3.5" aria-hidden="true" />
        </Button>

        <!-- Apply (final step) -->
        <div v-if="isLastStep && !readonly" class="flex flex-col items-end gap-1">
          <Button
            size="sm"
            :disabled="applyDisabledReason !== null"
            :loading="applyPending"
            @click="emit('apply')"
          >
            <CheckCircle2 class="mr-1.5 h-4 w-4" aria-hidden="true" />
            {{ applyPending ? 'Applying...' : 'Apply to profile' }}
          </Button>
          <p v-if="applyDisabledReason" class="text-xs text-amber-600 max-w-[280px] text-right">
            {{ applyDisabledReason }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
