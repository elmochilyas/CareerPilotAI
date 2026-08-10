<script setup lang="ts">
import type { WorkspaceStep } from '../types'

const { steps, activeIndex } = defineProps<{
  steps: readonly WorkspaceStep[]
  activeIndex: number
  enabledThrough: number
}>()

const emit = defineEmits<{
  navigate: [step: WorkspaceStep]
}>()

const stepLabels: Record<WorkspaceStep, string> = {
  generate: 'Generate',
  review: 'Review',
  export: 'Export',
}
</script>

<template>
  <nav aria-label="Resume tailoring steps">
    <ol class="flex items-center gap-2">
      <li
        v-for="(step, index) in steps"
        :key="step"
        class="flex items-center gap-2"
        :class="index < steps.length - 1 ? 'flex-1' : ''"
      >
        <button
          type="button"
          :aria-current="index === activeIndex ? 'step' : undefined"
          :disabled="index > enabledThrough"
          :class="[
            'flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30',
            index === activeIndex
              ? 'bg-[var(--color-primary-100)] text-[var(--color-primary-700)] shadow-[var(--shadow-neo-raised-sm)]'
              : index < activeIndex
                ? 'text-[var(--color-success-700)] hover:bg-[var(--color-success-50)]'
                : 'text-[var(--text-muted)] hover:bg-[var(--surface-secondary)] hover:text-[var(--text-secondary)] disabled:cursor-not-allowed disabled:opacity-45',
          ]"
          @click="index <= enabledThrough && emit('navigate', step)"
        >
          <span
            :class="[
              'flex size-6 shrink-0 items-center justify-center rounded-lg text-xs font-bold',
              index === activeIndex
                ? 'bg-[var(--color-primary-600)] text-white'
                : index < activeIndex
                  ? 'bg-[var(--color-success-600)] text-white'
                  : 'bg-[var(--color-neutral-100)] text-[var(--text-muted)]',
            ]"
          >
            <svg
              v-if="index < activeIndex"
              class="size-3.5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="3"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <polyline points="20 6 9 17 4 12" />
            </svg>
            <span v-else>{{ index + 1 }}</span>
          </span>
          <span class="hidden sm:inline">{{ stepLabels[step] }}</span>
        </button>
        <div
          v-if="index < steps.length - 1"
          class="mx-1 hidden h-0.5 flex-1 rounded-full bg-[var(--color-neutral-100)] sm:block"
          aria-hidden="true"
        >
          <div
            :class="[
              'h-full rounded-full transition-all duration-500',
              index < activeIndex ? 'bg-[var(--color-success-400)]' : 'bg-transparent',
            ]"
          />
        </div>
      </li>
    </ol>
  </nav>
</template>
