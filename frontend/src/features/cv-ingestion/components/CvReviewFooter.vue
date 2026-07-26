<script setup lang="ts">
import { computed } from 'vue'
import { ArrowLeft, ArrowRight, CheckCircle2, Save } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
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

const reviewedPercent = computed(() => {
  if (props.total === 0) return 0
  return Math.round((props.reviewed / props.total) * 100)
})

const allDone = computed(() => props.total > 0 && props.reviewed === props.total)
</script>

<template>
  <div
    class="sticky bottom-0 z-10 -mx-6 -mb-6 mt-6 rounded-b-2xl border-t border-slate-200 bg-white/90 px-8 py-5 backdrop-blur-xl shadow-[0_-4px_20px_rgba(0,0,0,0.04)]"
  >
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <!-- Review progress -->
      <div class="flex items-center gap-3" aria-live="polite">
        <div
          v-if="total > 0"
          class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
          :class="allDone ? 'bg-emerald-50' : 'bg-primary-50'"
        >
          <svg class="h-11 w-11 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
            <circle
              cx="18"
              cy="18"
              r="15.5"
              fill="none"
              class="stroke-slate-200"
              stroke-width="2.5"
            />
            <circle
              cx="18"
              cy="18"
              r="15.5"
              fill="none"
              :class="allDone ? 'stroke-emerald-500' : 'stroke-primary-500'"
              stroke-width="2.5"
              stroke-linecap="round"
              :stroke-dasharray="97.4"
              :stroke-dashoffset="97.4 - (97.4 * reviewedPercent) / 100"
              class="transition-all duration-700 ease-out"
            />
          </svg>
        </div>
        <div>
          <p class="text-sm text-slate-500">
            <template v-if="total > 0">
              <span class="font-bold text-slate-800">{{ reviewed }}</span>
              <span class="mx-1">/</span>
              <span class="font-medium">{{ total }}</span>
              <span class="ml-1">items reviewed</span>
              <span
                v-if="conflicts > 0"
                class="ml-2 inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700"
              >
                {{ conflicts }} conflict{{ conflicts > 1 ? 's' : '' }}
              </span>
              <span
                v-else-if="allDone"
                class="ml-2 inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
              >
                <CheckCircle2 class="h-3 w-3" aria-hidden="true" />
                All done
              </span>
            </template>
            <span v-else class="text-slate-400">No items to review</span>
          </p>
          <div
            v-if="total > 0"
            class="mt-1.5 h-1.5 w-28 overflow-hidden rounded-full bg-slate-100 shadow-inner"
          >
            <div
              class="h-full rounded-full transition-all duration-500 ease-out"
              :class="
                allDone
                  ? 'bg-gradient-to-r from-emerald-400 to-emerald-500'
                  : 'bg-gradient-to-r from-primary-400 to-primary-500'
              "
              :style="{ width: reviewedPercent + '%' }"
            />
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2">
        <Button
          v-if="!isFirstStep"
          variant="outline"
          size="sm"
          class="shadow-sm"
          @click="emit('previous')"
        >
          <ArrowLeft class="mr-1 h-3.5 w-3.5" aria-hidden="true" />
          Previous
        </Button>

        <Button
          v-if="showSaveAndContinue && !readonly"
          size="sm"
          class="shadow-sm"
          :loading="savePending"
          :disabled="savePending"
          @click="emit('saveAndContinue')"
        >
          <Save class="mr-1 h-3.5 w-3.5" aria-hidden="true" />
          {{ savePending ? 'Saving...' : 'Save and continue' }}
        </Button>

        <Button
          v-else-if="!isLastStep && !readonly"
          size="sm"
          class="shadow-sm"
          @click="emit('next')"
        >
          Continue
          <ArrowRight class="ml-1 h-3.5 w-3.5" aria-hidden="true" />
        </Button>

        <div v-if="isLastStep && !readonly" class="flex flex-col items-end gap-1">
          <Button
            size="sm"
            class="shadow-md"
            :disabled="applyDisabledReason !== null"
            :loading="applyPending"
            @click="emit('apply')"
          >
            <CheckCircle2 class="mr-1.5 h-4 w-4" aria-hidden="true" />
            {{ applyPending ? 'Applying to profile...' : 'Apply to profile' }}
          </Button>
          <p
            v-if="applyDisabledReason"
            class="max-w-[260px] text-right text-xs font-medium text-amber-600"
          >
            {{ applyDisabledReason }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
