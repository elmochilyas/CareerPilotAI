<script setup lang="ts">
import { FileUp, Briefcase, User } from '@lucide/vue'

interface Action {
  label: string
  description: string
  icon: typeof FileUp
  href: string
  color: string
}

const actions: Action[] = [
  {
    label: 'Import CV',
    description: 'Upload and parse your resume to build your profile',
    icon: FileUp,
    href: '/cv',
    color: 'bg-[var(--color-primary-50)] text-[var(--color-primary-600)]',
  },
  {
    label: 'Add Opportunity',
    description: 'Save a job posting for matching and tracking',
    icon: Briefcase,
    href: '/opportunities/import',
    color: 'bg-[var(--color-success-50)] text-[var(--color-success-600)]',
  },
  {
    label: 'View Profile',
    description: 'Complete your profile to improve match accuracy',
    icon: User,
    href: '/profile',
    color: 'bg-[var(--color-warning-50)] text-[var(--color-warning-600)]',
  },
]
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-3">
    <router-link
      v-for="action in actions"
      :key="action.label"
      :to="action.href"
      class="group flex items-start gap-4 rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-5 shadow-[var(--shadow-neo-raised)] transition-all duration-[120ms] hover:shadow-[var(--shadow-neo-raised-lg)]"
    >
      <div
        class="flex size-10 shrink-0 items-center justify-center rounded-[var(--radius-md)] shadow-[var(--shadow-neo-inset)] transition-transform group-hover:scale-110"
        :class="action.color"
      >
        <component :is="action.icon" class="size-5" aria-hidden="true" />
      </div>
      <div class="min-w-0">
        <p
          class="text-[var(--text-sm)] font-semibold text-[var(--text-primary)] group-hover:text-[var(--color-primary-700)]"
        >
          {{ action.label }}
        </p>
        <p class="mt-0.5 text-[var(--text-xs)] leading-relaxed text-[var(--text-tertiary)]">
          {{ action.description }}
        </p>
      </div>
    </router-link>
  </div>
</template>
