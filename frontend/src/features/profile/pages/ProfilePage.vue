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
import Skeleton from '@/components/ui/Skeleton.vue'

const state = useProfile()

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

const toastMessage = ref('')
let successTimer: ReturnType<typeof setTimeout> | null = null
const toastVisible = ref(false)
watch(
  () => state.announcement.value,
  (msg) => {
    if (!msg) return
    toastMessage.value = msg
    toastVisible.value = true
    if (successTimer) clearTimeout(successTimer)
    successTimer = setTimeout(() => {
      toastVisible.value = false
      setTimeout(() => {
        toastMessage.value = ''
      }, 250)
    }, 3500)
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

    <Teleport to="body">
      <Transition name="toast">
        <div
          v-if="toastMessage"
          class="fixed right-4 top-16 z-50 max-w-sm rounded-lg border border-emerald-200 bg-white px-4 py-3 shadow-lg ring-1 ring-slate-900/5"
          role="status"
        >
          <div class="flex items-center gap-3">
            <div
              class="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-100"
            >
              <svg
                viewBox="0 0 16 16"
                fill="none"
                class="size-3.5 text-emerald-600"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <polyline points="3 8 6 11 13 4" />
              </svg>
            </div>
            <span class="text-sm font-medium text-slate-800">{{ toastMessage }}</span>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Loading state -->
    <div v-if="state.isPending.value" aria-label="Loading profile" class="grid gap-6">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-center gap-4">
          <Skeleton classes="size-14 rounded-full" />
          <div class="flex-1 space-y-2">
            <Skeleton classes="h-5 w-56" />
            <Skeleton classes="h-4 w-40" />
            <Skeleton classes="h-3 w-64" />
          </div>
        </div>
      </div>
      <div class="flex gap-2">
        <Skeleton v-for="n in 5" :key="n" classes="h-9 w-24" />
      </div>
      <Skeleton classes="h-96 rounded-lg border border-slate-200" />
    </div>

    <!-- Error state -->
    <section
      v-else-if="state.isError.value"
      class="rounded-lg border border-red-200 bg-red-50 p-6"
      aria-labelledby="profile-error"
    >
      <div class="flex items-start gap-4">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100">
          <AlertCircle :size="20" class="text-red-600" />
        </div>
        <div>
          <h1 id="profile-error" class="text-base font-semibold text-red-900">
            We couldn't load your profile
          </h1>
          <p class="mt-1 text-sm text-red-700">
            Check your connection and try again. Your entered data has not been cleared.
          </p>
          <div class="mt-3">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
              @click="state.refetch()"
            >
              Retry
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Profile content -->
    <div v-else-if="state.profile.value" class="grid gap-6">
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

<style scoped>
.toast-enter-active {
  animation: toast-in 0.35s cubic-bezier(0.16, 1, 0.3, 1) both;
}
.toast-leave-active {
  animation: toast-out 0.25s cubic-bezier(0.55, 0, 1, 0.45) both;
}
</style>
