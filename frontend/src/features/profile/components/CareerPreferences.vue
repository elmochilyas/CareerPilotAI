<script setup lang="ts">
import { User, Briefcase, MapPin, DollarSign, Pencil, Check } from '@lucide/vue'
import { reactive, ref, watch } from 'vue'
import type { CandidateProfile, ContractType, ProfileUpdate, WorkMode } from '../types'
import TagInput from '@/components/ui/TagInput.vue'

const props = defineProps<{ profile: CandidateProfile; saving?: boolean }>()
const emit = defineEmits<{ save: [v: ProfileUpdate]; dirty: [v: boolean] }>()

const editing = ref(false)

const values = reactive({
  target_roles: [...props.profile.target_roles],
  preferred_locations: [...props.profile.preferred_locations],
  work_modes: [...props.profile.work_modes],
  contract_types: [...props.profile.contract_types],
  salary_min: props.profile.salary_min ?? '',
  salary_max: props.profile.salary_max ?? '',
  salary_currency: props.profile.salary_currency ?? '',
  salary_period: props.profile.salary_period ?? '',
  availability_date: props.profile.availability_date ?? '',
})

watch(
  () => [
    props.profile.target_roles,
    props.profile.preferred_locations,
    props.profile.work_modes,
    props.profile.contract_types,
  ],
  () => {
    if (editing.value) return
    values.target_roles = [...props.profile.target_roles]
    values.preferred_locations = [...props.profile.preferred_locations]
    values.work_modes = [...props.profile.work_modes]
    values.contract_types = [...props.profile.contract_types]
  },
  { deep: true },
)

function cancel() {
  values.target_roles = [...props.profile.target_roles]
  values.preferred_locations = [...props.profile.preferred_locations]
  values.work_modes = [...props.profile.work_modes]
  values.contract_types = [...props.profile.contract_types]
  values.salary_min = props.profile.salary_min ?? ''
  values.salary_max = props.profile.salary_max ?? ''
  values.salary_currency = props.profile.salary_currency ?? ''
  values.salary_period = props.profile.salary_period ?? ''
  values.availability_date = props.profile.availability_date ?? ''
  editing.value = false
  emit('dirty', false)
}

function toggleWorkMode(mode: string) {
  const idx = values.work_modes.indexOf(mode)
  if (idx >= 0) values.work_modes.splice(idx, 1)
  else values.work_modes.push(mode)
  emit('dirty', true)
}

function save() {
  const p = props.profile
  emit('save', {
    target_roles: values.target_roles,
    preferred_locations: values.preferred_locations,
    work_modes: values.work_modes as WorkMode[],
    contract_types: values.contract_types as ContractType[],
    salary_min: values.salary_min || null,
    salary_max: values.salary_max || null,
    salary_currency: values.salary_currency || null,
    salary_period: values.salary_period || null,
    updated_at: p.updated_at,
  })
  editing.value = false
  emit('dirty', false)
}

const workModeOptions: { value: string; label: string }[] = [
  { value: 'remote', label: 'Remote' },
  { value: 'hybrid', label: 'Hybrid' },
  { value: 'on_site', label: 'On site' },
]

const contractLabels: Record<string, string> = {
  'full-time': 'Full-time',
  'part-time': 'Part-time',
  contract: 'Contract',
  internship: 'Internship',
  freelance: 'Freelance',
}

const availabilityLabel: Record<string, string> = {
  immediately: 'Available immediately',
  within_2_weeks: 'Available in 2 weeks',
  within_month: 'Available within a month',
  not_looking: 'Not looking',
}

