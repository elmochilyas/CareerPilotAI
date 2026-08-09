<script setup lang="ts">
import { Briefcase, GraduationCap, Folder, Award, Calendar, ExternalLink } from '@lucide/vue'
import { computed } from 'vue'
import type { ProfileItem } from '../types'

const props = defineProps<{
  item: ProfileItem
  variant?: 'default' | 'timeline' | 'academic' | 'grid' | 'achievement'
}>()

function dateDisplay(start?: string | null, end?: string | null): string {
  if (!start && !end) return ''
  const fmt = (d: string) => {
    const date = new Date(d + 'T00:00:00')
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' })
  }
  const s = start ? fmt(start) : null
  const e = end ? fmt(end) : 'Present'
  return [s, e].filter(Boolean).join(' \u2014 ')
}

const metadata = computed(() => {
  const meta = props.item.metadata ?? {}
  const lines: { label: string; value: string; url?: string }[] = []
  if (meta.degree && props.item.type === 'education') {
    lines.push({ label: 'Degree', value: String(meta.degree) })
  }
  if (meta.field && props.item.type === 'education') {
    lines.push({ label: 'Field', value: String(meta.field) })
  }
  if (meta.issuer && props.item.type === 'certification') {
    lines.push({ label: 'Issuer', value: String(meta.issuer) })
  }
  if (meta.credential_url && props.item.type === 'certification') {
    lines.push({
      label: 'Credential',
      value: String(meta.credential_url),
      url: String(meta.credential_url),
    })
  }
  if (meta.expiry_date && props.item.type === 'certification') {
    const exp = new Date(String(meta.expiry_date) + 'T00:00:00')
    lines.push({
      label: 'Expires',
      value: exp.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }),
    })
  }
  if (meta.project_url && props.item.type === 'project') {
    lines.push({
      label: 'Project URL',
      value: String(meta.project_url),
      url: String(meta.project_url),
    })
  }
  if (meta.repository_url && props.item.type === 'project') {
    lines.push({
      label: 'Repository',
      value: String(meta.repository_url),
      url: String(meta.repository_url),
    })
  }
  return lines
})

const isCurrent = computed(() => {
  if (props.item.type === 'certification') return false
  return !props.item.end_date
})

const isTimeline = props.variant === 'timeline' || props.variant === 'academic'
const isGrid = props.variant === 'grid'
const isAchievement = props.variant === 'achievement'

const timelineDotColor = 'bg-[var(--color-primary-500)]'

const typeIcon: Record<string, object> = {
  experience: Briefcase,
  education: GraduationCap,
  project: Folder,
  certification: Award,
}

const iconColor: Record<string, string> = {
  experience: 'bg-[var(--color-primary-100)] text-[var(--color-primary-600)]',
  education: 'bg-[var(--color-primary-100)] text-[var(--color-primary-600)]',
  project: 'bg-[var(--color-primary-100)] text-[var(--color-primary-600)]',
  certification: 'bg-[var(--color-primary-100)] text-[var(--color-primary-600)]',
}
</script>

