<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import { BriefcaseBusiness, Building2, ChevronRight, MapPin } from '@lucide/vue'
import type { JobOpportunity } from '../types'
import { formatDate } from '@/app/utils/date'
import OpportunityStatusBadge from './OpportunityStatusBadge.vue'

const props = defineProps<{
  opportunity: JobOpportunity
  to: RouteLocationRaw
}>()

const location = computed(() =>
  [props.opportunity.city, props.opportunity.country].filter(Boolean).join(', '),
)

const workMode = computed(() => props.opportunity.work_mode?.replaceAll('_', ' '))
const skills = computed(() => props.opportunity.skills ?? [])
const visibleSkills = computed(() => skills.value.slice(0, 3))
const remainingSkillCount = computed(() =>
  Math.max(skills.value.length - visibleSkills.value.length, 0),
)
</script>

<template>
  <RouterLink :to="to" class="opportunity-row">
    <span class="row-icon" aria-hidden="true">
      <Building2 class="size-4" />
    </span>

    <span class="row-content">
      <span class="row-heading">
        <span class="row-title">{{ opportunity.title }}</span>
        <span v-if="opportunity.company_name" class="row-company">
          {{ opportunity.company_name }}
        </span>
      </span>

      <span class="row-meta">
        <span v-if="workMode">
          <BriefcaseBusiness class="size-3.5" aria-hidden="true" />
          {{ workMode }}
        </span>
        <span v-if="location">
          <MapPin class="size-3.5" aria-hidden="true" />
          {{ location }}
        </span>
        <span>Saved {{ formatDate(opportunity.saved_at) }}</span>
      </span>

      <span v-if="visibleSkills.length > 0" class="row-skills" aria-label="Key skills">
        <span v-for="skill in visibleSkills" :key="skill.id">
          {{ skill.original_label }}
        </span>
        <span v-if="remainingSkillCount > 0">+{{ remainingSkillCount }}</span>
      </span>
    </span>

    <span class="row-status">
      <OpportunityStatusBadge status="saved" compact />
    </span>

    <ChevronRight class="row-chevron" aria-hidden="true" />
  </RouterLink>
</template>

<style scoped>
.opportunity-row {
  display: grid;
  min-height: 6.25rem;
  grid-template-columns: 2.5rem minmax(0, 1fr) auto 1.25rem;
  align-items: center;
  gap: 1rem;
  border-radius: var(--radius-xl);
  padding: 1rem 1.125rem;
  background: var(--surface-primary);
  color: inherit;
  text-decoration: none;
  box-shadow: var(--shadow-neo-raised);
  transition:
    box-shadow 150ms ease,
    background-color 150ms ease;
}

.opportunity-row:hover {
  background: var(--surface-secondary);
  box-shadow: var(--shadow-neo-raised-lg);
}

.opportunity-row:focus-visible {
  outline: 2px solid var(--color-primary-500);
  outline-offset: 3px;
}

.row-icon {
  display: grid;
  width: 2.5rem;
  height: 2.5rem;
  place-items: center;
  border-radius: 0.625rem;
  background: var(--color-success-50);
  color: var(--color-success-600);
  box-shadow: var(--shadow-neo-raised-sm);
}

.row-content {
  display: grid;
  min-width: 0;
}

.row-heading {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.25rem 0.625rem;
}

.row-title {
  overflow: hidden;
  color: var(--text-primary);
  font-size: 0.9375rem;
  font-weight: 680;
  letter-spacing: -0.012em;
  line-height: 1.25rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.row-company {
  overflow-wrap: anywhere;
  color: var(--text-muted);
  font-size: 0.8125rem;
  line-height: 1.125rem;
}

.row-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem 0.875rem;
  margin-top: 0.375rem;
  color: var(--text-muted);
  font-size: 0.75rem;
  line-height: 1rem;
  text-transform: capitalize;
}

.row-meta > span {
  display: inline-flex;
  align-items: center;
  gap: 0.3125rem;
}

.row-skills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  margin-top: 0.625rem;
}

.row-skills > span {
  border-radius: 0.375rem;
  padding: 0.1875rem 0.4375rem;
  background: var(--surface-secondary);
  color: var(--text-muted);
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 0.875rem;
  box-shadow: var(--shadow-neo-raised-sm);
}

.row-chevron {
  width: 1rem;
  height: 1rem;
  color: var(--text-faint);
  transition:
    color 150ms ease,
    transform 150ms ease;
}

.opportunity-row:hover .row-chevron {
  color: var(--color-primary-500);
  transform: translateX(0.125rem);
}

@media (max-width: 39.999rem) {
  .opportunity-row {
    grid-template-columns: 2.5rem minmax(0, 1fr) 1rem;
    align-items: start;
    gap: 0.75rem;
    padding: 0.875rem;
  }

  .row-status {
    grid-column: 2 / 3;
    justify-self: start;
  }

  .row-chevron {
    grid-column: 3;
    grid-row: 1 / 3;
    align-self: center;
  }

  .row-title {
    white-space: normal;
  }
}

@media (prefers-reduced-motion: reduce) {
  .opportunity-row,
  .row-chevron {
    transition: none;
  }

  .opportunity-row:hover .row-chevron {
    transform: none;
  }
}
</style>
