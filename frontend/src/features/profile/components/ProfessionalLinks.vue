<script setup lang="ts">
import { Link, Pencil, ExternalLink } from '@lucide/vue'
import { computed, reactive, ref, watch } from 'vue'
import type { CandidateProfile, ProfileUpdate } from '../types'
import { normalizeUrl } from '../utils/validation'

const props = defineProps<{ profile: CandidateProfile; saving?: boolean }>()
const emit = defineEmits<{ save: [v: ProfileUpdate]; dirty: [v: boolean] }>()

const editing = ref(false)

const values = reactive({
  linkedin_url: props.profile.linkedin_url ?? '',
  github_url: props.profile.github_url ?? '',
  portfolio_url: props.profile.portfolio_url ?? '',
})

watch(
  () => [props.profile.linkedin_url, props.profile.github_url, props.profile.portfolio_url],
  () => {
    if (editing.value) return
    values.linkedin_url = props.profile.linkedin_url ?? ''
    values.github_url = props.profile.github_url ?? ''
    values.portfolio_url = props.profile.portfolio_url ?? ''
  },
)

function cancel() {
  values.linkedin_url = props.profile.linkedin_url ?? ''
  values.github_url = props.profile.github_url ?? ''
  values.portfolio_url = props.profile.portfolio_url ?? ''
  editing.value = false
  emit('dirty', false)
}

function save() {
  const linkedin = normalizeUrl(values.linkedin_url) || null
  const github = normalizeUrl(values.github_url) || null
  const portfolio = normalizeUrl(values.portfolio_url) || null
  if (
    linkedin !== (props.profile.linkedin_url ?? null) ||
    github !== (props.profile.github_url ?? null) ||
    portfolio !== (props.profile.portfolio_url ?? null)
  ) {
    emit('save', {
      linkedin_url: linkedin,
      github_url: github,
      portfolio_url: portfolio,
      updated_at: props.profile.updated_at,
    })
  }
  editing.value = false
  emit('dirty', false)
}

interface LinkField {
  key: 'linkedin_url' | 'github_url' | 'portfolio_url'
  label: string
  placeholder: string
  gradient: string
  icon: string
}

const fields: LinkField[] = [
  {
    key: 'linkedin_url',
    label: 'LinkedIn',
    placeholder: 'https://linkedin.com/in/…',
    gradient: 'from-blue-500 to-blue-600',
    icon: 'M16 4.5v7a2.5 2.5 0 0 1-2.5 2.5h-7A2.5 2.5 0 0 1 4 11.5v-7A2.5 2.5 0 0 1 6.5 2h7A2.5 2.5 0 0 1 16 4.5ZM8 6v4M6 6v4m2-2.5a1.5 1.5 0 1 0 3 0 1.5 1.5 0 0 0-3 0Z',
  },
  {
    key: 'github_url',
    label: 'GitHub',
    placeholder: 'https://github.com/…',
    gradient: 'from-slate-700 to-slate-900',
    icon: 'M8 1a7 7 0 0 0-2.21 13.64c.35.06.48-.15.48-.34v-1.2c-1.95.42-2.36-.94-2.36-.94-.32-.82-.78-1.04-.78-1.04-.64-.44.05-.43.05-.43.7.05 1.07.72 1.07.72.63 1.07 1.65.76 2.05.58.06-.45.25-.76.45-.94-1.56-.18-3.2-.78-3.2-3.48 0-.77.27-1.4.72-1.89-.07-.18-.31-.9.07-1.88 0 0 .59-.19 1.93.72a6.65 6.65 0 0 1 3.5 0c1.34-.91 1.93-.72 1.93-.72.38.98.14 1.7.07 1.88.45.5.72 1.12.72 1.89 0 2.7-1.64 3.3-3.2 3.48.25.22.48.65.48 1.3v1.93c0 .19.13.4.48.34A7 7 0 0 0 8 1Z',
  },
  {
    key: 'portfolio_url',
    label: 'Portfolio',
    placeholder: 'https://…',
    gradient: 'from-violet-500 to-violet-600',
    icon: 'M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1ZM1 8h14M8 1a11 11 0 0 1 3.5 7A11 11 0 0 1 8 15a11 11 0 0 1-3.5-7A11 11 0 0 1 8 1Z',
  },
]

