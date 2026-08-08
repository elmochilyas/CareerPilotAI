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
    href: '/profile/cv',
    color: 'bg-primary-50 text-primary-600',
  },
  {
    label: 'Add Opportunity',
    description: 'Save a job posting for matching and tracking',
    icon: Briefcase,
    href: '/opportunities/import',
    color: 'bg-emerald-50 text-emerald-600',
  },
  {
    label: 'View Profile',
    description: 'Complete your profile to improve match accuracy',
    icon: User,
    href: '/profile',
    color: 'bg-amber-50 text-amber-600',
  },
]
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-3">
    <router-link
      v-for="action in actions"
      :key="action.label"
      :to="action.href"
      class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 transition-all hover:border-primary-200 hover:shadow-md"
    >
      <div
        class="flex size-10 shrink-0 items-center justify-center rounded-lg transition-transform group-hover:scale-110"
        :class="action.color"
      >
        <component :is="action.icon" class="size-5" aria-hidden="true" />
      </div>
      <div class="min-w-0">
        <p class="text-sm font-semibold text-slate-900 group-hover:text-primary-700">
          {{ action.label }}
        </p>
        <p class="mt-0.5 text-xs leading-relaxed text-slate-500">{{ action.description }}</p>
      </div>
    </router-link>
  </div>
</template>
