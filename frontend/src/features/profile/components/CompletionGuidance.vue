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

const ringRadius = 26
const ringCircumference = 2 * Math.PI * ringRadius
const completionOffset = computed(
  () => ringCircumference * (1 - Math.min(100, Math.max(0, score.value)) / 100),
)

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
    class="sticky top-24 overflow-hidden rounded-2xl border border-slate-200/80 bg-white/80 shadow-sm backdrop-blur-md transition-shadow duration-300 hover:shadow-md"
    aria-labelledby="completion-heading"
  >
    <div
      class="border-b border-slate-100/80 bg-gradient-to-br from-white to-slate-50/50 px-5 py-5 sm:px-6"
    >
      <div class="flex items-center gap-4">
        <div class="relative flex shrink-0 items-center justify-center">
          <svg width="64" height="64" viewBox="0 0 64 64" class="-rotate-90 drop-shadow-md">
            <defs>
              <linearGradient id="sidebarRing" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#3b82f6" />
                <stop offset="50%" stop-color="#8b5cf6" />
                <stop offset="100%" stop-color="#6366f1" />
              </linearGradient>
            </defs>
            <circle
              cx="32"
              cy="32"
              :r="ringRadius"
              fill="none"
              stroke="currentColor"
              stroke-width="5"
              class="text-slate-100"
            />
            <circle
              cx="32"
              cy="32"
              :r="ringRadius"
              fill="none"
              stroke="url(#sidebarRing)"
              stroke-width="5"
              stroke-linecap="round"
              :stroke-dasharray="ringCircumference"
              :stroke-dashoffset="completionOffset"
              class="transition-[stroke-dashoffset] duration-1000 motion-reduce:transition-none"
            />
          </svg>
          <span class="absolute text-lg font-bold text-slate-900">
            {{ score }}<span class="text-[11px] font-normal text-slate-400">%</span>
          </span>
        </div>
        <div>
          <h2 id="completion-heading" class="text-sm font-bold text-slate-900">Profile strength</h2>
          <p class="text-xs text-slate-500">
            {{ completedCount }} of {{ totalCount }} areas complete
          </p>
        </div>
      </div>
      <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
        <div
          class="h-full rounded-full bg-gradient-to-r from-primary-500 via-violet-500 to-indigo-500 transition-all duration-700 motion-reduce:transition-none"
          :style="{ width: score + '%' }"
        />
      </div>
    </div>

    <div v-if="recommendation" class="px-5 pt-4 sm:px-6">
      <p class="text-[11px] font-semibold uppercase tracking-widest text-slate-400">
        Recommended next step
      </p>
      <div
        class="mt-2 overflow-hidden rounded-xl border border-indigo-100/60 bg-gradient-to-br from-indigo-50 via-indigo-50/80 to-white p-3.5 shadow-sm"
      >
        <p class="text-sm font-semibold text-slate-900">{{ recommendation.title }}</p>
        <p
          v-if="recommendation.gain"
          class="mt-1 inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700"
        >
          {{ recommendation.gain }} profile completion
        </p>
      </div>
    </div>

    <div class="px-5 pb-5 pt-4 sm:px-6 sm:pb-6">
      <p class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">
        Checklist
      </p>
      <ul class="space-y-2.5">
        <li
          v-for="(area, i) in details.areas"
          :key="area.key"
          class="flex items-start gap-2.5 text-sm transition-all"
          :class="`animate-fade-in-up stagger-${Math.min(i + 1, 8)}`"
        >
          <div class="relative mt-0.5 shrink-0">
            <CheckCircle
              v-if="area.complete"
              :size="17"
              class="text-emerald-500"
              stroke-width="2"
              aria-label="Complete"
            />
            <Circle
              v-else
              :size="17"
              class="text-slate-300 transition-colors duration-200 group-hover:text-slate-400"
              stroke-width="1.5"
              aria-label="Incomplete"
            />
          </div>
          <span
            :class="
              area.complete
                ? 'text-slate-400 line-through decoration-slate-300'
                : 'font-medium text-slate-800'
            "
          >
            {{ areaLabels[area.key] ?? area.key }}
          </span>
        </li>
      </ul>
    </div>
  </section>
</template>
