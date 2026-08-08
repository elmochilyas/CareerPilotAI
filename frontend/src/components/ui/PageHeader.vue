<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
      <h1 class="text-2xl font-bold tracking-tight text-slate-900">
        {{ title }}
      </h1>
      <p v-if="description && !$slots.description" class="mt-1 text-sm text-slate-500">
        {{ description }}
      </p>
      <slot v-else name="description" />
    </div>
    <div v-if="$slots.default" class="flex shrink-0 items-center gap-2">
      <slot />
    </div>
  </div>
</template>