const hasAny = computed(() => fields.some((f) => props.profile[f.key]))

function displayUrl(url: string): string {
  return url.replace(/^https?:\/\//, '').replace(/\/$/, '')
}
</script>

<template>
  <section
    class="scroll-mt-28 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition-all duration-300 hover:shadow-md"
    aria-labelledby="links-heading"
  >
    <header
      class="flex items-center justify-between gap-3 border-b border-slate-100/80 bg-gradient-to-b from-slate-50/50 to-white px-5 py-4 sm:px-6"
    >
      <div class="flex items-center gap-3">
        <div
          class="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-rose-500 to-rose-600 text-white shadow-sm"
        >
          <Link :size="16" stroke-width="1.5" />
        </div>
        <h2 id="links-heading" class="text-base font-bold text-slate-900">Professional links</h2>
      </div>
      <button
        v-if="hasAny && !editing"
        type="button"
        class="flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-500 shadow-sm transition-all hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700 active:scale-[0.97]"
        @click="editing = true"
      >
        <Pencil :size="13" stroke-width="1.5" />
        Edit
      </button>
    </header>

    <div class="px-5 pb-5 sm:px-6 sm:pb-6">
      <form
        v-if="editing"
        class="grid gap-5 sm:grid-cols-2"
        @submit.prevent="save"
        @input="emit('dirty', true)"
      >
        <label v-for="f in fields" :key="f.key" class="grid gap-1.5 text-sm font-semibold">
          <span class="flex items-center gap-2">
            <span
              class="flex size-5 items-center justify-center rounded bg-gradient-to-br text-white"
              :class="f.gradient"
            >
              <svg
                viewBox="0 0 16 16"
                fill="none"
                class="size-3"
                stroke="currentColor"
                stroke-width="1.3"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path :d="f.icon" />
              </svg>
            </span>
            {{ f.label }}
          </span>
          <input
            v-model="values[f.key]"
            type="url"
            inputmode="url"
            maxlength="500"
            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3.5 shadow-sm transition-all focus:border-primary-400 focus:ring-2 focus:ring-primary-500/30"
            :placeholder="f.placeholder"
          />
        </label>
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
            {{ saving ? 'Saving…' : 'Save links' }}
          </button>
        </div>
      </form>

      <div v-else-if="!hasAny" class="flex flex-col items-center py-12 text-center">
        <div
          class="flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-rose-50 to-rose-100 shadow-inner"
        >
          <Link :size="26" class="text-rose-500" stroke-width="1.3" />
        </div>
        <h3 class="mt-4 text-sm font-bold text-slate-900">Connect your professional presence</h3>
        <p class="mt-1.5 max-w-xs text-xs leading-relaxed text-slate-500">
          Add LinkedIn, GitHub or your portfolio website.
        </p>
        <button
          type="button"
          class="mt-5 min-h-10 cursor-pointer rounded-xl bg-gradient-to-br from-primary-600 to-primary-500 px-5 text-sm font-semibold text-white shadow-md shadow-primary-500/20 transition-all hover:shadow-lg hover:shadow-primary-500/30 active:scale-[0.97]"
          @click="editing = true"
        >
          Add professional links
        </button>
      </div>

      <div v-else class="grid gap-3 pt-2">
        <div
          v-for="f in fields"
          :key="f.key"
          v-show="profile[f.key]"
          class="group flex items-center gap-3 overflow-hidden rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg"
        >
          <div
            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br text-white shadow-sm"
            :class="f.gradient"
          >
            <svg
              viewBox="0 0 16 16"
              fill="none"
              class="size-4"
              stroke="currentColor"
              stroke-width="1.3"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path :d="f.icon" />
            </svg>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-slate-900">{{ f.label }}</p>
            <p class="truncate text-xs text-slate-500 mt-0.5">{{ displayUrl(profile[f.key]!) }}</p>
          </div>
          <a
            :href="profile[f.key]!"
            target="_blank"
            rel="noopener noreferrer"
            class="flex size-9 items-center justify-center rounded-lg text-slate-400 transition-all hover:bg-slate-100 hover:text-primary-600 group-hover:translate-x-0.5"
            :aria-label="`Open ${f.label}`"
          >
            <ExternalLink :size="16" stroke-width="1.5" />
          </a>
        </div>
      </div>
    </div>
  </section>
</template>
