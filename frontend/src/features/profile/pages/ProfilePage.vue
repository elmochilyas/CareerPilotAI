<script setup lang="ts">
import { AlertCircle, User, Briefcase, Folder, Award, Wrench } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import type { ProfileItem, ProfileItemInput, ProfileItemType, ProfileUpdate } from '../types'
import { useProfile } from '../composables/useProfile'
import ProfileHeader from '../components/ProfileHeader.vue'
import ProfessionalSummary from '../components/ProfessionalSummary.vue'
import CareerPreferences from '../components/CareerPreferences.vue'
import ProfessionalLinks from '../components/ProfessionalLinks.vue'
import ExperienceTimeline from '../components/ExperienceTimeline.vue'
import EducationSection from '../components/EducationSection.vue'
import ProjectsSection from '../components/ProjectsSection.vue'
import CertificationsSection from '../components/CertificationsSection.vue'
import SkillsSection from '@/features/skills/components/SkillsSection.vue'
import Tabs from '@/components/ui/Tabs.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import Toast from '@/components/ui/Toast.vue'
import { useToast } from '@/composables/useToast'

const state = useProfile()
const { toast } = useToast()

const busy = computed(
  () =>
    state.profileMutation.isPending.value ||
    state.createItemMutation.isPending.value ||
    state.updateItemMutation.isPending.value ||
    state.deleteItemMutation.isPending.value ||
    state.reorderMutation.isPending.value,
)

const section = (type: ProfileItemType) => ({
  items: state.profile.value?.items[type] ?? [],
  busy: busy.value,
})

const saveProfile = (v: ProfileUpdate) => state.profileMutation.mutate(v)
const create = (v: ProfileItemInput) => state.createItemMutation.mutate(v)
const update = (item: ProfileItem, v: ProfileItemInput) =>
  state.updateItemMutation.mutate({ item, input: v })
const remove = (item: ProfileItem) => state.deleteItemMutation.mutate(item)
const reorder = (type: ProfileItemType, ids: number[]) =>
  state.reorderMutation.mutate({ type, ids })

watch(
  () => state.announcement.value,
  (msg) => {
    if (!msg) return
    toast.success(msg)
  },
)

onBeforeRouteLeave(
  () =>
    !state.hasUnsavedChanges.value ||
    window.confirm('You have unsaved profile changes. Leave this page?'),
)

const tabs = [
  { key: 'about', label: 'About', icon: User },
  { key: 'experience', label: 'Experience', icon: Briefcase },
  { key: 'projects', label: 'Projects', icon: Folder },
  { key: 'certifications', label: 'Certs', icon: Award },
  { key: 'skills', label: 'Skills', icon: Wrench },
]

const activeTab = ref('about')

const completionScore = computed(() => {
  const details = state.profile.value?.completion_details
  if (!details) return 0
  const total = details.areas.reduce((s, a) => s + a.available, 0)
  const earned = details.areas.reduce((s, a) => s + a.earned, 0)
  return total > 0 ? Math.round((earned / total) * 100) : 0
})

const completedAreas = computed(() => {
  const details = state.profile.value?.completion_details
  if (!details) return { completed: 0, total: 0 }
  return {
    completed: details.areas.filter((a) => a.complete).length,
    total: details.areas.length,
  }
})
</script>

<template>
  <div class="mx-auto w-full max-w-[1320px] overflow-x-hidden pb-16">
    <p class="sr-only" aria-live="polite">{{ state.announcement.value }}</p>

    <Toast />

    <!-- Loading state -->
    <div v-if="state.isPending.value" aria-label="Loading profile" class="grid gap-6">
      <div
        class="rounded-[var(--radius-2xl)] bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised-lg)]"
      >
        <div
          class="h-1.5 w-full animate-pulse rounded-t-[var(--radius-2xl)] bg-[var(--color-neutral-200)]"
        />
        <div class="p-8">
          <div class="flex items-start gap-5">
            <Skeleton classes="size-20 shrink-0 rounded-2xl sm:size-24" />
            <div class="flex-1 space-y-3 pt-1">
              <Skeleton classes="h-7 w-64" />
              <Skeleton classes="h-5 w-48" />
            </div>
          </div>
        </div>
      </div>
      <div class="flex gap-2">
        <Skeleton v-for="n in 5" :key="n" classes="h-9 w-24 rounded-xl" />
      </div>
      <Skeleton classes="h-96 rounded-[var(--radius-xl)] shadow-[var(--shadow-neo-raised)]" />
    </div>

    <!-- Error state -->
    <section
      v-else-if="state.isError.value"
      class="rounded-[var(--radius-xl)] bg-[var(--color-error-50)] p-6 shadow-[var(--shadow-neo-raised)]"
      aria-labelledby="profile-error"
    >
      <div class="flex items-start gap-4">
        <div
          class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[var(--color-error-100)]"
        >
          <AlertCircle :size="20" class="text-[var(--color-error-600)]" />
        </div>
        <div>
          <h1
            id="profile-error"
            class="text-[var(--text-base)] font-semibold text-[var(--text-primary)]"
          >
            We couldn't load your profile
          </h1>
          <p class="mt-1 text-[var(--text-sm)] text-[var(--color-error-600)]">
            Check your connection and try again. Your entered data has not been cleared.
          </p>
          <div class="mt-3">
            <Button variant="secondary" @click="state.refetch()">
              Retry
            </Button>
          </div>
        </div>
      </div>
    </section>

    <!-- Profile content -->
    <div v-else-if="state.profile.value" class="ds-animate-fade-in grid gap-6">
      <ProfileHeader
        :profile="state.profile.value"
        :saving="busy"
        :completion-score="completionScore"
        :completed-areas="completedAreas"
        @save="saveProfile"
        @dirty="state.markDirty('header', $event)"
      />

      <Tabs v-model="activeTab" :tabs="tabs">
        <template #default="{ activeTab: currentTab }">
          <!-- About tab -->
          <div v-if="currentTab === 'about'" class="grid gap-6 pt-6">
            <ProfessionalSummary
              :profile="state.profile.value!"
              :saving="busy"
              @save="saveProfile"
              @dirty="state.markDirty('summary', $event)"
            />
            <CareerPreferences
              :profile="state.profile.value!"
              :saving="busy"
              @save="saveProfile"
              @dirty="state.markDirty('preferences', $event)"
            />
            <ProfessionalLinks
              :profile="state.profile.value!"
              :saving="busy"
              @save="saveProfile"
              @dirty="state.markDirty('links', $event)"
            />
          </div>

          <!-- Experience tab -->
          <div v-else-if="currentTab === 'experience'" class="grid gap-6 pt-6">
            <ExperienceTimeline
              v-bind="section('experience')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('experience', $event)"
              @dirty="state.markDirty('experience', $event)"
            />
            <EducationSection
              v-bind="section('education')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('education', $event)"
              @dirty="state.markDirty('education', $event)"
            />
          </div>

          <!-- Projects tab -->
          <div v-else-if="currentTab === 'projects'" class="pt-6">
            <ProjectsSection
              v-bind="section('project')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('project', $event)"
              @dirty="state.markDirty('project', $event)"
            />
          </div>

          <!-- Certifications tab -->
          <div v-else-if="currentTab === 'certifications'" class="pt-6">
            <CertificationsSection
              v-bind="section('certification')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('certification', $event)"
              @dirty="state.markDirty('certification', $event)"
            />
          </div>

          <!-- Skills tab -->
          <div v-else-if="currentTab === 'skills'" class="pt-6">
            <SkillsSection />
          </div>
        </template>
      </Tabs>
    </div>
  </div>
</template>
