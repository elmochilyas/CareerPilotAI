<script setup lang="ts">
import { computed } from 'vue'
import { Circle, LoaderCircle, TriangleAlert, Check } from '@lucide/vue'
import type { IngestionStatus } from '../types'

const props = defineProps<{
  status: IngestionStatus | 'saved'
  compact?: boolean
}>()

type BadgeConfig = {
  label: string
  tone: 'neutral' | 'info' | 'attention' | 'success' | 'danger'
  icon: unknown
}

const config = computed<BadgeConfig>(() => {
  const values: Record<string, BadgeConfig> = {
    draft: { label: 'Waiting to start', tone: 'neutral', icon: Circle },
    queued: { label: 'In queue', tone: 'info', icon: Circle },
    processing: { label: 'Analyzing', tone: 'info', icon: LoaderCircle },
    review_ready: { label: 'Ready to review', tone: 'attention', icon: TriangleAlert },
    confirmed: { label: 'Confirmed', tone: 'success', icon: Check },
    saved: { label: 'Saved', tone: 'success', icon: Check },
    failed: { label: 'Needs attention', tone: 'danger', icon: TriangleAlert },
    cancelled: { label: 'Cancelled', tone: 'neutral', icon: Circle },
  }
  return values[props.status] ?? { label: 'Unknown', tone: 'neutral', icon: Circle }
})
</script>

<template>
  <span class="status-badge" :class="[`status-${config.tone}`, { 'status-compact': compact }]">
    <component :is="config.icon" class="status-icon" aria-hidden="true" />
    {{ config.label }}
  </span>
</template>

<style scoped>
.status-badge {
  display: inline-flex;
  min-height: 1.5rem;
  align-items: center;
  gap: 0.3125rem;
  border-radius: 0.375rem;
  padding: 0.1875rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 620;
  line-height: 1rem;
  white-space: nowrap;
}

.status-compact {
  min-height: 1.375rem;
  padding: 0.1875rem 0.4375rem;
  font-size: 0.6875rem;
}

.status-icon {
  width: 0.75rem;
  height: 0.75rem;
  flex-shrink: 0;
}

.status-neutral {
  background: var(--cp-surface-muted);
  color: var(--cp-text-muted);
}

.status-info {
  background: var(--cp-info-soft);
  color: var(--cp-info);
}

.status-attention {
  background: var(--cp-warning-soft);
  color: var(--cp-warning);
}

.status-success {
  background: var(--cp-success-soft);
  color: var(--cp-success);
}

.status-danger {
  background: var(--cp-danger-soft);
  color: var(--cp-danger);
}
</style>
