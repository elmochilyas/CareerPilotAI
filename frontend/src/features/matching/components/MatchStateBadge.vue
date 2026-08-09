<script setup lang="ts">
import { computed } from 'vue'
import { CheckCircle2, HelpCircle, MinusCircle, XCircle } from '@lucide/vue'
import type { LucideIcon } from '@lucide/vue'
import { stateLabels } from '../utils/matchPresentation'
import type { MatchState } from '../types'

const props = defineProps<{
  state: MatchState
  count?: number
}>()

const config = computed<{
  icon: LucideIcon
  classes: string
  iconClasses: string
}>(() => {
  const map: Record<MatchState, { icon: LucideIcon; classes: string; iconClasses: string }> = {
    matched: {
      icon: CheckCircle2,
      classes: 'bg-emerald-50 text-emerald-700 shadow-[var(--shadow-neo-raised-sm)]',
      iconClasses: 'text-emerald-600',
    },
    partial: {
      icon: MinusCircle,
      classes: 'bg-amber-50 text-amber-700 shadow-[var(--shadow-neo-raised-sm)]',
      iconClasses: 'text-amber-600',
    },
    gap: {
      icon: XCircle,
      classes: 'bg-red-50 text-red-700 shadow-[var(--shadow-neo-raised-sm)]',
      iconClasses: 'text-red-600',
    },
    unknown: {
      icon: HelpCircle,
      classes:
        'bg-[var(--surface-secondary)] text-[var(--text-secondary)] shadow-[var(--shadow-neo-raised-sm)]',
      iconClasses: 'text-[var(--text-muted)]',
    },
  }

  return map[props.state]
})
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
    :class="config.classes"
  >
    <component :is="config.icon" class="size-3.5" :class="config.iconClasses" aria-hidden="true" />
    <span>{{ stateLabels[state] }}</span>
    <span v-if="count !== undefined" class="font-semibold tabular-nums">{{ count }}</span>
  </span>
</template>
