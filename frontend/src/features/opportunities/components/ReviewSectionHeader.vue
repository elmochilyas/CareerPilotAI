<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  title: string
  headingId?: string
  description: string
  icon: Component
  itemCount: number
  reviewedCount: number
  hint?: string
}>()
</script>

<template>
  <header class="section-header">
    <div class="section-icon" aria-hidden="true">
      <component :is="icon" />
    </div>
    <div class="section-copy">
      <div class="section-title-row">
        <div>
          <h2 :id="headingId">{{ title }}</h2>
          <p>{{ description }}</p>
        </div>
        <span class="section-count">{{ itemCount }} {{ itemCount === 1 ? 'item' : 'items' }}</span>
      </div>
      <div class="section-meta" aria-live="polite">
        <span>{{ itemCount }} extracted</span>
        <span aria-hidden="true">·</span>
        <span>{{ reviewedCount }} reviewed</span>
        <span v-if="hint" class="section-hint">{{ hint }}</span>
      </div>
    </div>
  </header>
</template>

<style scoped>
.section-header {
  display: flex;
  gap: 1rem;
  border-bottom: 1px solid var(--cp-color-border);
  padding-bottom: 1.5rem;
}

.section-icon {
  display: grid;
  width: 2.75rem;
  height: 2.75rem;
  flex: 0 0 auto;
  place-items: center;
  border: 1px solid var(--cp-color-border);
  border-radius: 0.625rem;
  background: var(--cp-color-primary);
  color: var(--cp-color-primary-foreground);
}

.section-icon :deep(svg) {
  width: 1.125rem;
  height: 1.125rem;
}

.section-copy {
  min-width: 0;
  flex: 1;
}

.section-title-row {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.section-title-row h2 {
  color: var(--cp-color-foreground);
  font-size: 1.375rem;
  font-weight: 690;
  letter-spacing: -0.025em;
  line-height: 1.875rem;
}

.section-title-row p {
  max-width: 42rem;
  margin-top: 0.25rem;
  color: var(--cp-color-muted-foreground);
  font-size: 0.875rem;
  line-height: 1.375rem;
}

.section-count {
  align-self: flex-start;
  border: 1px solid var(--cp-color-border);
  border-radius: 999px;
  padding: 0.25rem 0.625rem;
  background: var(--cp-color-accent);
  color: var(--cp-color-muted-foreground);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  font-weight: 650;
  white-space: nowrap;
}

.section-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
  margin-top: 0.75rem;
  color: var(--cp-color-muted-foreground);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  font-weight: 570;
}

.section-hint {
  flex-basis: 100%;
  color: var(--cp-color-info);
}

@media (min-width: 40rem) {
  .section-title-row {
    flex-direction: row;
    align-items: flex-start;
    justify-content: space-between;
  }
}
</style>
