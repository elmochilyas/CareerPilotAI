<script setup lang="ts">
import { MapPin, CheckCircle } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import type { CandidateProfile, ProfileUpdate } from '../types'

const props = defineProps<{ profile: CandidateProfile; saving: boolean }>()
const emit = defineEmits<{ save: [value: ProfileUpdate]; dirty: [value: boolean] }>()

const editing = ref(false)
const headline = ref(props.profile.headline ?? '')

watch(
  () => props.profile.headline,
  (value) => {
    if (!editing.value) headline.value = value ?? ''
  },
)

function cancel() {
  headline.value = props.profile.headline ?? ''
  editing.value = false
  emit('dirty', false)
}

function save() {
  const newVal = headline.value || null
  const oldVal = props.profile.headline ?? null
  if (newVal !== oldVal) {
    emit('save', { headline: newVal, updated_at: props.profile.updated_at })
  }
  editing.value = false
  emit('dirty', false)
}

const initials = computed(
  () =>
    (props.profile.full_name ?? '')
      .split(' ')
      .map((n) => n.charAt(0))
      .join('')
      .slice(0, 2)
      .toUpperCase() || '?',
)

const ringRadius = 32
const ringCircumference = 2 * Math.PI * ringRadius
const completionOffset = computed(
  () =>
    ringCircumference * (1 - Math.min(100, Math.max(0, props.profile.profile_completion)) / 100),
)

const availabilityColor: Record<string, string> = {
  immediately: 'bg-emerald-500',
  within_2_weeks: 'bg-amber-400',
  within_month: 'bg-blue-400',
  not_looking: 'bg-slate-400',
}

const availabilityLabel: Record<string, string> = {
  immediately: 'Available immediately',
  within_2_weeks: 'Available in 2 weeks',
  within_month: 'Available within a month',
  not_looking: 'Not looking',
}

const firstMissingAction = computed<{ key: string; action: string } | null>(() => {
  const missing = props.profile.completion_details.missing_areas[0]
  if (!missing) return null
  const map: Record<string, string> = {
    basic_information: 'Complete basic information',
    headline: 'Add headline',
    professional_summary: 'Add professional summary',
    professional_links: 'Add professional links',
    target_roles: 'Add target roles',
    career_preferences: 'Set career preferences',
    languages: 'Add languages',
    education: 'Add education',
    practical_background: 'Add experience or projects',
  }
  return { key: missing.key, action: map[missing.key] ?? 'Complete this section' }
})

function scrollToSection(key: string) {
  const idMap: Record<string, string> = {
    basic_information: 'section-summary',
    headline: 'section-summary',
    professional_summary: 'section-summary',
    professional_links: 'section-links',
    target_roles: 'section-preferences',
    career_preferences: 'section-preferences',
    languages: 'section-summary',
    education: 'section-education',
    practical_background: 'section-experience',
  }
  const id = idMap[key] ?? 'section-summary'
  const el = document.getElementById(id)
  if (el) {
    const top = el.getBoundingClientRect().top + window.scrollY - 130
    window.scrollTo({ top, behavior: 'smooth' })
  }
}
</script>

