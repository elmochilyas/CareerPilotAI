<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
      <h1
        class="text-[var(--text-2xl)] font-semibold tracking-[var(--tracking-tight)] text-[var(--text-primary)]"
      >
        {{ title }}
      </h1>
      <p
        v-if="description && !$slots.description"
        class="mt-1 text-sm text-[var(--text-secondary)]"
      >
        {{ description }}
      </p>
      <slot v-else name="description" />
    </div>
    <div v-if="$slots.default" class="flex shrink-0 items-center gap-2">
      <slot />
    </div>
  </div>
</template>
