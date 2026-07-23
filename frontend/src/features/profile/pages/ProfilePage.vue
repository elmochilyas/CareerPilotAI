<script setup lang="ts">
import { CheckCircle, AlertCircle, User } from '@lucide/vue'
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import type { ProfileItem, ProfileItemInput, ProfileItemType, ProfileUpdate } from '../types'
import { useProfile } from '../composables/useProfile'
import ProfileHeader from '../components/ProfileHeader.vue'
import CompletionGuidance from '../components/CompletionGuidance.vue'
import ProfessionalSummary from '../components/ProfessionalSummary.vue'
import ExperienceTimeline from '../components/ExperienceTimeline.vue'
import EducationSection from '../components/EducationSection.vue'
import ProjectsSection from '../components/ProjectsSection.vue'
import CertificationsSection from '../components/CertificationsSection.vue'
import CareerPreferences from '../components/CareerPreferences.vue'
import ProfessionalLinks from '../components/ProfessionalLinks.vue'

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

const successMessage = ref('')
let successTimer: ReturnType<typeof setTimeout> | null = null
const toastVisible = ref(false)
watch(
  () => state.announcement.value,
  (msg) => {
    if (!msg) return
    successMessage.value = msg
    toastVisible.value = true
    if (successTimer) clearTimeout(successTimer)
    successTimer = setTimeout(() => {
      toastVisible.value = false
      setTimeout(() => {
        successMessage.value = ''
      }, 250)
    }, 3500)
  },
)

onBeforeRouteLeave(
  () =>
    !state.hasUnsavedChanges.value ||
    window.confirm('You have unsaved profile changes. Leave this page?'),
)

const sections = [
  { id: 'section-summary', label: 'Overview' },
  { id: 'section-experience', label: 'Experience' },
  { id: 'section-education', label: 'Education' },
  { id: 'section-projects', label: 'Projects' },
  { id: 'section-certifications', label: 'Certifications' },
  { id: 'section-preferences', label: 'Career preferences' },
  { id: 'section-links', label: 'Professional links' },
] as const

const activeSection = ref('')
const navObserver = ref<IntersectionObserver | null>(null)

function scrollToSection(id: string) {
  const el = document.getElementById(id)
  if (el) {
    const navHeight = 120
    const top = el.getBoundingClientRect().top + window.scrollY - navHeight
    window.scrollTo({ top, behavior: 'smooth' })
  }
}

onMounted(() => {
  if (typeof IntersectionObserver === 'undefined') return
  navObserver.value = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          activeSection.value = entry.target.id
        }
      }
    },
    { rootMargin: '-130px 0px -60% 0px', threshold: 0 },
  )
  for (const s of sections) {
    const el = document.getElementById(s.id)
    if (el) navObserver.value?.observe(el)
  }
})

onUnmounted(() => {
  navObserver.value?.disconnect()
})
</script>

