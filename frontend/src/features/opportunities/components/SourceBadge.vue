<script setup lang="ts">
import { FileText } from '@lucide/vue'

withDefaults(
  defineProps<{
    label?: string
    excerpt?: string | null
  }>(),
  {
    label: 'Original description',
    excerpt: null,
  },
)
</script>

<template>
  <details v-if="excerpt" class="source-details">
    <summary class="source-summary">
      <span class="source-badge">
        <FileText class="source-icon" aria-hidden="true" />
        Source: {{ label }}
      </span>
      <span class="source-action">View source</span>
    </summary>
    <blockquote class="source-excerpt">{{ excerpt }}</blockquote>
  </details>

  <span v-else class="source-badge">
    <FileText class="source-icon" aria-hidden="true" />
    Source: {{ label }}
  </span>
</template>

<style scoped>
.source-details {
  margin-top: 0.75rem;
}

.source-summary {
  display: inline-flex;
  min-height: 2.75rem;
  cursor: pointer;
  list-style: none;
  align-items: center;
  gap: 0.625rem;
  border-radius: var(--cp-radius-control);
  padding-right: 0.375rem;
  transition: opacity 170ms ease;
}

.source-summary::-webkit-details-marker {
  display: none;
}

.source-summary:focus-visible {
  outline: 2px solid var(--cp-primary);
  outline-offset: 2px;
}

.source-summary:hover {
  background: var(--cp-primary-soft);
}

.source-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--cp-text-muted);
  font-size: 0.75rem;
  font-weight: 610;
  line-height: 1.125rem;
}

.source-icon {
  width: 0.875rem;
  height: 0.875rem;
  color: var(--cp-primary);
  stroke-width: 2;
}

.source-action {
  color: var(--cp-primary-deep);
  font-size: 0.75rem;
  font-weight: 600;
}

.source-excerpt {
  max-width: 42rem;
  margin-top: 0.5rem;
  border-left: 2px solid var(--cp-primary-bright);
  border-radius: 0 var(--cp-radius-control) var(--cp-radius-control) 0;
  background: var(--cp-surface-violet);
  padding-top: 0.625rem;
  padding-right: 0.75rem;
  padding-bottom: 0.625rem;
  padding-left: 0.75rem;
  color: var(--cp-text-muted);
  font-size: 0.8125rem;
  line-height: 1.375rem;
}

@media (prefers-reduced-motion: reduce) {
  .source-summary {
    transition: none;
  }
}
</style>
