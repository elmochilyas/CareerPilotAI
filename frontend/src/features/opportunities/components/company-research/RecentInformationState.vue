<script setup lang="ts">
import { Clock, RefreshCw } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'
import type { ResearchClaim } from '../../types'

defineProps<{
  claims: ResearchClaim[]
  isBusy?: boolean
}>()

defineEmits<{ (e: 'refresh'): void }>()
</script>

<template>
  <div v-if="claims.length > 0" class="space-y-3">
    <div v-for="(claim, idx) in claims" :key="idx" class="flex gap-3">
      <span
        class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border"
        :class="[
          claim.kind === 'fact'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-600'
            : claim.kind === 'inference'
              ? 'border-amber-200 bg-amber-50 text-amber-600'
              : 'border-slate-200 bg-slate-50 text-slate-500',
        ]"
        aria-hidden="true"
      >
        <span v-if="claim.kind === 'fact'" class="size-1.5 rounded-full bg-emerald-500" />
        <span v-else-if="claim.kind === 'inference'" class="size-1.5 rounded-full bg-amber-500" />
        <span v-else class="size-1.5 rounded-full bg-slate-400" />
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-sm leading-relaxed text-[var(--text-primary)]">{{ claim.text }}</p>
        <div v-if="claim.kind === 'inference'" class="mt-1">
          <span
            class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 ring-1 ring-amber-200"
            >INFERENCE</span
          >
        </div>
        <div v-else-if="claim.kind === 'unknown'" class="mt-1">
          <span
            class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 ring-1 ring-slate-200"
            >UNKNOWN</span
          >
        </div>
      </div>
    </div>
  </div>

  <div
    v-else
    class="rounded-xl border border-dashed border-[var(--border-default)] bg-[var(--surface-secondary)] px-6 py-10 text-center"
  >
    <div
      class="mx-auto flex size-10 items-center justify-center rounded-full bg-white shadow-[var(--shadow-neo-raised-sm)]"
    >
      <Clock class="size-5 text-[var(--text-muted)]" aria-hidden="true" />
    </div>
    <p class="mt-3 text-sm font-semibold text-[var(--text-primary)]">
      No recent verified company updates available.
    </p>
    <p class="mx-auto mt-1 max-w-sm text-xs leading-relaxed text-[var(--text-muted)]">
      Refresh research to check for newly published information.
    </p>
    <Button
      variant="secondary"
      size="sm"
      class="mt-4"
      :loading="isBusy"
      :disabled="isBusy"
      @click="$emit('refresh')"
    >
      <RefreshCw class="mr-1.5 size-4" aria-hidden="true" />
      Refresh research
    </Button>
  </div>
</template>