<template>
  <!-- Timeline variant -->
  <article v-if="isTimeline" class="relative flex gap-4">
    <div class="relative flex shrink-0 flex-col items-center pt-2">
      <div
        class="size-3 shrink-0 rounded-full ring-4 ring-[var(--surface-page)]"
        :class="timelineDotColor"
      />
    </div>

    <div
      class="min-w-0 flex-1 rounded-xl bg-[var(--surface-primary)] p-4 shadow-[var(--shadow-neo-raised)]"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h3 class="text-sm font-semibold text-[var(--text-primary)]">
            {{ item.title }}
          </h3>
          <p class="mt-0.5 text-sm text-[var(--text-secondary)]">
            {{ item.organization }}
            <span v-if="item.organization && item.location" class="mx-1 text-[var(--text-muted)]"
              >\u00b7</span
            >
            <span v-if="item.location">{{ item.location }}</span>
          </p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <slot name="actions" />
        </div>
      </div>

      <div class="mt-3 flex flex-wrap items-center gap-2">
        <span
          v-if="dateDisplay(item.start_date, item.end_date)"
          class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-primary-50)] px-2.5 py-1 text-xs font-medium text-[var(--color-primary-700)]"
        >
          <Calendar :size="12" />
          {{ dateDisplay(item.start_date, item.end_date) }}
        </span>
        <span
          v-if="isCurrent"
          class="inline-flex items-center rounded-lg bg-[var(--color-primary-500)] px-2 py-0.5 text-xs font-semibold text-white"
        >
          Current
        </span>
      </div>

      <p v-if="item.description" class="mt-3 text-sm leading-relaxed text-[var(--text-secondary)]">
        {{ item.description }}
      </p>

      <div v-if="metadata.length" class="mt-3 space-y-0.5">
        <p v-for="(line, i) in metadata" :key="i" class="text-xs text-[var(--text-secondary)]">
          <span class="font-semibold text-[var(--text-primary)]">{{ line.label }}:</span>
          <a
            v-if="line.url"
            :href="line.url"
            target="_blank"
            rel="noopener noreferrer"
            class="ml-1 inline-flex items-center gap-0.5 text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)]"
          >
            {{ line.value }} <ExternalLink :size="10" />
          </a>
          <span v-else class="ml-1">{{ line.value }}</span>
        </p>
      </div>
    </div>
  </article>

  <!-- Grid variant (projects) -->
  <article
    v-else-if="isGrid"
    class="overflow-hidden rounded-xl bg-[var(--surface-primary)] p-4 shadow-[var(--shadow-neo-raised-sm)] transition-shadow hover:shadow-[var(--shadow-neo-raised)]"
  >
    <div class="flex items-start justify-between gap-2">
      <div class="flex items-start gap-3 min-w-0">
        <div
          class="flex size-10 shrink-0 items-center justify-center rounded-lg shadow-[var(--shadow-neo-inset)]"
          :class="
            iconColor[item.type] ?? 'bg-[var(--color-neutral-100)] text-[var(--color-neutral-600)]'
          "
        >
          <component :is="typeIcon[item.type]" :size="18" />
        </div>
        <div class="min-w-0">
          <h3 class="truncate text-sm font-semibold text-[var(--text-primary)]">
            {{ item.title }}
          </h3>
          <p v-if="item.organization" class="mt-0.5 text-xs text-[var(--text-secondary)]">
            {{ item.organization }}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-1 shrink-0">
        <slot name="actions" />
      </div>
    </div>

    <div v-if="dateDisplay(item.start_date, item.end_date)" class="mt-3">
      <span
        class="inline-flex items-center gap-1 rounded-lg bg-[var(--surface-secondary)] px-2 py-0.5 text-xs font-medium text-[var(--text-secondary)]"
      >
        <Calendar :size="11" />
        {{ dateDisplay(item.start_date, item.end_date) }}
      </span>
    </div>

    <p
      v-if="item.description"
      class="mt-3 text-xs leading-relaxed text-[var(--text-secondary)] line-clamp-3"
    >
      {{ item.description }}
    </p>

    <div v-if="metadata.length" class="mt-3 flex flex-wrap gap-1.5">
      <template v-for="(line, i) in metadata" :key="i">
        <a
          v-if="line.url"
          :href="line.url"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1 rounded-lg bg-[var(--surface-secondary)] px-2.5 py-1 text-xs font-medium text-[var(--text-primary)] transition-all hover:bg-[var(--color-primary-50)] hover:text-[var(--color-primary-700)]"
        >
          {{ line.label === 'Project URL' ? 'Live demo' : 'Repository' }}
        </a>
      </template>
    </div>
  </article>

  <!-- Achievement variant (certifications) -->
  <article
    v-else-if="isAchievement"
    class="flex items-start gap-4 overflow-hidden rounded-xl bg-[var(--surface-primary)] p-4 shadow-[var(--shadow-neo-raised-sm)] transition-shadow hover:shadow-[var(--shadow-neo-raised)]"
  >
    <div
      class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--color-primary-100)] text-[var(--color-primary-600)] shadow-[var(--shadow-neo-inset)]"
    >
      <Award :size="18" />
    </div>
    <div class="min-w-0 flex-1">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-semibold text-[var(--text-primary)]">
            {{ item.title }}
          </h3>
          <p class="mt-0.5 text-xs text-[var(--text-secondary)]">
            {{ item.organization }}
            <span
              v-if="item.organization && metadata.find((m) => m.label === 'Issuer')"
              class="mx-1"
              >\u00b7</span
            >
            <span
              v-if="metadata.find((m) => m.label === 'Issuer')"
              class="rounded-lg bg-[var(--surface-secondary)] px-1.5 py-0.5 font-medium text-[var(--text-secondary)]"
            >
              {{ metadata.find((m) => m.label === 'Issuer')?.value }}
            </span>
          </p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
          <slot name="actions" />
        </div>
      </div>
      <div v-if="dateDisplay(item.start_date, null)" class="mt-2">
        <span class="inline-flex items-center gap-1 text-xs text-[var(--text-secondary)]">
          <Calendar :size="12" />
          Issued {{ dateDisplay(item.start_date, null) }}
        </span>
      </div>
      <div v-if="metadata.filter((m) => m.label === 'Credential').length" class="mt-2.5">
        <a
          v-for="(line, i) in metadata.filter((m) => m.label === 'Credential')"
          :key="i"
          :href="line.url"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1.5 rounded-xl bg-[var(--color-primary-50)] px-3 py-1.5 text-xs font-semibold text-[var(--color-primary-700)] transition-all hover:bg-[var(--color-primary-100)]"
        >
          <ExternalLink :size="12" />
          View credential
        </a>
      </div>
      <div v-if="metadata.filter((m) => m.label === 'Expires').length" class="mt-1.5">
        <p
          v-for="(line, i) in metadata.filter((m) => m.label === 'Expires')"
          :key="i"
          class="inline-flex items-center gap-1 text-xs text-[var(--color-primary-600)]"
        >
          <Calendar :size="11" />
          {{ line.label }}: {{ line.value }}
        </p>
      </div>
    </div>
  </article>

  <!-- Default card variant -->
  <article
    v-else
    class="overflow-hidden rounded-xl bg-[var(--surface-primary)] p-4 shadow-[var(--shadow-neo-raised-sm)]"
  >
    <div class="flex items-start gap-3">
      <div
        class="flex size-10 shrink-0 items-center justify-center rounded-lg shadow-[var(--shadow-neo-inset)]"
        :class="
          iconColor[item.type] ?? 'bg-[var(--color-neutral-100)] text-[var(--color-neutral-600)]'
        "
      >
        <component :is="typeIcon[item.type]" :size="18" />
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div>
            <h3 class="text-sm font-semibold text-[var(--text-primary)]">
              {{ item.title }}
            </h3>
            <p class="mt-0.5 text-xs text-[var(--text-secondary)]">
              {{ [item.organization, item.location].filter(Boolean).join(' \u00b7 ') }}
            </p>
          </div>
          <div class="flex items-center gap-1"><slot name="actions" /></div>
        </div>

        <p v-if="dateDisplay(item.start_date, item.end_date)" class="mt-2">
          <span
            class="inline-flex items-center gap-1.5 rounded-lg bg-[var(--surface-secondary)] px-2.5 py-0.5 text-xs font-medium text-[var(--text-secondary)]"
          >
            <Calendar :size="11" />
            {{ dateDisplay(item.start_date, item.end_date) }}
          </span>
        </p>

        <div v-if="metadata.length" class="mt-2.5 space-y-0.5">
          <p v-for="(line, i) in metadata" :key="i" class="text-xs text-[var(--text-secondary)]">
            <span class="font-semibold text-[var(--text-primary)]">{{ line.label }}:</span>
            <a
              v-if="line.url"
              :href="line.url"
              target="_blank"
              rel="noopener noreferrer"
              class="ml-1 inline-flex items-center gap-0.5 text-[var(--color-primary-600)] hover:text-[var(--color-primary-700)]"
            >
              {{ line.value }} <ExternalLink :size="10" />
            </a>
            <span v-else class="ml-1">{{ line.value }}</span>
          </p>
        </div>

        <p
          v-if="item.description"
          class="mt-2 text-sm leading-relaxed text-[var(--text-secondary)]"
        >
          {{ item.description }}
        </p>
      </div>
    </div>
  </article>
</template>
