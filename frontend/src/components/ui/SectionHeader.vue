<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
      <h2 class="text-lg font-semibold tracking-tight text-slate-900">
        {{ title }}
      </h2>
      <p v-if="description" class="mt-0.5 text-sm text-slate-500">
        {{ description }}
      </p>
    </div>
    <div v-if="$slots.default" class="flex shrink-0 items-center gap-2">
      <slot />
    </div>
  </div>
</template>
