<script setup lang="ts">
import { Check } from '@lucide/vue'

defineProps<{
  steps: Array<{ label: string; description?: string }>
  currentStep: number
}>()
</script>

<template>
  <nav aria-label="Progress" class="flex flex-col gap-0">
    <ol class="relative flex flex-col gap-0">
      <li v-for="(step, index) in steps" :key="index" class="relative flex items-start gap-3 pb-6">
        <div class="relative flex flex-col items-center">
          <span
            class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-sm font-semibold transition-colors"
            :class="
              index < currentStep
                ? 'border-primary-600 bg-primary-600 text-white'
                : index === currentStep
                  ? 'border-primary-600 bg-white text-primary-600'
                  : 'border-slate-300 bg-white text-slate-400'
            "
            :aria-current="index === currentStep ? 'step' : undefined"
          >
            <Check v-if="index < currentStep" :size="16" stroke-width="3" aria-label="Completed" />
            <span v-else>{{ index + 1 }}</span>
          </span>
          <div
            v-if="index < steps.length - 1"
            class="absolute top-8 h-full w-0.5"
            :class="index < currentStep ? 'bg-primary-600' : 'bg-slate-200'"
          />
        </div>
        <div class="pt-1">
          <p
            class="text-sm font-medium"
            :class="index <= currentStep ? 'text-slate-900' : 'text-slate-400'"
          >
            {{ step.label }}
          </p>
          <p
            v-if="step.description"
            class="mt-0.5 text-xs"
            :class="index <= currentStep ? 'text-slate-500' : 'text-slate-400'"
          >
            {{ step.description }}
          </p>
        </div>
      </li>
    </ol>
  </nav>
</template>
