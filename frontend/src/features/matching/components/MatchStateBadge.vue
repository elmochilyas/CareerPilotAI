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
      classes: 'border-emerald-300 bg-emerald-50 text-emerald-700',
      iconClasses: 'text-emerald-600',
    },
    partial: {
      icon: MinusCircle,
      classes: 'border-amber-300 bg-amber-50 text-amber-700',
      iconClasses: 'text-amber-600',
    },
    gap: {
      icon: XCircle,
      classes: 'border-red-300 bg-red-50 text-red-700',
      iconClasses: 'text-red-600',
    },
    unknown: {
      icon: HelpCircle,
      classes: 'border-slate-300 bg-slate-100 text-slate-600',
      iconClasses: 'text-slate-500',
    },
  }

  return map[props.state]
})
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
    :class="config.classes"
  >
    <component :is="config.icon" class="size-3.5" :class="config.iconClasses" aria-hidden="true" />
    <span>{{ stateLabels[state] }}</span>
    <span v-if="count !== undefined" class="font-semibold tabular-nums">{{ count }}</span>
  </span>
</template>
