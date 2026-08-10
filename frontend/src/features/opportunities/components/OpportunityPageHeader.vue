<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'

defineProps<{
  title: string
  description?: string
  backLabel?: string
}>()

const emit = defineEmits<{
  back: []
}>()
</script>

<template>
  <header class="opportunity-header">
    <button v-if="backLabel" type="button" class="opportunity-back-link" @click="emit('back')">
      <ArrowLeft class="size-4" aria-hidden="true" />
      {{ backLabel }}
    </button>

    <div class="opportunity-header-grid">
      <div class="opportunity-header-copy">
        <h1 class="opportunity-title">{{ title }}</h1>
        <p v-if="description" class="opportunity-description">
          {{ description }}
        </p>
        <div v-if="$slots.meta" class="opportunity-meta">
          <slot name="meta" />
        </div>
      </div>

      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-3 lg:justify-end">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>

<style scoped>
.opportunity-header {
  border-bottom: 1px solid var(--border-default);
  padding: 0.25rem 0 1.5rem;
}

.opportunity-header-grid {
  display: grid;
  gap: 1.25rem;
}

.opportunity-header-copy {
  min-width: 0;
}

.opportunity-title {
  max-width: 56rem;
  overflow-wrap: anywhere;
  color: var(--text-primary);
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: -0.025em;
  line-height: 2.25rem;
  text-wrap: pretty;
}

.opportunity-description {
  margin-top: 0.375rem;
  max-width: 72ch;
  color: var(--text-muted);
  font-size: 0.875rem;
  line-height: 1.375rem;
  text-wrap: pretty;
}

.opportunity-meta {
  margin-top: 0.625rem;
  color: var(--text-muted);
  font-size: 0.8125rem;
  line-height: 1.25rem;
}

.opportunity-back-link {
  display: inline-flex;
  min-height: 2.75rem;
  align-items: center;
  gap: 0.45rem;
  margin: -0.25rem 0 0.5rem;
  border-radius: var(--radius-md);
  color: var(--text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.25rem;
}

.opportunity-back-link:hover {
  color: var(--color-primary-700);
}

.opportunity-back-link:focus-visible {
  outline: 2px solid var(--color-primary-600);
  outline-offset: 3px;
}

@media (min-width: 64rem) {
  .opportunity-header-grid {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
  }

  .opportunity-title {
    font-size: 2rem;
    line-height: 2.5rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .opportunity-back-link {
    transition: none;
  }
}
</style>
