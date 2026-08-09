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
  reviewed?: number
  total?: number
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
  return `${step?.label ?? 'Step'} \u2014 ${labels[s] ?? s}`
}
</script>

<template>
  <nav aria-label="Review progress">
    <!-- Desktop stepper -->
    <ol v-if="!isMobileView" class="flex items-start gap-0">
      <li
        v-for="(step, i) in steps"
        :key="step.key"
        :aria-current="i === activeIndex ? 'step' : undefined"
        class="relative flex flex-col items-center"
        :class="i < steps.length - 1 ? 'flex-1' : ''"
      >
        <button
          type="button"
          :aria-label="labelForStep(i)"
          :class="[
            'relative z-10 flex flex-col items-center gap-2 px-2 py-2 transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-primary-500/40 rounded-xl',
            i === activeIndex ? '' : 'opacity-60 hover:opacity-100',
          ]"
          :disabled="readonly ? false : stepStatuses[i] === 'not_reviewed' && i > activeIndex"
          @click="emit('navigate', i)"
        >
          <span class="relative">
            <span
              :class="[
                'flex h-11 w-11 items-center justify-center rounded-xl text-sm font-bold transition-all duration-500',
                i === activeIndex
                  ? 'bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-lg shadow-primary-200/60 ring-4 ring-primary-50'
                  : stepStatuses[i] === 'completed'
                    ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-white shadow-md shadow-emerald-200/50'
                    : stepStatuses[i] === 'conflict'
                      ? 'bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-md shadow-amber-200/50'
                      : 'bg-slate-100 text-slate-400 shadow-sm',
              ]"
            >
              <Check v-if="stepStatuses[i] === 'completed'" class="h-5 w-5" aria-hidden="true" />
              <AlertTriangle
                v-else-if="stepStatuses[i] === 'conflict'"
                class="h-5 w-5"
                aria-hidden="true"
              />
              <span v-else class="text-lg">{{ i + 1 }}</span>
            </span>
            <span
              v-if="i === activeIndex"
              class="absolute inset-0 -z-10 animate-ping rounded-xl bg-primary-300/40"
              aria-hidden="true"
            />
          </span>
          <span
            :class="[
              'text-xs font-semibold text-center leading-tight max-w-[84px] transition-colors duration-300',
              i === activeIndex
                ? 'text-primary-700'
                : stepStatuses[i] === 'completed'
                  ? 'text-emerald-700'
                  : stepStatuses[i] === 'conflict'
                    ? 'text-amber-700'
                    : 'text-slate-400',
            ]"
          >
            {{ step.label }}
          </span>
        </button>
        <div
          v-if="i < steps.length - 1"
          class="absolute left-[calc(50%+28px)] top-[22px] h-1 w-[calc(100%-56px)] overflow-hidden rounded-full bg-slate-100"
          aria-hidden="true"
        >
          <div
            :class="[
              'h-full rounded-full transition-all duration-700 ease-out',
              stepStatuses[i] === 'completed' ? 'bg-emerald-400' : 'bg-slate-100',
            ]"
          />
        </div>
      </li>
    </ol>

    <!-- Mobile stepper -->
    <div v-else class="space-y-3">
      <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-slate-700">
          Step {{ activeIndex + 1 }} of {{ steps.length }}
        </p>
        <div class="relative">
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            @click="mobileDropdownOpen = !mobileDropdownOpen"
          >
            <span
              :class="[
                'flex h-5 w-5 items-center justify-center rounded-md text-[10px] font-bold',
                stepStatuses[activeIndex] === 'completed'
                  ? 'bg-emerald-100 text-emerald-700'
                  : stepStatuses[activeIndex] === 'conflict'
                    ? 'bg-amber-100 text-amber-700'
                    : 'bg-primary-100 text-primary-700',
              ]"
            >
              <Check
                v-if="stepStatuses[activeIndex] === 'completed'"
                class="h-3 w-3"
                aria-hidden="true"
              />
              <AlertTriangle
                v-else-if="stepStatuses[activeIndex] === 'conflict'"
                class="h-3 w-3"
                aria-hidden="true"
              />
              <span v-else>{{ activeIndex + 1 }}</span>
            </span>
            <span class="mx-1">{{ currentStep?.label ?? 'Select step' }}</span>
            <ChevronDown
              class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200"
              :class="mobileDropdownOpen ? 'rotate-180' : ''"
              aria-hidden="true"
            />
          </button>
          <Transition name="dropdown">
            <div
              v-if="mobileDropdownOpen"
              class="absolute right-0 top-full z-20 mt-1.5 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
            >
              <ul class="py-1.5">
                <li v-for="(step, i) in steps" :key="step.key">
                  <button
                    type="button"
                    :aria-current="i === activeIndex ? 'step' : undefined"
                    class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm transition-all hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500/40"
                    :class="
                      i === activeIndex
                        ? 'bg-primary-50 font-semibold text-primary-700'
                        : 'text-slate-600'
                    "
                    @click="navigateTo(i)"
                  >
                    <span
                      :class="[
                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold',
                        i === activeIndex
                          ? 'bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-sm'
                          : stepStatuses[i] === 'completed'
                            ? 'bg-gradient-to-br from-emerald-400 to-emerald-600 text-white'
                            : stepStatuses[i] === 'conflict'
                              ? 'bg-gradient-to-br from-amber-400 to-amber-600 text-white'
                              : 'bg-slate-100 text-slate-400',
                      ]"
                    >
                      <Check
                        v-if="stepStatuses[i] === 'completed'"
                        class="h-3.5 w-3.5"
                        aria-hidden="true"
                      />
                      <AlertTriangle
                        v-else-if="stepStatuses[i] === 'conflict'"
                        class="h-3.5 w-3.5"
                        aria-hidden="true"
                      />
                      <span v-else>{{ i + 1 }}</span>
                    </span>
                    <div class="flex-1">
                      <p class="text-sm">{{ step.label }}</p>
                      <p v-if="stepStatuses[i] === 'completed'" class="text-xs text-emerald-600">
                        Reviewed
                      </p>
                      <p v-else-if="stepStatuses[i] === 'conflict'" class="text-xs text-amber-600">
                        Needs attention
                      </p>
                      <p v-else-if="i === activeIndex" class="text-xs text-primary-600">
                        In progress
                      </p>
                      <p v-else class="text-xs text-slate-400">Pending</p>
                    </div>
                  </button>
                </li>
              </ul>
            </div>
          </Transition>
        </div>
      </div>

      <!-- Mobile progress bar -->
      <div
        class="h-2 overflow-hidden rounded-full bg-slate-100"
        role="progressbar"
        :aria-valuenow="activeIndex + 1"
        :aria-valuemin="1"
        :aria-valuemax="steps.length"
        :aria-label="`Step ${activeIndex + 1} of ${steps.length}`"
      >
        <div
          class="h-full rounded-full bg-gradient-to-r from-primary-500 to-primary-400 transition-[transform] duration-500 ease-out origin-left"
          :style="{ transform: `scaleX(${(activeIndex + 1) / steps.length})` }"
        />
      </div>

      <!-- Review progress on mobile -->
      <div v-if="total && total > 0" class="flex items-center gap-2 text-xs text-slate-500">
        <div class="flex-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full rounded-full bg-emerald-400 transition-[transform] duration-500 origin-left"
            :style="{ transform: `scaleX(${total ? (reviewed ?? 0) / total : 0})` }"
          />
        </div>
        <span class="font-medium text-slate-700 shrink-0"> {{ reviewed ?? 0 }}/{{ total }} </span>
        <span>reviewed</span>
      </div>
    </div>
  </nav>
</template>

<style scoped>
@keyframes stepper-ping {
  75%,
  to {
    opacity: 0;
    transform: scale(1.8);
  }
}
.animate-ping {
  animation: stepper-ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
}
.dropdown-enter-active {
  transition:
    opacity 0.2s cubic-bezier(0.16, 1, 0.3, 1),
    transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.dropdown-leave-active {
  transition:
    opacity 0.15s cubic-bezier(0.55, 0, 1, 0.45),
    transform 0.15s cubic-bezier(0.55, 0, 1, 0.45);
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(0.96);
}
</style>
