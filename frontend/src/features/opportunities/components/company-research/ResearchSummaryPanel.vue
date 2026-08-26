<script setup lang="ts">
import { computed } from 'vue'
import { RefreshCw } from '@lucide/vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import { formatDate } from '@/app/utils/date'
import type { CompanyResearchBrief } from '../../types'

const props = defineProps<{
  brief: CompanyResearchBrief | null
  isBusy: boolean
}>()

defineEmits<{ (e: 'refresh'): void }>()

const statusLabel = computed(() => {
  const s = props.brief?.status
  if (s === 'completed') return 'Completed'
  if (s === 'limited') return 'Limited'
  if (s === 'failed') return 'Failed'
  if (s === 'processing') return 'Processing'
  if (s === 'not_researched') return 'Not researched'
  return s ? String(s).charAt(0).toUpperCase() + String(s).slice(1) : '—'
})

const statusVariant = computed(() => {
  const s = props.brief?.status
  if (s === 'completed') return 'success' as const
  if (s === 'limited') return 'warning' as const
  if (s === 'failed') return 'error' as const
  return 'default' as const
})

const freshnessLabel = computed(() => {
  if (!props.brief) return '—'
  return props.brief.stale ? 'Older' : 'Fresh'
})

const freshnessVariant = computed(() => {
  if (!props.brief) return 'default' as const
  return props.brief.stale ? ('warning' as const) : ('success' as const)
})

const confidenceLabel = computed(() => {
  const brief = props.brief
  if (!brief) return '—'
  const allClaims = [
    ...(brief.products ?? []),
    ...(brief.technology_context ?? []),
    ...(brief.role_context ?? []),
    ...(brief.recent_information ?? []),
    ...(brief.candidate_preparation ?? []),
  ]
  const confidences = allClaims
    .map((c) => c.confidence)
    .filter((c): c is 'low' | 'medium' | 'high' => c !== null)
  if (confidences.length === 0) return '—'
  let high = 0
  let medium = 0
  let low = 0
  for (const c of confidences) {
    if (c === 'high') high++
    else if (c === 'medium') medium++
    else low++
  }
  if (high >= medium && high >= low) return 'High'
  if (low >= high && low >= medium) return 'Low'
  return 'Medium'
})

const confidenceVariant = computed(() => {
  const label = confidenceLabel.value
  if (label === 'High') return 'success' as const
  if (label === 'Medium') return 'warning' as const
  if (label === 'Low') return 'default' as const
  return 'default' as const
})

const researchedLabel = computed(() => {
  const iso = props.brief?.researched_at ?? props.brief?.generated_at ?? null
  if (!iso) return null
  const formatted = formatDate(iso)
  return formatted || null
})
</script>

<template>
  <div
    class="rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-5 sm:p-6 lg:sticky lg:top-6"
  >
    <h3 class="text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">
      Research summary
    </h3>

    <dl class="mt-4 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <dt class="text-xs font-medium text-[var(--text-muted)]">Research status</dt>
        <dd>
          <Badge :variant="statusVariant" size="sm">{{ statusLabel }}</Badge>
        </dd>
      </div>

      <div class="flex items-center justify-between gap-3">
        <dt class="text-xs font-medium text-[var(--text-muted)]">Confidence</dt>
        <dd>
          <Badge :variant="confidenceVariant" size="sm">{{ confidenceLabel }}</Badge>
        </dd>
      </div>

      <div class="flex items-center justify-between gap-3">
        <dt class="text-xs font-medium text-[var(--text-muted)]">Information freshness</dt>
        <dd>
          <Badge :variant="freshnessVariant" size="sm">{{ freshnessLabel }}</Badge>
        </dd>
      </div>

      <div v-if="researchedLabel" class="rounded-xl bg-[var(--surface-secondary)] px-3 py-2.5">
        <dt class="text-[11px] font-medium uppercase tracking-wide text-[var(--text-muted)]">
          Last researched
        </dt>
        <dd class="mt-0.5 text-sm font-semibold text-[var(--text-primary)]">
          {{ researchedLabel }}
        </dd>
      </div>
      <div v-else class="rounded-xl bg-[var(--surface-secondary)] px-3 py-2.5">
        <dt class="text-[11px] font-medium uppercase tracking-wide text-[var(--text-muted)]">
          Last researched
        </dt>
        <dd class="mt-0.5 text-sm font-medium text-[var(--text-muted)]">—</dd>
      </div>
    </dl>

    <Button
      variant="secondary"
      size="sm"
      :loading="isBusy"
      :disabled="isBusy"
      class="mt-5 w-full"
      @click="$emit('refresh')"
    >
      <RefreshCw class="mr-1.5 size-4" aria-hidden="true" />
      Refresh research
    </Button>

    <p v-if="brief?.stale" class="mt-3 text-center text-[11px] leading-relaxed text-amber-700">
      Potentially outdated — refresh recommended
    </p>
  </div>
</template>
