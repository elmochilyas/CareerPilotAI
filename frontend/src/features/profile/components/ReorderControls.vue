<script setup lang="ts">
import { ChevronUp, ChevronDown } from '@lucide/vue'
defineProps<{ first: boolean; last: boolean }>()
defineEmits<{ up: []; down: [] }>()
</script>

<template>
  <nav aria-label="Reorder item" class="flex gap-1">
    <button
      type="button"
      :disabled="first"
      class="flex min-h-8 min-w-8 cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 active:scale-[0.92] disabled:cursor-not-allowed disabled:opacity-30"
      aria-label="Move up"
      @click="$emit('up')"
    >
      <ChevronUp :size="14" stroke-width="2" />
    </button>
    <button
      type="button"
      :disabled="last"
      class="flex min-h-8 min-w-8 cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 active:scale-[0.92] disabled:cursor-not-allowed disabled:opacity-30"
      aria-label="Move down"
      @click="$emit('down')"
    >
      <ChevronDown :size="14" stroke-width="2" />
    </button>
  </nav>
</template>
