<script setup lang="ts">
import type { Component } from 'vue'

withDefaults(
  defineProps<{
    label: string
    icon?: Component
    tone?: 'neutral' | 'primary' | 'success' | 'warning'
  }>(),
  {
    icon: undefined,
    tone: 'neutral',
  },
)
</script>

<template>
  <span class="metadata-chip" :class="`metadata-chip-${tone}`">
    <component :is="icon" v-if="icon" class="metadata-chip-icon" aria-hidden="true" />
    {{ label }}
  </span>
</template>

<style scoped>
.metadata-chip {
  display: inline-flex;
  min-height: 1.75rem;
  align-items: center;
  gap: 0.375rem;
  border: 1px solid color-mix(in srgb, var(--cp-border) 76%, var(--cp-text-faint));
  border-radius: 999px;
  padding: 0.25rem 0.625rem;
  background: linear-gradient(180deg, var(--cp-surface-lifted), var(--cp-surface-subtle));
  color: var(--cp-text-muted);
  font-size: 0.75rem;
  font-weight: 620;
  line-height: 1.125rem;
  box-shadow:
    0 1px 0 rgb(255 255 255 / 0.9) inset,
    0 0.125rem 0.375rem rgb(16 24 40 / 0.035);
}

.metadata-chip-icon {
  width: 0.875rem;
  height: 0.875rem;
  stroke-width: 2;
}

.metadata-chip-primary {
  border-color: var(--cp-primary-border);
  background: linear-gradient(180deg, var(--cp-surface-violet), var(--cp-primary-soft));
  color: var(--cp-primary-hover);
}

.metadata-chip-success {
  border-color: var(--cp-success-border);
  background: var(--cp-success-soft);
  color: var(--cp-success);
}

.metadata-chip-warning {
  border-color: var(--cp-warning-border);
  background: var(--cp-warning-soft);
  color: var(--cp-warning);
}
</style>
