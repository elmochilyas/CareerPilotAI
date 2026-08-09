<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  label: string
  score: number
  hasData?: boolean
}>()

const notEvaluated = computed(() => props.hasData === false)
const width = computed(() => `${Math.min(100, Math.max(0, props.score))}%`)
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <div class="flex items-center justify-between gap-3 text-xs">
      <span class="font-medium text-[var(--text-primary)]">{{ label }}</span>
      <span v-if="notEvaluated" class="font-medium text-[var(--text-muted)]">Not evaluated</span>
      <span v-else class="text-[var(--text-secondary)]">{{ score }}%</span>
    </div>
    <div
      role="meter"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="score"
      :aria-valuetext="notEvaluated ? 'Not evaluated' : `${score} out of 100`"
      :aria-label="`${label} score ${score} out of 100`"
      class="h-2 w-full overflow-hidden rounded-full bg-[var(--surface-sunken)] shadow-[var(--shadow-neo-inset)]"
    >
      <div
        class="h-full rounded-full transition-[width] duration-500 motion-reduce:transition-none"
        :class="notEvaluated ? 'bg-[var(--color-neutral-200)]' : 'bg-[var(--color-primary-500)]'"
        :style="{ width }"
      />
    </div>
    <p v-if="notEvaluated" class="text-xs text-[var(--text-muted)]">
      No candidate data available for this category
    </p>
  </div>
</template>
