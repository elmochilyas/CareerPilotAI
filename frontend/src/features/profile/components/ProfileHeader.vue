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
  <header
    class="overflow-hidden rounded-[var(--radius-2xl)] bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised-lg)]"
  >
    <div
      class="h-1.5 w-full bg-gradient-to-r from-[var(--color-primary-400)] via-[var(--color-primary-600)] to-[var(--color-primary-500)]"
    />

    <div class="px-6 py-8 sm:px-8 sm:py-10">
      <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-5">
          <div
            class="relative flex size-20 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[var(--color-primary-500)] to-[var(--color-primary-700)] text-xl font-bold text-white shadow-[var(--shadow-neo-raised-lg)] sm:size-24 sm:text-2xl"
          >
            {{ initials }}
          </div>
          <div class="min-w-0 pt-1 sm:pt-2">
            <h1
              class="text-2xl font-extrabold tracking-tight text-[var(--text-primary)] sm:text-3xl"
            >
              {{ profile.full_name }}
            </h1>
            <button
              type="button"
              class="group mt-1.5 flex w-full cursor-pointer items-center gap-2 rounded-xl px-2.5 py-2 text-left transition-all hover:bg-[var(--surface-secondary)]"
              @click="editing = true"
            >
              <span
                v-if="profile.headline"
                class="min-w-0 text-sm font-medium text-[var(--text-secondary)] transition-colors group-hover:text-[var(--text-primary)]"
              >
                {{ profile.headline }}
              </span>
              <span
                v-else
                class="rounded-lg border border-dashed border-[var(--border-default)] px-3 py-1 text-xs text-[var(--text-muted)] transition-all group-hover:border-[var(--color-primary-300)] group-hover:bg-[var(--color-primary-50)] group-hover:text-[var(--color-primary-600)]"
              >
                Add a professional headline
              </span>
              <Pencil
                :size="15"
                class="shrink-0 text-[var(--text-muted)] transition-colors group-hover:text-[var(--color-primary-500)]"
              />
            </button>
          </div>
        </div>

        <div class="flex items-center gap-5 shrink-0">
          <div class="flex flex-col items-center gap-2" aria-label="Profile completion">
            <ProgressRing :percentage="completionScore" :size="80" :stroke-width="7" />
            <span class="text-xs font-semibold tracking-wide text-[var(--text-tertiary)]">
              Profile strength
            </span>
          </div>

          <div
            v-if="completedAreas.completed === completedAreas.total && completedAreas.total > 0"
            class="flex items-center gap-2 rounded-2xl bg-[var(--color-success-50)] px-4 py-2.5 text-sm font-semibold text-[var(--color-success-700)] shadow-[var(--shadow-neo-raised)]"
          >
            <CheckCircle :size="18" />
            Complete
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center gap-2.5 text-xs">
        <span
          class="flex items-center gap-2 rounded-xl bg-[var(--surface-secondary)] px-3 py-2 font-medium shadow-[var(--shadow-neo-raised-sm)]"
          :class="
            profile.city || profile.country
              ? 'text-[var(--text-secondary)]'
              : 'text-[var(--text-muted)]'
          "
        >
          <MapPin
            :size="14"
            :class="
              profile.city || profile.country
                ? 'text-[var(--color-primary-400)]'
                : 'text-[var(--text-muted)]'
            "
          />
          {{ [profile.city, profile.country].filter(Boolean).join(', ') || 'Location not added' }}
        </span>
        <span
          class="flex items-center gap-2 rounded-xl bg-[var(--surface-secondary)] px-3 py-2 font-medium text-[var(--text-secondary)] shadow-[var(--shadow-neo-raised-sm)]"
        >
          <span
            class="inline-block size-2.5 rounded-full"
            :class="availabilityColor[profile.availability_status ?? ''] ?? 'bg-slate-300'"
          />
          {{ availabilityLabel[profile.availability_status ?? ''] ?? 'Availability not set' }}
        </span>
        <span
          v-if="profile.target_roles?.length"
          class="rounded-xl bg-[var(--color-primary-50)] px-3 py-2 font-semibold text-[var(--color-primary-700)] shadow-[var(--shadow-neo-raised-sm)]"
        >
          {{ profile.target_roles[0] }}
        </span>
      </div>
    </div>

    <div v-if="editing" class="border-t border-[var(--border-subtle)] px-6 py-5 sm:px-8">
      <form class="flex max-w-xl gap-2" @submit.prevent="save">
        <label class="sr-only" for="profile-headline">Professional headline</label>
        <div class="relative flex-1">
          <input
            id="profile-headline"
            v-model="headline"
            maxlength="255"
            class="min-h-11 w-full rounded-xl border border-[var(--border-default)] bg-[var(--surface-primary)] px-4 pr-14 text-sm shadow-[var(--shadow-neo-inset)] transition-all focus:border-[var(--color-primary-400)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
            placeholder="Your professional headline"
            @input="emit('dirty', true)"
          />
          <span
            class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[var(--text-muted)]"
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