function formatSalary(n: string | number | null): string {
  if (n === null || n === '') return ''
  return Number(n).toLocaleString('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  })
}

const hasAnyData = ref(false)
watch(
  () => props.profile,
  (p) => {
    hasAnyData.value = !!(
      p.target_roles.length ||
      p.preferred_locations.length ||
      p.work_modes.length ||
      p.contract_types.length ||
      p.salary_min ||
      p.salary_max ||
      p.availability_status ||
      p.availability_date
    )
  },
  { immediate: true },
)
</script>

<template>
  <section
    class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition-all duration-300 hover:shadow-md"
    aria-labelledby="preferences-heading"
  >
    <header
      class="flex items-center justify-between gap-3 border-b border-slate-100/80 bg-gradient-to-b from-slate-50/50 to-white px-5 py-4 sm:px-6"
    >
      <div class="flex items-center gap-3">
        <div
          class="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-teal-500 to-teal-600 text-white shadow-sm"
        >
          <User :size="16" stroke-width="1.5" />
        </div>
        <h2 id="preferences-heading" class="text-base font-bold text-slate-900">
          Career preferences
        </h2>
      </div>
      <button
        v-if="hasAnyData && !editing"
        type="button"
        class="flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-500 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700 active:scale-[0.97]"
        @click="editing = true"
      >
        <Pencil :size="13" stroke-width="1.5" />
        Edit
      </button>
    </header>

    <div class="px-5 pb-5 sm:px-6 sm:pb-6">
      <form v-if="editing" class="grid gap-5 sm:grid-cols-2" @submit.prevent="save">
        <label class="grid gap-1.5 text-sm font-semibold">
          Target roles
          <TagInput
            v-model="values.target_roles"
            placeholder="Add a role and press Enter"
            :max="10"
            :disabled="saving"
            @update:model-value="emit('dirty', true)"
          />
        </label>
        <label class="grid gap-1.5 text-sm font-semibold">
          Preferred locations
          <TagInput
            v-model="values.preferred_locations"
            placeholder="Add a location and press Enter"
            :max="10"
            :disabled="saving"
            @update:model-value="emit('dirty', true)"
          />
        </label>

        <fieldset class="grid gap-2.5 sm:col-span-2">
          <legend class="text-sm font-semibold">Work modes</legend>
          <div class="flex flex-wrap gap-3">
            <label
              v-for="m in workModeOptions"
              :key="m.value"
              class="flex cursor-pointer select-none items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium shadow-sm transition-all has-checked:border-primary-400 has-checked:bg-primary-50 has-checked:text-primary-700 has-checked:shadow-md hover:border-slate-300"
            >
              <div class="relative flex items-center justify-center">
                <input
                  type="checkbox"
                  :checked="values.work_modes.includes(m.value)"
                  class="size-4 cursor-pointer appearance-none rounded-md border-2 border-slate-300 bg-white transition-all checked:border-primary-600 checked:bg-primary-600 focus:ring-2 focus:ring-primary-500/30"
                  @change="toggleWorkMode(m.value)"
                />
                <Check
                  v-if="values.work_modes.includes(m.value)"
                  :size="12"
                  class="pointer-events-none absolute text-white"
                  stroke-width="3"
                />
              </div>
              {{ m.label }}
            </label>
          </div>
        </fieldset>

        <label class="grid gap-1.5 text-sm font-semibold">
          Contract types
          <TagInput
            v-model="values.contract_types"
            placeholder="Add type and press Enter"
            :max="5"
            :disabled="saving"
            @update:model-value="emit('dirty', true)"
          />
        </label>

        <div class="grid gap-1.5 text-sm font-semibold">
          Salary expectations
          <div class="grid grid-cols-2 gap-2">
            <div class="relative">
              <span
                class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400 font-medium"
                >$</span
              >
              <input
                v-model.number="values.salary_min"
                type="number"
                min="0"
                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white pl-7 pr-3.5 shadow-sm transition-all focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30"
                placeholder="Min"
                :disabled="saving"
                @input="emit('dirty', true)"
              />
            </div>
            <div class="relative">
              <span
                class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400 font-medium"
                >$</span
              >
              <input
                v-model.number="values.salary_max"
                type="number"
                min="0"
                class="min-h-11 w-full rounded-xl border border-slate-300 bg-white pl-7 pr-3.5 shadow-sm transition-all focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30"
                placeholder="Max"
                :disabled="saving"
                @input="emit('dirty', true)"
              />
            </div>
          </div>
          <div class="flex gap-2">
            <select
              v-model="values.salary_currency"
              class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30"
              :disabled="saving"
              @change="emit('dirty', true)"
            >
              <option value="">Currency</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
              <option value="GBP">GBP</option>
              <option value="MAD">MAD</option>
            </select>
            <select
              v-model="values.salary_period"
              class="min-h-10 flex-1 rounded-xl border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30"
              :disabled="saving"
              @change="emit('dirty', true)"
            >
              <option value="">Period</option>
              <option value="yearly">Yearly</option>
              <option value="monthly">Monthly</option>
              <option value="hourly">Hourly</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end gap-2 sm:col-span-2">
          <button
            type="button"
            class="min-h-10 cursor-pointer rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 shadow-sm transition-all hover:bg-slate-50 active:scale-[0.97]"
            @click="cancel"
          >
            Cancel
          </button>
          <button
            :disabled="saving"
            class="min-h-10 cursor-pointer rounded-xl bg-gradient-to-br from-primary-600 to-primary-500 px-5 text-sm font-semibold text-white shadow-md shadow-primary-500/20 transition-all hover:shadow-lg hover:shadow-primary-500/30 active:scale-[0.97] disabled:opacity-60"
          >
            {{ saving ? 'Saving…' : 'Save preferences' }}
          </button>
        </div>
      </form>

      <div v-else-if="!hasAnyData" class="flex flex-col items-center py-12 text-center">
        <div
          class="flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-teal-50 to-teal-100 shadow-inner"
        >
          <User :size="26" class="text-teal-500" stroke-width="1.3" />
        </div>
        <h3 class="mt-4 text-sm font-bold text-slate-900">Define your career direction</h3>
        <p class="mt-1.5 max-w-xs text-xs leading-relaxed text-slate-500">
          Tell CareerPilot what roles and work conditions you prefer.
        </p>
        <button
          type="button"
          class="mt-5 min-h-10 cursor-pointer rounded-xl bg-gradient-to-br from-primary-600 to-primary-500 px-5 text-sm font-semibold text-white shadow-md shadow-primary-500/20 transition-all hover:shadow-lg hover:shadow-primary-500/30 active:scale-[0.97]"
          @click="editing = true"
        >
          Add career preferences
        </button>
      </div>

      <div v-else class="space-y-5 pt-2">
        <div v-if="profile.target_roles.length" class="flex flex-wrap items-center gap-2.5">
          <div
            class="flex size-7 items-center justify-center rounded-md bg-primary-100 text-primary-600"
          >
            <User :size="14" stroke-width="1.5" />
          </div>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"
            >Target roles</span
          >
          <div class="flex flex-wrap gap-1.5">
            <span
              v-for="role in profile.target_roles"
              :key="role"
              class="rounded-lg bg-gradient-to-r from-primary-50 to-primary-100 px-3 py-1 text-xs font-semibold text-primary-700 shadow-sm"
              >{{ role }}</span
            >
          </div>
        </div>

        <div
          v-if="profile.work_modes.length || profile.contract_types.length"
          class="flex flex-wrap items-center gap-2.5"
        >
          <div class="flex size-7 items-center justify-center rounded-md bg-sky-100 text-sky-600">
            <Briefcase :size="14" stroke-width="1.5" />
          </div>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"
            >Work preference</span
          >
          <span
            v-for="wm in profile.work_modes"
            :key="wm"
            class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
            >{{ wm.charAt(0).toUpperCase() + wm.slice(1).replace('_', ' ') }}</span
          >
          <span
            v-for="ct in profile.contract_types"
            :key="ct"
            class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700"
            >{{ contractLabels[ct] ?? ct }}</span
          >
        </div>

        <div v-if="profile.preferred_locations.length" class="flex flex-wrap items-center gap-2.5">
          <div
            class="flex size-7 items-center justify-center rounded-md bg-emerald-100 text-emerald-600"
          >
            <MapPin :size="14" stroke-width="1.5" />
          </div>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"
            >Preferred locations</span
          >
          <span class="text-sm font-medium text-slate-800">{{
            profile.preferred_locations.join(', ')
          }}</span>
        </div>

        <div v-if="profile.availability_status" class="flex flex-wrap items-center gap-2.5">
          <span
            class="inline-block size-2.5 rounded-full animate-pulse-dot"
            :class="{
              'bg-emerald-500': profile.availability_status === 'immediately',
              'bg-amber-400': profile.availability_status === 'within_2_weeks',
              'bg-blue-400': profile.availability_status === 'within_month',
              'bg-slate-400': profile.availability_status === 'not_looking',
            }"
          />
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"
            >Availability</span
          >
          <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{
            availabilityLabel[profile.availability_status] ?? profile.availability_status
          }}</span>
        </div>

        <div v-if="profile.availability_date" class="flex flex-wrap items-center gap-2.5">
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"
            >Available from</span
          >
          <span class="text-sm font-medium text-slate-800">{{
            new Date(profile.availability_date + 'T00:00:00').toLocaleDateString('en-US', {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
            })
          }}</span>
        </div>

        <div
          v-if="profile.salary_min || profile.salary_max"
          class="flex flex-wrap items-center gap-2.5"
        >
          <div
            class="flex size-7 items-center justify-center rounded-md bg-emerald-100 text-emerald-600"
          >
            <DollarSign :size="14" stroke-width="1.5" />
          </div>
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"
            >Salary range</span
          >
          <span
            class="rounded-lg bg-gradient-to-r from-emerald-50 to-emerald-100 px-3 py-1 text-sm font-bold text-emerald-800 shadow-sm"
          >
            {{ profile.salary_min ? formatSalary(profile.salary_min) : '—' }}
            <span v-if="profile.salary_min && profile.salary_max" class="mx-1 text-emerald-400"
              >–</span
            >
            {{
              profile.salary_max
                ? formatSalary(profile.salary_max)
                : profile.salary_min
                  ? 'Uncapped'
                  : ''
            }}
          </span>
          <span
            v-if="profile.salary_currency"
            class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600"
            >{{ profile.salary_currency }}</span
          >
          <span v-if="profile.salary_period" class="text-xs text-slate-500"
            >/{{ profile.salary_period }}</span
          >
        </div>
      </div>
    </div>
  </section>
</template>
