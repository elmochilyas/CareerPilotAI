<script setup lang="ts">
import Button from '@/components/ui/Button.vue'

withDefaults(
  defineProps<{
    title?: string
    detail?: string | null
    busy?: boolean
  }>(),
  {
    title: 'Could not load the match analysis',
    detail: null,
    busy: false,
  },
)

const emit = defineEmits<{
  retry: []
}>()
</script>

<template>
  <div
    role="alert"
    class="mx-auto max-w-md rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-6 text-center shadow-[var(--shadow-neo-raised)]"
  >
    <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
    <p v-if="detail" class="mt-2 text-sm leading-relaxed text-slate-600">{{ detail }}</p>
    <Button
      variant="primary"
      :disabled="busy"
      :loading="busy"
      class="mt-5"
      @click="emit('retry')"
    >
      Retrying…
    </Button>
  </div>
</template>
