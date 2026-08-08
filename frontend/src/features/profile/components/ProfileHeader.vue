<script setup lang="ts">
import { MapPin, CheckCircle, Pencil } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import type { CandidateProfile, ProfileUpdate } from '../types'
import ProgressRing from '@/components/ui/ProgressRing.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  profile: CandidateProfile
  saving: boolean
  completionScore: number
  completedAreas: { completed: number; total: number }
}>()
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
</script>

<template>
  <header class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="bg-primary-600 px-6 pb-6 pt-8 sm:px-8 sm:pb-8 sm:pt-10">
      <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-end gap-4">
          <div
            class="flex size-16 shrink-0 items-center justify-center rounded-full bg-white text-lg font-bold text-primary-700 shadow-sm ring-4 ring-white/30"
          >
            {{ initials }}
          </div>
          <div class="min-w-0 pb-0.5">
            <h1 class="text-xl font-bold text-white sm:text-2xl">
              {{ profile.full_name }}
            </h1>
            <button
              type="button"
              class="mt-1 flex cursor-pointer items-center gap-1.5 text-left"
              @click="editing = true"
            >
              <span
                v-if="profile.headline"
                class="text-sm text-white/80 transition-colors hover:text-white"
              >
                {{ profile.headline }}
              </span>
              <span
                v-else
                class="rounded border border-dashed border-white/40 px-2 py-0.5 text-xs text-white/60 transition-all hover:border-white/70 hover:text-white/90"
              >
                Add a professional headline
              </span>
              <Pencil :size="12" class="text-white/40" />
            </button>
          </div>
        </div>

        <div class="flex items-center gap-4 shrink-0">
          <div class="flex flex-col items-center gap-1" aria-label="Profile completion">
            <ProgressRing :percentage="completionScore" :size="72" :stroke-width="6" />
            <span class="text-xs font-medium text-white/70">Profile strength</span>
          </div>

          <div
            v-if="completedAreas.completed === completedAreas.total && completedAreas.total > 0"
            class="flex items-center gap-1.5 rounded-lg bg-white/20 px-3 py-2 text-sm font-medium text-white"
          >
            <CheckCircle :size="16" class="text-emerald-300" />
            Complete
          </div>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs">
        <span
          class="flex items-center gap-1.5 rounded bg-white/15 px-2 py-1"
          :class="profile.city || profile.country ? 'text-white/80' : 'text-white/60'"
        >
          <MapPin
            :size="13"
            :class="profile.city || profile.country ? 'text-white/60' : 'text-white/40'"
          />
          {{ [profile.city, profile.country].filter(Boolean).join(', ') || 'Location not added' }}
        </span>
        <span class="flex items-center gap-1.5 rounded bg-white/15 px-2 py-1 text-white/80">
          <span
            class="inline-block size-2 rounded-full"
            :class="availabilityColor[profile.availability_status ?? ''] ?? 'bg-slate-300'"
          />
          {{ availabilityLabel[profile.availability_status ?? ''] ?? 'Availability not set' }}
        </span>
        <span
          v-if="profile.target_roles?.length"
          class="rounded bg-white/20 px-2 py-1 font-medium text-white"
        >
          {{ profile.target_roles[0] }}
        </span>
      </div>
    </div>

    <div v-if="editing" class="border-t border-slate-100 px-6 py-4 sm:px-8">
      <form class="flex max-w-xl gap-2" @submit.prevent="save">
        <label class="sr-only" for="profile-headline">Professional headline</label>
        <div class="relative flex-1">
          <input
            id="profile-headline"
            v-model="headline"
            maxlength="255"
            class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3.5 pr-14 text-sm shadow-sm transition-all focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
            placeholder="Your professional headline"
            @input="emit('dirty', true)"
          />
          <span
            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"
          >
            {{ headline.length }}/255
          </span>
        </div>
        <Button type="submit" :disabled="saving">
          {{ saving ? '...' : 'Save' }}
        </Button>
        <Button type="button" variant="outline" @click="cancel">Cancel</Button>
      </form>
    </div>
  </header>
</template>
