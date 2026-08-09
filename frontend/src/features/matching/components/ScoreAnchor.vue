<script setup lang="ts">
import { computed } from 'vue'
import type { AlignmentTier } from '../utils/matchPresentation'
import { alignmentLabel, alignmentTier } from '../utils/matchPresentation'

const props = defineProps<{
  score: number
}>()

const tier = computed<AlignmentTier>(() => alignmentTier(props.score))

const valueClasses: Record<AlignmentTier, string> = {
  strong: 'text-emerald-700',
  moderate: 'text-primary-700',
  weak: 'text-amber-700',
}

const labelClasses: Record<AlignmentTier, string> = {
  strong: 'text-emerald-700',
  moderate: 'text-primary-700',
  weak: 'text-amber-700',
}
</script>

<template>
  <div
    class="flex flex-col items-center gap-1 rounded-[var(--radius-2xl)] bg-[var(--surface-primary)] px-8 py-6 shadow-[var(--shadow-neo-raised-lg)] lg:items-start"
  >
    <p class="flex items-baseline gap-1" :aria-label="`Match score ${score} out of 100`">
      <span class="text-5xl font-bold tracking-tight tabular-nums" :class="valueClasses[tier]">
        {{ score }}%
      </span>
      <span class="text-lg font-medium text-slate-500">Match</span>
    </p>
    <span class="text-sm font-semibold" :class="labelClasses[tier]">
      {{ alignmentLabel(score) }}
    </span>
  </div>
</template>
