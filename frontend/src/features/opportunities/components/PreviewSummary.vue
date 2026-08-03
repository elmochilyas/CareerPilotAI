<script setup lang="ts">
import { computed } from 'vue'
import type { PreviewData } from '../types'

const props = defineProps<{
  preview: PreviewData
}>()

const d = computed(() => props.preview.data)

function isNonEmpty(obj: Record<string, unknown> | null | undefined): boolean {
  if (!obj) return false
  return Object.keys(obj).length > 0
}

function entries(record: Record<string, unknown>): Array<[string, unknown]> {
  return Object.entries(record).filter(([, v]) => v !== null && v !== undefined && v !== '')
}

function renderValue(v: unknown): string {
  if (v === null || v === undefined) return ''
  if (typeof v === 'boolean') return v ? 'Yes' : 'No'
  return String(v)
}
</script>

<template>
  <div class="space-y-4">
    <h2 class="text-lg font-medium">Job Preview</h2>

    <div
      v-if="d.warnings.length"
      class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700"
    >
      <p v-for="(w, i) in d.warnings" :key="i">{{ w }}</p>
    </div>

    <section v-if="isNonEmpty(d.overview)">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">Overview</h3>
      <div class="space-y-1">
        <p v-for="[key, val] in entries(d.overview)" :key="key" class="text-sm">
          <span class="text-gray-400">{{ key.replace(/_/g, ' ') }}:</span>
          {{ renderValue(val) }}
        </p>
      </div>
    </section>

    <section v-if="isNonEmpty(d.work_details)">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">Work Details</h3>
      <div class="space-y-1">
        <p v-for="[key, val] in entries(d.work_details)" :key="key" class="text-sm">
          <span class="text-gray-400">{{ key.replace(/_/g, ' ') }}:</span>
          {{ renderValue(val) }}
        </p>
      </div>
    </section>

    <section v-if="d.responsibilities.length">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">
        Responsibilities
      </h3>
      <ul class="list-inside list-disc space-y-1 text-sm">
        <li v-for="r in d.responsibilities" :key="r.id">{{ r.text }}</li>
      </ul>
    </section>

    <section v-if="d.required_experience.length">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">
        Required Experience
      </h3>
      <ul class="list-inside list-disc space-y-1 text-sm">
        <li v-for="e in d.required_experience" :key="e.id">
          {{ e.summary }}<span v-if="e.years !== null"> ({{ e.years }}+ years)</span>
        </li>
      </ul>
    </section>

    <section v-if="d.preferred_experience.length">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">
        Preferred Experience
      </h3>
      <ul class="list-inside list-disc space-y-1 text-sm">
        <li v-for="e in d.preferred_experience" :key="e.id">
          {{ e.summary }}<span v-if="e.years !== null"> ({{ e.years }}+ years)</span>
        </li>
      </ul>
    </section>

    <section v-if="d.education.length">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">Education</h3>
      <ul class="list-inside list-disc space-y-1 text-sm">
        <li v-for="edu in d.education" :key="edu.id">
          {{ edu.degree }}<span v-if="edu.field"> in {{ edu.field }}</span>
          <span v-if="edu.required === true"> (required)</span>
          <span v-else-if="edu.required === false"> (preferred)</span>
        </li>
      </ul>
    </section>

    <section v-if="d.required_skills.length">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">
        Required Skills
      </h3>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="s in d.required_skills"
          :key="s.id"
          class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700"
        >
          {{ s.label }}
          <template v-if="s.proficiency"> - {{ s.proficiency }}</template>
          <template v-if="s.years_experience !== null"> ({{ s.years_experience }}y)</template>
        </span>
      </div>
    </section>

    <section v-if="d.preferred_skills.length">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">
        Preferred Skills
      </h3>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="s in d.preferred_skills"
          :key="s.id"
          class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700"
        >
          {{ s.label }}
          <template v-if="s.proficiency"> - {{ s.proficiency }}</template>
          <template v-if="s.years_experience !== null"> ({{ s.years_experience }}y)</template>
        </span>
      </div>
    </section>

    <section v-if="d.languages_certifications.length">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">
        Languages & Certifications
      </h3>
      <ul class="list-inside list-disc space-y-1 text-sm">
        <li v-for="lc in d.languages_certifications" :key="lc.id">
          {{ lc.name }}<span v-if="lc.proficiency"> ({{ lc.proficiency }})</span>
          <span v-if="lc.required === true"> - required</span>
        </li>
      </ul>
    </section>

    <section v-if="d.compensation">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">Compensation</h3>
      <div class="space-y-1 text-sm">
        <p v-for="[key, val] in entries(d.compensation)" :key="key">
          <span class="text-gray-400">{{ key.replace(/_/g, ' ') }}:</span>
          {{ renderValue(val) }}
        </p>
      </div>
    </section>

    <section v-if="isNonEmpty(d.dates)">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">Dates</h3>
      <div class="space-y-1 text-sm">
        <p v-for="[key, val] in entries(d.dates)" :key="key">
          <span class="text-gray-400">{{ key.replace(/_/g, ' ') }}:</span>
          {{ renderValue(val) }}
        </p>
      </div>
    </section>

    <section v-if="d.excluded.length">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">Excluded</h3>
      <ul class="list-inside list-disc space-y-1 text-sm text-gray-500">
        <li v-for="ex in d.excluded" :key="ex.id">{{ ex.type.replace(/_/g, ' ') }}</li>
      </ul>
    </section>

    <section v-if="d.unknown_skills.length">
      <h3 class="mb-2 text-sm font-medium text-gray-500 uppercase tracking-wide">Unknown Skills</h3>
      <ul class="list-inside list-disc space-y-1 text-sm text-amber-600">
        <li v-for="us in d.unknown_skills" :key="us.id">{{ us.type.replace(/_/g, ' ') }}</li>
      </ul>
    </section>
  </div>
</template>
