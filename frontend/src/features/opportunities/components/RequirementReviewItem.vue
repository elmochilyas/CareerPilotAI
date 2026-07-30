<script setup lang="ts">
import { shallowRef } from 'vue'
import type { Component } from 'vue'
import MetadataChip from './MetadataChip.vue'
import ReviewDecisionControl from './ReviewDecisionControl.vue'
import SourceBadge from './SourceBadge.vue'
import type { JobSuggestion } from '../types'

const props = withDefaults(
  defineProps<{
    suggestion: JobSuggestion
    category: string
    title: string
    badges?: string[]
    icon?: Component
    sourceLabel?: string
    saving?: boolean
  }>(),
  {
    badges: () => [],
    icon: undefined,
    sourceLabel: 'Requirements section',
    saving: false,
  },
)

const emit = defineEmits<{
  keep: [suggestionId: number]
  exclude: [suggestionId: number]
  restore: [suggestionId: number]
}>()

const isChanging = shallowRef(false)

function keepRequirement(): void {
  emit('keep', props.suggestion.id)
  isChanging.value = false
}

function excludeRequirement(): void {
  emit('exclude', props.suggestion.id)
  isChanging.value = false
}
</script>

<template>
  <article
    class="requirement-item"
    :class="{
      'requirement-item-complete': suggestion.review_decision !== 'pending',
      'requirement-item-included':
        suggestion.review_decision === 'accepted' || suggestion.review_decision === 'resolved',
      'requirement-item-excluded':
        suggestion.review_decision === 'rejected' || suggestion.review_decision === 'keep_blank',
    }"
  >
    <div class="requirement-main">
      <div class="requirement-category">
        <component :is="icon" v-if="icon" aria-hidden="true" />
        <span>{{ category }}</span>
      </div>
      <p class="requirement-title">{{ title }}</p>
      <div v-if="badges.length" class="requirement-badges">
        <MetadataChip v-for="badge in badges" :key="badge" :label="badge" />
      </div>
      <SourceBadge :label="sourceLabel" :excerpt="suggestion.source_evidence" />
    </div>

    <ReviewDecisionControl
      :decision="suggestion.review_decision"
      :expanded="isChanging"
      :saving="saving"
      :allow-edit="false"
      @keep="keepRequirement"
      @exclude="excludeRequirement"
      @restore="emit('restore', suggestion.id)"
      @change="isChanging = true"
    />
  </article>
</template>

<style scoped>
.requirement-item {
  position: relative;
  border: 1px solid transparent;
  border-radius: var(--cp-radius-xl);
  padding: 1rem;
  background: var(--cp-color-card);
  transition: all var(--cp-duration-fast) var(--cp-ease-out);
}

.requirement-item::before {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  width: 3px;
  background: var(--cp-color-border);
  content: '';
}

.requirement-item:hover {
  border-color: var(--cp-color-border);
}

.requirement-item-included {
  background: var(--cp-color-success-soft);
}

.requirement-item-included::before {
  background: var(--cp-color-success);
}

.requirement-item-excluded {
  background: var(--cp-color-accent);
}

.requirement-item-excluded::before {
  background: var(--cp-color-danger);
}

.requirement-item-excluded .requirement-main {
  opacity: 0.6;
}

.requirement-main {
  min-width: 0;
}

.requirement-category {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--cp-color-foreground);
  font-size: 0.75rem;
  font-weight: 650;
  line-height: 1.125rem;
}

.requirement-category svg {
  width: 0.875rem;
  height: 0.875rem;
  color: var(--cp-color-primary);
}

.requirement-title {
  margin-top: 0.5rem;
  overflow-wrap: anywhere;
  color: var(--cp-color-foreground);
  font-size: 1rem;
  font-weight: 610;
  letter-spacing: -0.008em;
  line-height: 1.5rem;
}

.requirement-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.75rem;
}

.requirement-item :deep(.decision-actions),
.requirement-item :deep(.decision-summary) {
  margin-top: 1rem;
}

@media (min-width: 48rem) {
  .requirement-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 1.5rem;
    padding: 1.25rem;
  }

  .requirement-item :deep(.decision-actions),
  .requirement-item :deep(.decision-summary) {
    margin-top: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .requirement-item {
    transition: none;
  }
}
</style>
