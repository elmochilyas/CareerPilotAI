<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    percentage: number
    size?: number
    strokeWidth?: number
  }>(),
  { size: 64, strokeWidth: 5 },
)

const radius = computed(() => (props.size - props.strokeWidth) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const offset = computed(
  () => circumference.value * (1 - Math.min(100, Math.max(0, props.percentage)) / 100),
)
</script>

<template>
  <div
    class="relative inline-flex items-center justify-center rounded-full shadow-[var(--shadow-neo-raised-sm)]"
  >
    <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`" class="-rotate-90">
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        stroke="var(--color-neutral-200)"
        :stroke-width="strokeWidth"
      />
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        stroke="var(--color-primary-600)"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="offset"
        class="transition-[stroke-dashoffset] duration-700 motion-reduce:transition-none"
      />
    </svg>
    <span class="absolute text-sm font-bold text-[var(--text-primary)]">
      {{ Math.round(percentage)
      }}<span class="text-xs font-normal text-[var(--text-muted)]">%</span>
    </span>
  </div>
</template>