<template>
  <header
    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition-shadow duration-300 hover:shadow-md"
  >
    <div
      class="relative bg-gradient-to-br from-primary-600 via-primary-500 to-violet-500 px-6 pb-6 pt-8 sm:px-8 sm:pb-8 sm:pt-10"
    >
      <div
        class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(255,255,255,0.15),transparent_60%)]"
      />
      <div
        class="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(139,92,246,0.2),transparent_50%)]"
      />
      <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-end gap-5">
          <div class="relative shrink-0 -mb-2">
            <div
              class="absolute -inset-1 rounded-full bg-gradient-to-br from-white/40 to-white/10 blur-sm"
            />
            <div
              class="absolute -inset-0.5 animate-glow-pulse rounded-full bg-gradient-to-br from-white/30 to-primary-300/30"
            />
            <div
              class="relative flex size-20 items-center justify-center rounded-full bg-gradient-to-br from-white/95 to-white/80 text-2xl font-bold tracking-wide text-primary-700 shadow-xl ring-4 ring-white/30 backdrop-blur-sm"
            >
              {{ initials }}
            </div>
          </div>
          <div class="min-w-0 pb-0.5">
            <h1 class="text-2xl font-bold tracking-tight text-white drop-shadow-sm sm:text-3xl">
              {{ profile.full_name }}
            </h1>
            <button
              type="button"
              class="mt-1 flex cursor-pointer items-center gap-1.5 text-left"
              @click="editing = true"
            >
              <span
                v-if="profile.headline"
                class="text-sm text-white/80 hover:text-white transition-colors"
              >
                {{ profile.headline }}
              </span>
              <span
                v-else
                class="rounded-lg border border-dashed border-white/40 px-2.5 py-1 text-xs text-white/60 transition-all hover:border-white/70 hover:text-white/90"
              >
                Add a professional headline
              </span>
            </button>
          </div>
        </div>

        <div class="flex items-center gap-4 shrink-0">
          <div class="flex flex-col items-center gap-1.5" aria-label="Profile completion">
            <div class="relative flex items-center justify-center">
              <svg width="76" height="76" viewBox="0 0 76 76" class="-rotate-90 drop-shadow-lg">
                <defs>
                  <linearGradient id="ringGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="rgba(255,255,255,0.3)" />
                    <stop offset="100%" stop-color="rgba(255,255,255,0.8)" />
                  </linearGradient>
                </defs>
                <circle
                  cx="38"
                  cy="38"
                  :r="ringRadius"
                  fill="none"
                  stroke="rgba(255,255,255,0.15)"
                  stroke-width="6"
                />
                <circle
                  cx="38"
                  cy="38"
                  :r="ringRadius"
                  fill="none"
                  stroke="url(#ringGradient)"
                  stroke-width="6"
                  stroke-linecap="round"
                  :stroke-dasharray="ringCircumference"
                  :stroke-dashoffset="completionOffset"
                  class="transition-[stroke-dashoffset] duration-1000 motion-reduce:transition-none"
                />
              </svg>
              <span class="absolute text-lg font-bold text-white">
                {{ Math.round(profile.profile_completion)
                }}<span class="text-[11px] font-normal text-white/60">%</span>
              </span>
            </div>
            <span class="text-[11px] font-medium tracking-wide text-white/70"
              >Profile strength</span
            >
          </div>

          <button
            v-if="firstMissingAction"
            type="button"
            class="min-h-11 cursor-pointer rounded-xl bg-white/20 px-5 text-sm font-semibold text-white shadow-lg shadow-black/10 backdrop-blur-sm transition-all hover:bg-white/30 active:scale-[0.97]"
            @click="scrollToSection(firstMissingAction.key)"
          >
            {{ firstMissingAction.action }}
          </button>
          <div
            v-else
            class="flex items-center gap-1.5 rounded-xl bg-white/20 px-4 py-2.5 text-sm font-medium text-white shadow-sm backdrop-blur-sm"
          >
            <CheckCircle :size="16" stroke-width="2.5" class="text-emerald-300" />
            Complete
          </div>
        </div>
      </div>

      <div class="relative mt-5 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs">
        <span
          v-if="profile.city || profile.country"
          class="flex items-center gap-1.5 rounded-lg bg-white/15 px-2.5 py-1 text-white/80 backdrop-blur-sm"
        >
          <MapPin :size="13" class="text-white/60" stroke-width="1.5" />
          {{ [profile.city, profile.country].filter(Boolean).join(', ') || 'Location not added' }}
        </span>
        <span
          v-else
          class="flex items-center gap-1.5 rounded-lg bg-white/15 px-2.5 py-1 text-white/60 backdrop-blur-sm"
        >
          <MapPin :size="13" class="text-white/40" stroke-width="1.5" />
          Location not added
        </span>
        <span
          class="flex items-center gap-1.5 rounded-lg bg-white/15 px-2.5 py-1 text-white/80 backdrop-blur-sm"
        >
          <span
            class="inline-block size-2 rounded-full animate-pulse-dot"
            :class="availabilityColor[profile.availability_status ?? ''] ?? 'bg-slate-300'"
          />
          {{ availabilityLabel[profile.availability_status ?? ''] ?? 'Availability not set' }}
        </span>
        <span
          v-if="profile.target_roles?.length"
          class="rounded-lg bg-white/20 px-2.5 py-1 font-medium text-white backdrop-blur-sm"
        >
          {{ profile.target_roles[0] }}
        </span>
      </div>
    </div>

    <div
      v-if="editing"
      class="border-t border-slate-100 bg-gradient-to-b from-slate-50/50 to-white px-6 py-4 sm:px-8"
    >
      <form class="flex max-w-xl gap-2" @submit.prevent="save">
        <label class="sr-only" for="profile-headline">Professional headline</label>
        <div class="relative flex-1">
          <input
            id="profile-headline"
            v-model="headline"
            maxlength="255"
            class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 pr-16 text-sm shadow-sm transition-all focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30"
            placeholder="Your professional headline"
            @input="emit('dirty', true)"
          />
          <span
            class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400"
            >{{ headline.length }}/255</span
          >
        </div>
        <button
          :disabled="saving"
          class="min-h-11 cursor-pointer rounded-xl bg-gradient-to-br from-primary-600 to-primary-500 px-5 text-sm font-semibold text-white shadow-md shadow-primary-500/20 transition-all hover:shadow-lg hover:shadow-primary-500/30 active:scale-[0.97] disabled:opacity-60"
        >
          {{ saving ? '…' : 'Save' }}
        </button>
        <button
          type="button"
          class="min-h-11 cursor-pointer rounded-xl border border-slate-300 bg-white px-4 text-sm text-slate-600 shadow-sm transition-all hover:bg-slate-50 active:scale-[0.97]"
          @click="cancel"
        >
          Cancel
        </button>
      </form>
    </div>
  </header>
</template>
