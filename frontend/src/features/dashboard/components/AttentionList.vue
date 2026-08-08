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
  <div class="space-y-2">
    <div
      v-for="item in items"
      :key="item.id"
      class="flex items-center justify-between gap-4 rounded-lg border border-slate-100 bg-slate-50/50 px-4 py-3 transition-colors hover:bg-slate-50"
    >
      <div class="flex items-center gap-3 min-w-0">
        <div
          class="flex size-8 shrink-0 items-center justify-center rounded-full"
          :class="[
            item.status === 'failed'
              ? 'bg-red-100'
              : item.status === 'review_ready'
                ? 'bg-amber-100'
                : 'bg-blue-100',
          ]"
        >
          <AlertCircle
            v-if="item.status === 'failed'"
            class="size-4 text-red-600"
            aria-hidden="true"
          />
          <Clock v-else class="size-4 text-amber-600" aria-hidden="true" />
        </div>
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-slate-900">
            {{ item.personal_label || `Ingestion #${item.id}` }}
          </p>
          <p class="text-xs text-slate-500">
            <span
              class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
              :class="[
                statusVariant(item.status) === 'warning'
                  ? 'bg-amber-100 text-amber-700'
                  : statusVariant(item.status) === 'error'
                    ? 'bg-red-100 text-red-700'
                    : statusVariant(item.status) === 'info'
                      ? 'bg-blue-100 text-blue-700'
                      : 'bg-slate-100 text-slate-600',
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
        class="shrink-0 rounded-md bg-white border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition-colors hover:bg-slate-50 hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
        @click="navigateToReview(item.id)"
      >
        Review
      </button>
    </div>
  </div>
</template>
