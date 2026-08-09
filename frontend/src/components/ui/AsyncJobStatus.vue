<script setup lang="ts">
import { AlertCircle, CheckCircle, Clock, Loader2, RotateCcw } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'

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
  idle: {
    icon: Clock,
    label: 'Idle',
    color: 'text-[var(--text-muted)]',
    bg: 'bg-[var(--surface-secondary)]',
  },
  queued: {
    icon: Clock,
    label: 'Queued',
    color: 'text-[var(--color-warning-600)]',
    bg: 'bg-[var(--color-warning-50)]',
  },
  processing: {
    icon: Loader2,
    label: 'Processing',
    color: 'text-[var(--color-info-600)]',
    bg: 'bg-[var(--color-info-50)]',
  },
  completed: {
    icon: CheckCircle,
    label: 'Completed',
    color: 'text-[var(--color-success-600)]',
    bg: 'bg-[var(--color-success-50)]',
  },
  failed: {
    icon: AlertCircle,
    label: 'Failed',
    color: 'text-[var(--color-error-600)]',
    bg: 'bg-[var(--color-error-50)]',
  },
}
</script>

<template>
  <div
    class="flex items-center gap-3 rounded-xl shadow-[var(--shadow-neo-raised-sm)] p-4"
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
      <p v-if="message" class="mt-0.5 text-xs text-[var(--text-muted)]">
        {{ message }}
      </p>
      <div
        v-if="status === 'processing' && progress != null"
        class="mt-2 h-1.5 overflow-hidden rounded-full bg-[var(--surface-secondary)] shadow-[var(--shadow-neo-inset)]"
      >
        <div
          class="h-full rounded-full bg-[var(--color-primary-500)] transition-all duration-500"
          :style="{ width: `${Math.min(100, Math.max(0, progress))}%` }"
        />
      </div>
    </div>
    <Button
      v-if="status === 'failed'"
      variant="soft-danger"
      size="sm"
      @click="emit('retry')"
    >
      <RotateCcw :size="12" aria-hidden="true" />
      Retry
    </Button>
  </div>
</template>
