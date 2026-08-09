<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'
import type { MatchFinding } from '../types'
import { collapsedSummary, humanReason, labelOf, requirementKind } from '../utils/matchPresentation'
import MatchEvidenceList from './MatchEvidenceList.vue'
import MatchStateBadge from './MatchStateBadge.vue'

defineProps<{
  finding: MatchFinding
}>()
</script>

<template>
  <details
    class="group rounded-[var(--radius-xl)] bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised)]"
  >
    <summary
      class="flex cursor-pointer list-none items-start justify-between gap-4 p-5 focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
    >
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-sm font-medium text-[var(--text-primary)]">{{ labelOf(finding) }}</h3>
          <MatchStateBadge :state="finding.match_state" />
        </div>
        <p class="mt-0.5 text-xs text-[var(--text-secondary)]">{{ requirementKind(finding) }}</p>
        <p class="mt-2 text-sm text-[var(--text-secondary)]">{{ collapsedSummary(finding) }}</p>
      </div>
      <span
        class="flex shrink-0 items-center gap-1 pt-0.5 text-xs font-medium text-[var(--text-muted)]"
      >
        Details
        <ChevronDown class="size-4 transition-transform group-open:rotate-180" aria-hidden="true" />
      </span>
    </summary>

    <dl class="space-y-3 border-t border-[var(--border-subtle)] px-5 py-4">
      <div>
        <dt class="text-xs font-medium tracking-wide text-[var(--text-muted)] uppercase">
          Job requires
        </dt>
        <dd class="mt-1 text-sm text-[var(--text-primary)]">
          {{ finding.requirement_label ?? finding.requirement_text }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium tracking-wide text-[var(--text-muted)] uppercase">
          Your profile
        </dt>
        <dd class="mt-1">
          <MatchEvidenceList :finding="finding" />
        </dd>
      </div>
      <div>
        <dt class="text-xs font-medium tracking-wide text-[var(--text-muted)] uppercase">Why</dt>
        <dd class="mt-1 text-sm leading-relaxed text-[var(--text-secondary)]">
          {{ humanReason(finding) }}
        </dd>
      </div>
    </dl>
  </details>
</template>
