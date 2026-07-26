<script setup lang="ts">
import { computed } from 'vue'
import { Check, AlertTriangle, ChevronDown } from '@lucide/vue'

export interface StepDef {
  key: string
  label: string
}

export type StepStatus = 'not_reviewed' | 'in_progress' | 'completed' | 'conflict'

const props = defineProps<{
  steps: StepDef[]
  activeIndex: number
  stepStatuses: StepStatus[]
  readonly?: boolean
}>()

const emit = defineEmits<{
  navigate: [index: number]
}>()

const isMobileView = computed(() => {
  if (typeof window === 'undefined') return false
  return window.innerWidth < 640
})

const currentStep = computed(() => props.steps[props.activeIndex])

const mobileDropdownOpen = defineModel<boolean>('mobileDropdownOpen', { default: false })

function navigateTo(index: number): void {
  mobileDropdownOpen.value = false
  emit('navigate', index)
}

function labelForStep(index: number): string {
  const s = props.stepStatuses[index] ?? 'not_reviewed'
  const labels: Record<string, string> = {
    not_reviewed: 'Not reviewed',
    in_progress: 'In progress',
    completed: 'Completed',
    conflict: 'Has conflict',
  }
  const step = props.steps[index]
  return `${step?.label ?? 'Step'} — ${labels[s] ?? s}`
}
</script>

<template>
  <nav aria-label="Review progress">
    <!-- Desktop stepper -->
    <ol v-if="!isMobileView" class="flex items-center gap-0">
      <li
        v-for="(step, i) in steps"
        :key="step.key"
        :aria-current="i === activeIndex ? 'step' : undefined"
        :class="['relative flex items-center', i < steps.length - 1 ? 'flex-1' : '']"
      >
        <button
          type="button"
          :aria-label="labelForStep(i)"
          :class="[
            'flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40',
            i === activeIndex
              ? 'bg-primary-50 text-primary-700 ring-1 ring-primary-200'
              : stepStatuses[i] === 'completed'
                ? 'text-emerald-700 hover:bg-emerald-50'
                : stepStatuses[i] === 'conflict'
                  ? 'text-amber-700 hover:bg-amber-50'
                  : 'text-slate-400 hover:text-slate-600',
          ]"
          :disabled="readonly ? false : stepStatuses[i] === 'not_reviewed' && i > activeIndex"
          @click="emit('navigate', i)"
        >
          <span
            :class="[
              'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
              i === activeIndex
                ? 'bg-primary-600 text-white'
                : stepStatuses[i] === 'completed'
                  ? 'bg-emerald-100 text-emerald-700'
                  : stepStatuses[i] === 'conflict'
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-slate-100 text-slate-400',
            ]"
          >
            <Check v-if="stepStatuses[i] === 'completed'" class="h-3 w-3" aria-hidden="true" />
            <AlertTriangle
              v-else-if="stepStatuses[i] === 'conflict'"
              class="h-3 w-3"
              aria-hidden="true"
            />
            <span v-else>{{ i + 1 }}</span>
          </span>
          <span class="hidden sm:inline">{{ step.label }}</span>
        </button>
        <div
          v-if="i < steps.length - 1"
          :class="[
            'mx-1 h-px flex-1',
            stepStatuses[i] === 'completed' ? 'bg-emerald-300' : 'bg-slate-200',
          ]"
          aria-hidden="true"
        />
      </li>
    </ol>

    <!-- Mobile stepper -->
    <div v-else class="space-y-2">
      <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-slate-700">
          Step {{ activeIndex + 1 }} of {{ steps.length }}
        </p>
        <div class="relative">
          <button
            type="button"
            class="flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded px-2 py-1"
            @click="mobileDropdownOpen = !mobileDropdownOpen"
          >
            View all steps
            <ChevronDown class="h-3.5 w-3.5" aria-hidden="true" />
          </button>
          <div
            v-if="mobileDropdownOpen"
            class="absolute right-0 top-full z-10 mt-1 w-48 rounded-lg border border-slate-200 bg-white shadow-lg"
          >
            <ul class="py-1">
              <li v-for="(step, i) in steps" :key="step.key">
                <button
                  type="button"
                  :aria-current="i === activeIndex ? 'step' : undefined"
                  class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500/40"
                  :class="
                    i === activeIndex
                      ? 'bg-primary-50 font-medium text-primary-700'
                      : 'text-slate-600'
                  "
                  @click="navigateTo(i)"
                >
                  <Check
                    v-if="stepStatuses[i] === 'completed'"
                    class="h-3.5 w-3.5 text-emerald-500"
                    aria-hidden="true"
                  />
                  <AlertTriangle
                    v-else-if="stepStatuses[i] === 'conflict'"
                    class="h-3.5 w-3.5 text-amber-500"
                    aria-hidden="true"
                  />
                  <span v-else class="h-3.5 w-3.5 text-center text-[10px] text-slate-400">{{
                    i + 1
                  }}</span>
                  {{ step.label }}
                </button>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <div
        class="h-1.5 rounded-full bg-slate-200"
        role="progressbar"
        :aria-valuenow="activeIndex + 1"
        :aria-valuemin="1"
        :aria-valuemax="steps.length"
        :aria-label="`Step ${activeIndex + 1} of ${steps.length}`"
      >
        <div
          class="h-full rounded-full bg-primary-500 transition-all duration-300"
          :style="{ width: `${((activeIndex + 1) / steps.length) * 100}%` }"
        />
      </div>
      <p class="text-sm font-medium text-slate-800">{{ currentStep?.label }}</p>
    </div>
  </nav>
</template>
