<script setup lang="ts">
import { AlertCircle } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import type { ProfileItem, ProfileItemInput, ProfileItemType, ProfileUpdate } from '../types'
import { useProfile } from '../composables/useProfile'
import ProfileHeader from '../components/ProfileHeader.vue'
import ProfessionalSummary from '../components/ProfessionalSummary.vue'
import ExperienceTimeline from '../components/ExperienceTimeline.vue'
import EducationSection from '../components/EducationSection.vue'
import ProjectsSection from '../components/ProjectsSection.vue'
import CertificationsSection from '../components/CertificationsSection.vue'
import CareerPreferences from '../components/CareerPreferences.vue'
import ProfessionalLinks from '../components/ProfessionalLinks.vue'
import SkillsSection from '@/features/skills/components/SkillsSection.vue'
import Button from '@/components/ui/Button.vue'
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
  { id: 'about', label: 'About' },
  { id: 'experience', label: 'Experience' },
  { id: 'projects', label: 'Projects' },
  { id: 'certifications', label: 'Certifications' },
  { id: 'skills', label: 'Skills' },
] as const

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

    <div v-if="state.isPending.value" aria-label="Loading profile" class="grid gap-6">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-center gap-4">
          <Skeleton class="size-14 rounded-full" />
          <div class="flex-1 space-y-2">
            <Skeleton class="h-5 w-56" />
            <Skeleton class="h-4 w-40" />
            <Skeleton class="h-3 w-64" />
          </div>
        </div>
      </div>
      <div class="flex gap-2">
        <Skeleton v-for="n in 5" :key="n" class="h-9 w-24" />
      </div>
      <Skeleton class="h-96 rounded-lg border border-slate-200" />
    </div>

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
            <Button variant="outline" @click="state.refetch()">Retry</Button>
          </div>
        </div>
      </div>
    </section>

    <div v-else-if="state.profile.value" class="grid gap-0">
      <ProfileHeader
        :profile="state.profile.value"
        :saving="busy"
        @save="saveProfile"
        @dirty="state.markDirty('header', $event)"
      />

      <!-- Completion progress bar -->
      <div
        class="mt-6 flex items-center gap-4 rounded-lg border border-slate-200 bg-white px-5 py-4 shadow-sm"
      >
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <div class="relative flex shrink-0 items-center justify-center">
            <svg width="48" height="48" viewBox="0 0 48 48" class="-rotate-90">
              <circle
                cx="24"
                cy="24"
                r="20"
                fill="none"
                stroke="currentColor"
                stroke-width="4"
                class="text-slate-100"
              />
              <circle
                cx="24"
                cy="24"
                r="20"
                fill="none"
                stroke="currentColor"
                stroke-width="4"
                stroke-linecap="round"
                :stroke-dasharray="2 * Math.PI * 20"
                :stroke-dashoffset="2 * Math.PI * 20 * (1 - completionScore / 100)"
                class="text-primary-500 transition-[stroke-dashoffset] duration-700 motion-reduce:transition-none"
              />
            </svg>
            <span class="absolute text-xs font-bold text-slate-900">{{ completionScore }}%</span>
          </div>
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-900">Profile strength</p>
            <p class="text-xs text-slate-500">
              {{ completedAreas.completed }} of {{ completedAreas.total }} areas complete
            </p>
          </div>
          <div
            class="hidden h-1.5 w-full max-w-56 flex-1 overflow-hidden rounded-full bg-slate-100 sm:block"
          >
            <div
              class="h-full rounded-full bg-primary-500 transition-all duration-500 motion-reduce:transition-none"
              :style="{ width: completionScore + '%' }"
            />
          </div>
        </div>
      </div>

      <!-- Tab bar -->
      <nav class="mt-6 border-b border-slate-200" aria-label="Profile sections">
        <div class="flex gap-0 -mb-px">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.id"
            class="relative min-h-10 whitespace-nowrap px-4 text-sm font-medium transition-colors"
            :class="
              activeTab === tab.id ? 'text-primary-700' : 'text-slate-500 hover:text-slate-700'
            "
            @click="activeTab = tab.id"
          >
            {{ tab.label }}
            <span
              v-if="activeTab === tab.id"
              class="absolute bottom-0 left-1/2 h-0.5 w-4/5 -translate-x-1/2 rounded-full bg-primary-500"
            />
          </button>
        </div>
      </nav>

      <!-- Tab content -->
      <div class="mt-0 rounded-b-lg border-x border-b border-slate-200 bg-white shadow-sm">
        <!-- About tab -->
        <div v-show="activeTab === 'about'" class="divide-y divide-slate-100">
          <div class="px-6 py-6">
            <ProfessionalSummary
              :profile="state.profile.value"
              :saving="busy"
              @save="saveProfile"
              @dirty="state.markDirty('summary', $event)"
            />
          </div>
          <div class="px-6 py-6">
            <CareerPreferences
              :profile="state.profile.value"
              :saving="busy"
              @save="saveProfile"
              @dirty="state.markDirty('preferences', $event)"
            />
          </div>
          <div class="px-6 py-6">
            <ProfessionalLinks
              :profile="state.profile.value"
              :saving="busy"
              @save="saveProfile"
              @dirty="state.markDirty('links', $event)"
            />
          </div>
        </div>

        <!-- Experience tab -->
        <div v-show="activeTab === 'experience'" class="divide-y divide-slate-100">
          <div class="px-6 py-6">
            <ExperienceTimeline
              v-bind="section('experience')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('experience', $event)"
              @dirty="state.markDirty('experience', $event)"
            />
          </div>
          <div class="px-6 py-6">
            <EducationSection
              v-bind="section('education')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('education', $event)"
              @dirty="state.markDirty('education', $event)"
            />
          </div>
        </div>

        <!-- Projects tab -->
        <div v-show="activeTab === 'projects'" class="px-6 py-6">
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
        <div v-show="activeTab === 'certifications'" class="px-6 py-6">
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
        <div v-show="activeTab === 'skills'" class="px-6 py-6">
          <SkillsSection />
        </div>
      </div>
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
