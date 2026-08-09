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
            class="flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition-all duration-200"
            :class="
              index < currentStep
                ? 'bg-[var(--color-success-500)] text-white shadow-[var(--shadow-neo-button)]'
                : index === currentStep
                  ? 'bg-[var(--surface-primary)] text-[var(--color-primary-600)] shadow-[var(--shadow-neo-raised)]'
                  : 'bg-[var(--surface-secondary)] text-[var(--text-muted)] shadow-[var(--shadow-neo-inset)]'
            "
            :aria-current="index === currentStep ? 'step' : undefined"
          >
            <Check v-if="index < currentStep" :size="16" stroke-width="3" aria-label="Completed" />
            <span v-else>{{ index + 1 }}</span>
          </span>
          <div
            v-if="index < steps.length - 1"
            class="absolute top-8 h-full w-0.5"
            :class="
              index < currentStep
                ? 'bg-[var(--color-success-500)]'
                : 'bg-[var(--color-neutral-200)]'
            "
          />
        </div>
        <div class="pt-1">
          <p
            class="text-sm font-medium"
            :class="
              index <= currentStep ? 'text-[var(--text-primary)]' : 'text-[var(--text-muted)]'
            "
          >
            {{ step.label }}
          </p>
          <p
            v-if="step.description"
            class="mt-0.5 text-xs"
            :class="
              index <= currentStep ? 'text-[var(--text-secondary)]' : 'text-[var(--text-muted)]'
            "
          >
            {{ step.description }}
          </p>
        </div>
      </li>
    </ol>
  </nav>
</template>
