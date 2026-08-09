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
  border-radius: 999px;
  padding: 0.25rem 0.625rem;
  background: var(--surface-page);
  color: var(--text-muted);
  font-size: 0.75rem;
  font-weight: 620;
  line-height: 1.125rem;
  box-shadow: var(--shadow-neo-raised-sm);
}

.metadata-chip-icon {
  width: 0.875rem;
  height: 0.875rem;
  stroke-width: 2;
}

.metadata-chip-primary {
  background: var(--color-primary-50);
  color: var(--color-primary-600);
}

.metadata-chip-success {
  background: var(--color-success-50);
  color: var(--color-success-600);
}

.metadata-chip-warning {
  background: var(--color-warning-50);
  color: var(--color-warning-600);
}
</style>
