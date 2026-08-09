<script setup lang="ts">
import Button from '@/components/ui/Button.vue'

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
    class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-xl)] bg-[var(--color-warning-50)] px-4 py-3 shadow-[var(--shadow-neo-raised)]"
  >
    <div class="min-w-0 text-sm text-amber-800">
      <p class="font-semibold">Profile or opportunity changed since this analysis</p>
      <p class="mt-0.5 text-xs text-amber-700">
        Recalculate to see an up-to-date match. The previous result stays visible below.
      </p>
    </div>
    <Button variant="warning" size="sm" :disabled="busy" @click="emit('recalculate')">
      {{ busy ? 'Recalculating…' : 'Recalculate' }}
    </Button>
  </div>
</template>
