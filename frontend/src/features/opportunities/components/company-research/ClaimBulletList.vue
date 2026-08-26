<script setup lang="ts">
import { Check } from '@lucide/vue'
import type { ResearchClaim } from '../../types'

defineProps<{ claims: ResearchClaim[] }>()
</script>

<template>
  <ul class="space-y-3">
    <li v-for="(claim, idx) in claims" :key="idx" class="flex gap-3">
      <span
        class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border border-[var(--color-primary-100)] bg-[var(--color-primary-50)]"
        aria-hidden="true"
      >
        <Check class="size-3.5 text-[var(--color-primary-600)]" />
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-sm leading-relaxed text-[var(--text-primary)]">
          {{ claim.text }}
        </p>
        <div v-if="claim.kind === 'inference'" class="mt-1.5">
          <span
            class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 ring-1 ring-amber-200"
          >
            INFERENCE
          </span>
          <span class="ml-1.5 text-xs text-[var(--text-muted)]"
            >— CareerPilot interpretation based on the sources</span
          >
        </div>
        <div v-else-if="claim.kind === 'unknown'" class="mt-1.5">
          <span
            class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 ring-1 ring-slate-200"
          >
            UNKNOWN
          </span>
          <span class="ml-1.5 text-xs text-[var(--text-muted)]"
            >— could not be reliably established</span
          >
        </div>
      </div>
    </li>
  </ul>
</template>
