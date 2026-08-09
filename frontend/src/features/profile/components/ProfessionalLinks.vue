<script setup lang="ts">
import { Link, Pencil, ExternalLink } from '@lucide/vue'
import { computed, reactive, ref, watch } from 'vue'
import type { CandidateProfile, ProfileUpdate } from '../types'
import { normalizeUrl } from '../utils/validation'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'

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
  iconBg: string
  icon: string
  viewBox: string
  filled?: boolean
}

const fields: LinkField[] = [
  {
    key: 'linkedin_url',
    label: 'LinkedIn',
    placeholder: 'https://linkedin.com/in/...',
    iconBg: 'bg-[#0a66c2]/10 text-[#0a66c2]',
    icon: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
    viewBox: '0 0 24 24',
    filled: true,
  },
  {
    key: 'github_url',
    label: 'GitHub',
    placeholder: 'https://github.com/...',
    iconBg: 'bg-[var(--color-neutral-800)]/10 text-[var(--color-neutral-800)]',
    icon: 'M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12',
    viewBox: '0 0 24 24',
    filled: true,
  },
  {
    key: 'portfolio_url',
    label: 'Portfolio',
    placeholder: 'https://...',
    iconBg: 'bg-[var(--color-primary-100)] text-[var(--color-primary-600)]',
    icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z',
    viewBox: '0 0 24 24',
  },
]

const hasAny = computed(() => fields.some((f) => props.profile[f.key]))

function displayUrl(url: string | null): string {
  return (url ?? '').replace(/^https?:\/\//, '').replace(/\/$/, '')
}
</script>

<template>
  <Card>
    <template #header>
      <div class="flex items-center gap-3">
        <div
          class="flex size-9 items-center justify-center rounded-lg bg-[var(--color-primary-100)] text-[var(--color-primary-600)]"
        >
          <Link :size="16" stroke-width="1.5" />
        </div>
        <h2 class="text-base font-semibold text-[var(--text-primary)]">Professional links</h2>
      </div>
      <Button v-if="hasAny && !editing" variant="outline" size="sm" @click="editing = true">
        <Pencil :size="13" stroke-width="1.5" />
        Edit
      </Button>
    </template>

    <form
      v-if="editing"
      class="grid gap-5 sm:grid-cols-2"
      @submit.prevent="save"
      @input="emit('dirty', true)"
    >
      <label
        v-for="f in fields"
        :key="f.key"
        class="grid gap-1.5 text-sm font-medium text-[var(--text-secondary)]"
      >
        <span class="flex items-center gap-2">
          <span class="flex size-5 items-center justify-center rounded" :class="f.iconBg">
            <svg
              :viewBox="f.viewBox"
              :fill="f.filled ? 'currentColor' : 'none'"
              class="size-3"
              :stroke="f.filled ? 'none' : 'currentColor'"
              :stroke-width="f.filled ? 0 : 1.3"
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
          class="min-h-11 rounded-xl bg-[var(--surface-inset)] px-3.5 shadow-[var(--shadow-neo-inset)] text-[var(--text-primary)] placeholder:text-[var(--text-muted)] transition-all focus:outline-none focus:ring-2 focus:ring-[var(--color-primary-500)]/30"
          :placeholder="f.placeholder"
        />
      </label>
      <div class="flex justify-end gap-2 sm:col-span-2">
        <Button variant="outline" type="button" @click="cancel">Cancel</Button>
        <Button type="submit" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save links' }}
        </Button>
      </div>
    </form>

    <div v-else-if="!hasAny" class="flex flex-col items-center py-12 text-center">
      <div
        class="flex size-12 items-center justify-center rounded-full bg-[var(--color-primary-100)] shadow-[var(--shadow-neo-inset)]"
      >
        <Link :size="22" class="text-[var(--color-primary-600)]" stroke-width="1.3" />
      </div>
      <h3 class="mt-4 text-sm font-semibold text-[var(--text-primary)]">
        Connect your professional presence
      </h3>
      <p class="mt-1.5 max-w-xs text-xs leading-relaxed text-[var(--text-muted)]">
        Add LinkedIn, GitHub or your portfolio website.
      </p>
      <div class="mt-5">
        <Button @click="editing = true">Add professional links</Button>
      </div>
    </div>

    <div v-else class="grid gap-3">
      <template v-for="f in fields" :key="f.key">
        <a
          v-if="profile[f.key]"
          :href="normalizeUrl(profile[f.key]!)"
          target="_blank"
          rel="noopener noreferrer"
          :aria-label="`Open ${f.label}`"
          class="group flex items-center gap-3 rounded-xl bg-[var(--surface-primary)] p-4 shadow-[var(--shadow-neo-raised-sm)] transition-all hover:shadow-[var(--shadow-neo-raised)]"
        >
          <div
            class="flex size-10 shrink-0 items-center justify-center rounded-lg shadow-[var(--shadow-neo-inset)]"
            :class="f.iconBg"
          >
            <svg
              :viewBox="f.viewBox"
              :fill="f.filled ? 'currentColor' : 'none'"
              class="size-4"
              :stroke="f.filled ? 'none' : 'currentColor'"
              :stroke-width="f.filled ? 0 : 1.3"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path :d="f.icon" />
            </svg>
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-[var(--text-primary)]">{{ f.label }}</p>
            <p class="mt-0.5 truncate text-xs text-[var(--text-muted)]">
              {{ displayUrl(profile[f.key]!) }}
            </p>
          </div>
          <ExternalLink
            :size="16"
            stroke-width="1.5"
            class="shrink-0 text-[var(--text-muted)] transition-all group-hover:text-[var(--color-primary-600)]"
          />
        </a>
      </template>
    </div>
  </Card>
</template>
