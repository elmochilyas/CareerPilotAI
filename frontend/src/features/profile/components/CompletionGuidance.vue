<script setup lang="ts">
import { CheckCircle, Circle } from '@lucide/vue'
import { computed } from 'vue'
import type { CandidateProfile } from '../types'

const props = defineProps<{ details: CandidateProfile['completion_details'] }>()

const score = computed(() => {
  const total = props.details.areas.reduce((s, a) => s + a.available, 0)
  const earned = props.details.areas.reduce((s, a) => s + a.earned, 0)
  return total > 0 ? Math.round((earned / total) * 100) : 0
})

const recommendation = computed(() => {
  const missing = props.details.missing_areas[0]
  if (!missing) return null
  const labels: Record<string, { title: string; gain: string }> = {
    basic_information: { title: 'Add your basic information', gain: '+10%' },
    headline: { title: 'Add your professional headline', gain: '+10%' },
    professional_summary: { title: 'Tell recruiters about yourself', gain: '+15%' },
    professional_links: { title: 'Connect your professional presence', gain: '+10%' },
    target_roles: { title: 'Define your target roles', gain: '+15%' },
    career_preferences: { title: 'Set your career preferences', gain: '+10%' },
    languages: { title: 'Add your languages', gain: '+10%' },
    education: { title: 'Add your education', gain: '+10%' },
    practical_background: { title: 'Add experience or projects', gain: '+10%' },
  }
  return labels[missing.key] ?? { title: missing.guidance, gain: '' }
})

const areaLabels: Record<string, string> = {
  basic_information: 'Basic information',
  headline: 'Headline',
  professional_summary: 'Summary',
  professional_links: 'Professional links',
  target_roles: 'Target roles',
  career_preferences: 'Career preferences',
  languages: 'Languages',
  education: 'Education',
  practical_background: 'Experience & projects',
}

const completedCount = computed(() => props.details.areas.filter((a) => a.complete).length)
const totalCount = computed(() => props.details.areas.length)
</script>

<template>
  <section
    class="sticky top-24 overflow-hidden rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] shadow-[var(--shadow-sm)]"
    aria-labelledby="completion-heading"
  >
    <div class="border-b border-[var(--border-subtle)] px-5 py-5 sm:px-6">
      <div class="flex items-center gap-4">
        <div class="relative flex shrink-0 items-center justify-center">
          <svg width="64" height="64" viewBox="0 0 64 64" class="-rotate-90">
            <circle
              cx="32"
              cy="32"
              r="26"
              fill="none"
              stroke="currentColor"
              stroke-width="5"
              class="text-[var(--color-neutral-100)]"
            />
            <circle
              cx="32"
              cy="32"
              r="26"
              fill="none"
              stroke="currentColor"
              stroke-width="5"
              stroke-linecap="round"
              :stroke-dasharray="2 * Math.PI * 26"
              :stroke-dashoffset="2 * Math.PI * 26 * (1 - score / 100)"
              class="text-[var(--color-primary-500)] transition-[stroke-dashoffset] duration-700 motion-reduce:transition-none"
            />
          </svg>
          <span class="absolute text-[var(--text-base)] font-bold text-[var(--text-primary)]">
            {{ score
            }}<span class="text-[var(--text-xs)] font-normal text-[var(--text-muted)]">%</span>
          </span>
        </div>
        <div>
          <h2
            id="completion-heading"
            class="text-[var(--text-sm)] font-semibold text-[var(--text-primary)]"
          >
            Profile strength
          </h2>
          <p class="text-[var(--text-xs)] text-[var(--text-secondary)]">
            {{ completedCount }} of {{ totalCount }} areas complete
          </p>
        </div>
      </div>
      <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-[var(--color-neutral-100)]">
        <div
          class="h-full rounded-full bg-[var(--color-primary-500)] transition-[transform] duration-500 motion-reduce:transition-none origin-left"
          :style="{ transform: `scaleX(${score / 100})` }"
        />
      </div>
    </div>

    <div v-if="recommendation" class="px-5 pt-4 sm:px-6">
      <p
        class="text-[var(--text-xs)] font-semibold uppercase tracking-wider text-[var(--text-muted)]"
      >
        Recommended next step
      </p>
      <div
        class="mt-2 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-primary-100)] bg-[var(--color-primary-50)] p-3"
      >
        <p class="text-[var(--text-sm)] font-semibold text-[var(--text-primary)]">
          {{ recommendation.title }}
        </p>
        <p
          v-if="recommendation.gain"
          class="mt-1 inline-flex items-center gap-1 rounded bg-[var(--color-success-100)] px-2 py-0.5 text-[var(--text-xs)] font-medium text-[var(--color-success-700)]"
        >
          {{ recommendation.gain }} profile completion
        </p>
      </div>
    </div>

    <div class="px-5 pb-5 pt-4 sm:px-6 sm:pb-6">
      <p
        class="mb-3 text-[var(--text-xs)] font-semibold uppercase tracking-wider text-[var(--text-muted)]"
      >
        Checklist
      </p>
      <ul class="space-y-2">
        <li
          v-for="(area, i) in details.areas"
          :key="area.key"
          class="flex items-start gap-2 text-[var(--text-sm)] transition-all"
          :class="`animate-fade-in-up stagger-${Math.min(i + 1, 8)}`"
        >
          <div class="relative mt-0.5 shrink-0">
            <CheckCircle
              v-if="area.complete"
              :size="16"
              class="text-[var(--color-success-500)]"
              aria-label="Complete"
            />
            <Circle
              v-else
              :size="16"
              class="text-[var(--color-neutral-300)]"
              aria-label="Incomplete"
            />
          </div>
          <span
            :class="
              area.complete
                ? 'text-[var(--text-muted)] line-through'
                : 'font-medium text-[var(--text-primary)]'
            "
          >
            {{ areaLabels[area.key] ?? area.key }}
          </span>
        </li>
      </ul>
    </div>
  </section>
</template>
