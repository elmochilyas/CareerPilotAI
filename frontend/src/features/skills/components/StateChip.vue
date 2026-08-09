<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue'
import { Check, BookOpen, Archive, X, Circle } from '@lucide/vue'
import type { SkillState } from '../types'

defineProps<{ state: SkillState }>()

const config: Record<SkillState, { icon: LucideIcon; label: string; class: string }> = {
  claimed: {
    icon: Circle,
    label: 'Claimed',
    class:
      'text-[var(--color-primary-700)] bg-[var(--color-primary-50)] shadow-[var(--shadow-neo-raised-sm)]',
  },
  verified: {
    icon: Check,
    label: 'Verified',
    class: 'text-green-700 bg-green-50 shadow-[var(--shadow-neo-raised-sm)]',
  },
  learning: {
    icon: BookOpen,
    label: 'Learning',
    class: 'text-amber-700 bg-amber-50 shadow-[var(--shadow-neo-raised-sm)] border-dashed',
  },
  rejected: {
    icon: X,
    label: 'Rejected',
    class: 'text-gray-500 bg-gray-50 shadow-[var(--shadow-neo-raised-sm)]',
  },
  archived: {
    icon: Archive,
    label: 'Archived',
    class: 'text-gray-500 bg-gray-50 shadow-[var(--shadow-neo-raised-sm)]',
  },
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="config[state].class"
  >
    <component :is="config[state].icon" class="h-3.5 w-3.5" aria-hidden="true" />
    {{ config[state].label }}
  </span>
</template>
