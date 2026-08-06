<script setup lang="ts">
withDefaults(
  defineProps<{
    busy?: boolean
  }>(),
  { busy: false },
)

const emit = defineEmits<{
  recalculate: []
}>()
</script>

<template>
  <div
    role="status"
    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3"
  >
    <div class="min-w-0 text-sm text-amber-800">
      <p class="font-semibold">Profile or opportunity changed since this analysis</p>
      <p class="mt-0.5 text-xs text-amber-700">
        Recalculate to see an up-to-date match. The previous result stays visible below.
      </p>
    </div>
    <button
      type="button"
      :disabled="busy"
      class="inline-flex min-h-9 items-center justify-center rounded-lg bg-amber-700 px-3.5 text-sm font-medium text-white hover:bg-amber-800 focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
      @click="emit('recalculate')"
    >
      {{ busy ? 'Recalculating…' : 'Recalculate' }}
    </button>
  </div>
</template>