<template>
  <div class="mx-auto w-full max-w-[1320px] overflow-x-hidden pb-16">
    <p class="sr-only" aria-live="polite">{{ state.announcement.value }}</p>

    <Teleport to="body">
      <Transition name="toast">
        <div
          v-if="successMessage"
          class="fixed right-6 top-24 z-50 max-w-sm rounded-2xl border border-emerald-200/60 bg-gradient-to-br from-emerald-500 to-emerald-600 px-5 py-4 text-sm font-medium text-white shadow-xl shadow-emerald-500/20 backdrop-blur-sm motion-reduce:transition-none"
          role="status"
        >
          <div class="flex items-center gap-3">
            <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-white/20">
              <CheckCircle :size="14" stroke-width="3" />
            </div>
            <span>{{ successMessage }}</span>
          </div>
        </div>
      </Transition>
    </Teleport>

    <div v-if="state.isPending.value" aria-label="Loading profile" class="grid gap-6">
      <div class="h-36 animate-shimmer rounded-2xl border border-slate-200 bg-white p-6">
        <div class="flex items-center gap-5">
          <div class="size-16 rounded-full bg-slate-200/60" />
          <div class="flex-1 space-y-3">
            <div class="h-5 w-56 rounded-md bg-slate-200/60" />
            <div class="h-4 w-40 rounded-md bg-slate-200/60" />
            <div class="h-3 w-64 rounded-md bg-slate-200/60" />
          </div>
          <div class="size-16 rounded-full bg-slate-200/60" />
        </div>
      </div>
      <div class="flex gap-2">
        <div v-for="n in 6" :key="n" class="h-9 w-28 animate-shimmer rounded-xl bg-slate-200/60" />
      </div>
      <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="grid gap-6">
          <div
            v-for="n in 4"
            :key="n"
            class="h-40 animate-shimmer rounded-2xl border border-slate-100 bg-white"
          />
        </div>
        <div class="h-72 animate-shimmer rounded-2xl border border-slate-100 bg-white" />
      </div>
    </div>

    <section
      v-else-if="state.isError.value"
      class="rounded-2xl border border-red-200/60 bg-gradient-to-br from-red-50 to-red-50/50 p-8"
      aria-labelledby="profile-error"
    >
      <div class="flex items-start gap-4">
        <div
          class="flex size-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-red-100 to-red-200 shadow-inner"
        >
          <AlertCircle :size="22" class="text-red-600" stroke-width="1.5" />
        </div>
        <div>
          <h1 id="profile-error" class="text-lg font-bold text-red-900">
            We couldn't load your profile
          </h1>
          <p class="mt-1.5 text-sm leading-relaxed text-red-700">
            Check your connection and try again. Your entered data has not been cleared.
          </p>
          <button
            class="mt-4 min-h-11 cursor-pointer rounded-xl bg-gradient-to-br from-red-600 to-red-700 px-5 text-sm font-semibold text-white shadow-lg shadow-red-600/20 transition-all hover:shadow-xl hover:shadow-red-600/30 active:scale-[0.97]"
            @click="state.refetch()"
          >
            Retry
          </button>
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

      <nav
        class="sticky top-0 z-30 -mx-4 mt-5 border-b border-slate-200/80 bg-white/80 backdrop-blur-xl sm:-mx-6 lg:-mx-8 lg:mt-6"
        aria-label="Profile sections"
      >
        <div class="flex flex-wrap gap-x-0.5 gap-y-0.5 px-4 sm:px-6 lg:px-8">
          <button
            v-for="s in sections"
            :key="s.id"
            type="button"
            class="relative min-h-12 whitespace-nowrap px-3.5 text-sm font-medium transition-all duration-300"
            :class="
              activeSection === s.id ? 'text-primary-700' : 'text-slate-400 hover:text-slate-700'
            "
            @click="scrollToSection(s.id)"
          >
            {{ s.label }}
            <span
              v-if="activeSection === s.id"
              class="absolute bottom-0 left-1/2 h-0.5 w-4/5 -translate-x-1/2 rounded-full bg-gradient-to-r from-primary-500 to-primary-600"
            />
          </button>
        </div>
      </nav>

      <div
        v-if="!state.profile.value.id"
        class="mt-6 animate-fade-in-up flex items-start gap-5 rounded-2xl border border-primary-100/60 bg-gradient-to-br from-primary-50 via-primary-50/80 to-white p-6 shadow-sm"
      >
        <div
          class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-md"
          aria-hidden="true"
        >
          <User :size="22" stroke-width="1.5" />
        </div>
        <div>
          <h2 class="text-lg font-bold text-slate-950">Start building your trusted profile.</h2>
          <p class="mt-1 text-sm leading-6 text-slate-600">
            Choose a section below and add only information you can confidently confirm.
          </p>
        </div>
      </div>

      <div class="mt-6 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="grid min-w-0 gap-6">
          <div id="section-summary">
            <ProfessionalSummary
              :profile="state.profile.value"
              :saving="busy"
              @save="saveProfile"
              @dirty="state.markDirty('summary', $event)"
            />
          </div>

          <div id="section-experience">
            <ExperienceTimeline
              v-bind="section('experience')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('experience', $event)"
              @dirty="state.markDirty('experience', $event)"
            />
          </div>

          <div id="section-education">
            <EducationSection
              v-bind="section('education')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('education', $event)"
              @dirty="state.markDirty('education', $event)"
            />
          </div>

          <div id="section-projects">
            <ProjectsSection
              v-bind="section('project')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('project', $event)"
              @dirty="state.markDirty('project', $event)"
            />
          </div>

          <div id="section-certifications">
            <CertificationsSection
              v-bind="section('certification')"
              @create="create"
              @update="update"
              @delete="remove"
              @reorder="reorder('certification', $event)"
              @dirty="state.markDirty('certification', $event)"
            />
          </div>

          <div id="section-preferences">
            <CareerPreferences
              :profile="state.profile.value"
              :saving="busy"
              @save="saveProfile"
              @dirty="state.markDirty('preferences', $event)"
            />
          </div>

          <div id="section-links">
            <ProfessionalLinks
              :profile="state.profile.value"
              :saving="busy"
              @save="saveProfile"
              @dirty="state.markDirty('links', $event)"
            />
          </div>
        </div>

        <aside
          class="order-first lg:order-last lg:sticky lg:top-[5rem]"
          aria-label="Profile progress"
        >
          <CompletionGuidance :details="state.profile.value.completion_details" />
        </aside>
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
