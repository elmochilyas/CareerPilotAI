<script setup lang="ts">
import { AlertCircle, CheckCircle, Clock, Loader2, RotateCcw } from '@lucide/vue'

withDefaults(
  defineProps<{
    status: 'idle' | 'queued' | 'processing' | 'completed' | 'failed'
    message?: string
    progress?: number
  }>(),
  { status: 'idle' },
)

const emit = defineEmits<{
  retry: []
}>()

type JobStatus = 'idle' | 'queued' | 'processing' | 'completed' | 'failed'
const statusConfig: Record<
  JobStatus,
  { icon: typeof Clock; label: string; color: string; bg: string }
> = {
  idle: { icon: Clock, label: 'Idle', color: 'text-slate-500', bg: 'bg-slate-50' },
  queued: { icon: Clock, label: 'Queued', color: 'text-amber-600', bg: 'bg-amber-50' },
  processing: { icon: Loader2, label: 'Processing', color: 'text-blue-600', bg: 'bg-blue-50' },
  completed: {
    icon: CheckCircle,
    label: 'Completed',
    color: 'text-emerald-600',
    bg: 'bg-emerald-50',
  },
  failed: { icon: AlertCircle, label: 'Failed', color: 'text-red-600', bg: 'bg-red-50' },
}
</script>

<template>
  <div
    class="flex items-center gap-3 rounded-lg border p-4"
    :class="statusConfig[status as JobStatus].bg"
  >
    <component
      :is="statusConfig[status as JobStatus].icon"
      class="size-5 shrink-0"
      :class="[
        statusConfig[status as JobStatus].color,
        status === 'processing' ? 'ds-animate-spin' : '',
      ]"
      aria-hidden="true"
    />
    <div class="flex-1 min-w-0">
      <p class="text-sm font-medium" :class="statusConfig[status as JobStatus].color">
        {{ statusConfig[status].label }}
      </p>
      <p v-if="message" class="mt-0.5 text-xs text-slate-500">
        {{ message }}
      </p>
      <div
        v-if="status === 'processing' && progress != null"
        class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200"
      >
        <div
          class="h-full rounded-full bg-blue-500 transition-all duration-500"
          :style="{ width: `${Math.min(100, Math.max(0, progress))}%` }"
        />
      </div>
    </div>
    <button
      v-if="status === 'failed'"
      type="button"
      class="shrink-0 inline-flex items-center gap-1.5 rounded-md bg-red-100 px-3 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500/40"
      @click="emit('retry')"
    >
      <RotateCcw :size="12" aria-hidden="true" />
      Retry
    </button>
  </div>
</template>
