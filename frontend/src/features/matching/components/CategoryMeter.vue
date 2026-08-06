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
      <span class="font-medium text-slate-700">{{ label }}</span>
      <span v-if="notEvaluated" class="font-medium text-slate-400">Not evaluated</span>
      <span v-else class="text-slate-500">{{ score }}%</span>
    </div>
    <div
      role="meter"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="score"
      :aria-valuetext="notEvaluated ? 'Not evaluated' : `${score} out of 100`"
      :aria-label="`${label} score ${score} out of 100`"
      class="h-2 w-full overflow-hidden rounded-full bg-slate-100"
    >
      <div
        class="h-full rounded-full transition-[width] duration-500 motion-reduce:transition-none"
        :class="notEvaluated ? 'bg-slate-200' : 'bg-primary-500'"
        :style="{ width }"
      />
    </div>
    <p v-if="notEvaluated" class="text-xs text-slate-400">
      No candidate data available for this category
    </p>
  </div>
</template>
