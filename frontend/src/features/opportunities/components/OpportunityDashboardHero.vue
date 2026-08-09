<script setup lang="ts">
import { Plus } from '@lucide/vue'
import { RouterLink } from 'vue-router'
import OpportunityPipelineSummary from './OpportunityPipelineSummary.vue'

defineProps<{
  savedCount: number
  activeCount: number
  attentionCount: number
  loading?: boolean
}>()
</script>

<template>
  <section class="opportunity-header" aria-labelledby="opportunities-title">
    <div class="header-main">
      <div class="header-copy">
        <p class="header-eyebrow">Opportunity workspace</p>
        <h1 id="opportunities-title">Opportunities</h1>
        <p>Keep every role you are considering in one clear, trusted shortlist.</p>
      </div>

      <RouterLink :to="{ name: 'opportunities-import' }" class="header-action">
        <Plus class="size-4" aria-hidden="true" />
        Add opportunity
      </RouterLink>
    </div>

    <OpportunityPipelineSummary
      :saved-count="savedCount"
      :active-count="activeCount"
      :attention-count="attentionCount"
      :loading="loading"
    />
  </section>
</template>

<style scoped>
.opportunity-header {
  border-bottom: 1px solid var(--border-subtle);
  padding: 0.5rem 0 1.75rem;
}

.header-main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 2rem;
}

.header-copy {
  max-width: 42rem;
}

.header-eyebrow {
  color: var(--color-primary-500);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  line-height: 1rem;
  text-transform: uppercase;
}

.header-copy h1 {
  scroll-margin-top: 5rem;
  margin-top: 0.375rem;
  color: var(--text-primary);
  font-size: clamp(2rem, 4vw, 2.75rem);
  font-weight: 720;
  letter-spacing: -0.045em;
  line-height: 1.08;
  text-wrap: balance;
}

.header-copy > p:last-child {
  margin-top: 0.625rem;
  color: var(--text-muted);
  font-size: 0.9375rem;
  line-height: 1.5rem;
}

.header-action {
  display: inline-flex;
  min-height: 2.75rem;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  border-radius: var(--radius-md);
  padding: 0.625rem 0.875rem;
  background: var(--color-primary-500);
  box-shadow: var(--shadow-neo-button);
  color: white;
  font-size: 0.8125rem;
  font-weight: 680;
  text-decoration: none;
  transition:
    background-color 150ms ease,
    box-shadow 150ms ease;
}

.header-action:hover {
  background: var(--color-primary-600);
  box-shadow: var(--shadow-neo-button-pressed);
}

.header-action:focus-visible {
  outline: 2px solid var(--color-primary-500);
  outline-offset: 3px;
}

@media (max-width: 39.999rem) {
  .opportunity-header {
    padding-top: 0;
    padding-bottom: 1.25rem;
  }

  .header-main {
    display: grid;
    gap: 1.25rem;
  }

  .header-action {
    width: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .header-action {
    transition: none;
  }
}
</style>
