<script setup lang="ts">
import { useRouter } from 'vue-router'
import { Clock, AlertCircle } from '@lucide/vue'
import type { JobIngestion } from '@/features/opportunities/types'

defineProps<{
  items: JobIngestion[]
}>()

const router = useRouter()

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    review_ready: 'Review needed',
    failed: 'Failed',
    queued: 'Processing',
    processing: 'Processing',
  }
  return map[status] ?? status
}

function statusVariant(status: string): 'warning' | 'error' | 'info' | 'default' {
  const map: Record<string, 'warning' | 'error' | 'info' | 'default'> = {
    review_ready: 'warning',
    failed: 'error',
    queued: 'info',
    processing: 'info',
  }
  return map[status] ?? 'default'
}

function navigateToReview(id: number) {
  router.push({ name: 'opportunities-review', params: { id } })
}
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="item in items"
      :key="item.id"
      class="flex items-center justify-between gap-4 rounded-[var(--radius-xl)] bg-[var(--surface-primary)] px-4 py-3 shadow-[var(--shadow-neo-raised-sm)] transition-shadow hover:shadow-[var(--shadow-neo-raised)]"
    >
      <div class="flex items-center gap-3 min-w-0">
        <div
          class="flex size-8 shrink-0 items-center justify-center rounded-full shadow-[var(--shadow-neo-inset)]"
          :class="[
            item.status === 'failed'
              ? 'bg-[var(--color-error-100)]'
              : item.status === 'review_ready'
                ? 'bg-[var(--color-warning-100)]'
                : 'bg-[var(--color-primary-100)]',
          ]"
        >
          <AlertCircle
            v-if="item.status === 'failed'"
            class="size-4 text-[var(--color-error-600)]"
            aria-hidden="true"
          />
          <Clock v-else class="size-4 text-[var(--color-warning-600)]" aria-hidden="true" />
        </div>
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-[var(--text-primary)]">
            {{ item.personal_label || `Ingestion #${item.id}` }}
          </p>
          <p class="text-[var(--text-xs)] text-[var(--text-secondary)]">
            <span
              class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
              :class="[
                statusVariant(item.status) === 'warning'
                  ? 'bg-[var(--color-warning-100)] text-[var(--color-warning-700)]'
                  : statusVariant(item.status) === 'error'
                    ? 'bg-[var(--color-error-100)] text-[var(--color-error-700)]'
                    : statusVariant(item.status) === 'info'
                      ? 'bg-[var(--color-primary-100)] text-[var(--color-primary-700)]'
                      : 'bg-[var(--color-neutral-100)] text-[var(--color-neutral-600)]',
              ]"
            >
              {{ statusLabel(item.status) }}
            </span>
            <span class="ml-1.5">{{ item.failure_reason || 'Awaiting action' }}</span>
          </p>
        </div>
      </div>
      <button
        v-if="item.status === 'review_ready' || item.status === 'failed'"
        class="shrink-0 rounded-[var(--radius-md)] bg-[var(--surface-primary)] px-3 py-1.5 text-[var(--text-xs)] font-medium text-[var(--text-secondary)] shadow-[var(--shadow-neo-raised-sm)] transition-colors hover:bg-[var(--surface-secondary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/40"
        @click="navigateToReview(item.id)"
      >
        Review
      </button>
    </div>
  </div>
</template>
