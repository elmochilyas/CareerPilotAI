<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue'
import { Check, BookOpen, Archive, X, Circle } from '@lucide/vue'
import type { SkillState } from '../types'

defineProps<{ state: SkillState }>()

const config: Record<SkillState, { icon: LucideIcon; label: string; class: string }> = {
  claimed: {
    icon: Circle,
    label: 'Claimed',
    class: 'border-primary-300 text-primary-700 bg-primary-50',
  },
  verified: {
    icon: Check,
    label: 'Verified',
    class: 'border-green-300 text-green-700 bg-green-50',
  },
  learning: {
    icon: BookOpen,
    label: 'Learning',
    class: 'border-amber-300 text-amber-700 bg-amber-50 border-dashed',
  },
  rejected: { icon: X, label: 'Rejected', class: 'border-gray-300 text-gray-500 bg-gray-50' },
  archived: { icon: Archive, label: 'Archived', class: 'border-gray-300 text-gray-500 bg-gray-50' },
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium"
    :class="config[state].class"
  >
    <component :is="config[state].icon" class="h-3.5 w-3.5" aria-hidden="true" />
    {{ config[state].label }}
  </span>
</template>
