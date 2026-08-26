<script setup lang="ts">
import type { ResearchClaim } from '../../types'

defineProps<{ claims: ResearchClaim[] }>()
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <span
      v-for="(claim, idx) in claims"
      :key="idx"
      class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-3.5 py-2 text-xs font-medium leading-snug"
      :class="[
        claim.kind === 'fact'
          ? 'border-[var(--color-primary-200)] bg-[var(--color-primary-50)] text-[var(--color-primary-700)]'
          : claim.kind === 'inference'
            ? 'border-amber-200 bg-amber-50 text-amber-800'
            : 'border-slate-200 bg-slate-50 text-slate-600',
      ]"
    >
      <span class="break-words">{{ claim.text }}</span>
      <span
        v-if="claim.kind === 'inference'"
        class="shrink-0 rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-700"
      >
        INFERENCE
      </span>
      <span
        v-else-if="claim.kind === 'unknown'"
        class="shrink-0 rounded-full bg-slate-200 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-600"
      >
        UNKNOWN
      </span>
    </span>
  </div>
</template>
