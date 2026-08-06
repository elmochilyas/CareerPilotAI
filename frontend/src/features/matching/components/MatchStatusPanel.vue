<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  status: 'queued' | 'processing'
}>()

const label = computed(() =>
  props.status === 'processing' ? 'Analyzing your match…' : 'Match analysis queued…',
)
</script>

<template>
  <div
    role="status"
    aria-live="polite"
    class="flex flex-col items-center gap-3 rounded-xl border border-slate-200 bg-white px-6 py-10 text-center"
  >
    <div
      class="size-8 animate-spin rounded-full border-2 border-slate-200 border-t-primary-600 motion-reduce:animate-none"
      aria-hidden="true"
    />
    <div>
      <p class="text-sm font-semibold text-slate-800">{{ label }}</p>
      <p class="mt-1 text-xs text-slate-500">
        This can take a minute. The page refreshes automatically when it's ready.
      </p>
    </div>
  </div>
</template>
