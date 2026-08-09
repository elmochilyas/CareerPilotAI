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
  box-shadow: var(--shadow-neo-raised-sm);
  background: var(--surface-page);
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
  background: var(--surface-secondary);
  color: var(--text-muted);
}

.status-info {
  background: var(--color-info-50);
  color: var(--color-info-600);
}

.status-attention {
  background: var(--color-warning-50);
  color: var(--color-warning-600);
}

.status-success {
  background: var(--color-success-50);
  color: var(--color-success-600);
}

.status-danger {
  background: var(--color-error-50);
  color: var(--color-error-600);
}
</style>
