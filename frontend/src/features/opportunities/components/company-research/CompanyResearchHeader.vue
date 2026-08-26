<script setup lang="ts">
import { computed } from 'vue'
import { Building2, ExternalLink } from '@lucide/vue'
import type { CompanyResearchBrief } from '../../types'

const props = defineProps<{ overview: CompanyResearchBrief['overview'] }>()

function domain(url: string): string {
  try {
    return new URL(url).hostname.replace(/^www\./, '')
  } catch {
    return url
  }
}

function isSafeUrl(url: string): boolean {
  try {
    const u = new URL(url)
    return u.protocol === 'https:' || u.protocol === 'http:'
  } catch {
    return false
  }
}

const displayName = computed(() => props.overview?.name?.trim() || 'Company')
const descriptionText = computed(() => props.overview?.description?.trim() || null)
const website = computed(() => props.overview?.website ?? null)
const websiteDomain = computed(() => (website.value ? domain(website.value) : null))
const websiteIsSafe = computed(() => (website.value ? isSafeUrl(website.value) : false))
const industry = computed(() => props.overview?.industry?.trim() || null)
const headquarters = computed(() => props.overview?.headquarters?.trim() || null)

const initials = computed(() => {
  const name = displayName.value
  if (!name || name === 'Company') return 'CO'
  const words = name.split(/\s+/).filter(Boolean).slice(0, 2)
  if (words.length === 1) return words[0]!.slice(0, 2).toUpperCase()
  return (words[0]![0]! + words[1]![0]!).toUpperCase()
})

const hasMetadata = computed(() =>
  Boolean(industry.value || headquarters.value || (website.value && websiteIsSafe.value)),
)
</script>

<template>
  <div
    class="rounded-[var(--radius-xl)] border border-[var(--border-default)] bg-[var(--surface-primary)] p-6 sm:p-7"
  >
    <div class="flex gap-4 sm:gap-5">
      <!-- Logo placeholder monogram -->
      <div
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl border border-[var(--color-primary-100)] bg-[var(--color-primary-50)] shadow-[var(--shadow-neo-raised-sm)] sm:size-16"
        aria-hidden="true"
      >
        <span class="text-lg font-bold tracking-tight text-[var(--color-primary-700)] sm:text-xl">{{
          initials
        }}</span>
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h3
            class="text-[1.375rem] font-bold leading-tight tracking-[-0.015em] text-[var(--text-primary)] sm:text-[1.5rem]"
          >
            {{ displayName }}
          </h3>
          <a
            v-if="website && websiteIsSafe"
            :href="website"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex size-7 items-center justify-center rounded-full border border-[var(--border-default)] bg-white text-[var(--text-muted)] shadow-[var(--shadow-neo-raised-sm)] transition-colors hover:border-[var(--color-primary-200)] hover:text-[var(--color-primary-600)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary-500)]"
            aria-label="Open company website in new tab"
          >
            <ExternalLink class="size-3.5" aria-hidden="true" />
          </a>
          <span
            v-else-if="!websiteIsSafe && website"
            class="inline-flex size-7 items-center justify-center rounded-full border border-[var(--border-default)] bg-white text-[var(--text-muted)] opacity-60"
            aria-hidden="true"
          >
            <Building2 class="size-3.5" />
          </span>
        </div>

        <p
          v-if="descriptionText"
          class="mt-1.5 line-clamp-2 max-w-[60ch] text-sm leading-relaxed text-[var(--text-secondary)]"
        >
          {{ descriptionText }}
        </p>
        <p v-else class="mt-1.5 text-sm text-[var(--text-muted)]">
          Company overview based on verified research.
        </p>

        <!-- Metadata row -->
        <dl v-if="hasMetadata" class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs">
          <div v-if="industry" class="flex items-center gap-1.5">
            <dt class="font-medium text-[var(--text-muted)]">Industry</dt>
            <dd class="font-semibold text-[var(--text-primary)]">{{ industry }}</dd>
          </div>
          <div v-if="headquarters" class="flex items-center gap-1.5">
            <dt class="font-medium text-[var(--text-muted)]">Headquarters</dt>
            <dd class="font-semibold text-[var(--text-primary)]">{{ headquarters }}</dd>
          </div>
          <div v-if="website && websiteIsSafe && websiteDomain" class="flex items-center gap-1.5">
            <dt class="font-medium text-[var(--text-muted)]">Website</dt>
            <dd>
              <a
                :href="website!"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 font-semibold text-[var(--color-primary-600)] underline decoration-[var(--color-primary-200)] underline-offset-2 transition-colors hover:text-[var(--color-primary-700)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primary-500)]"
              >
                {{ websiteDomain }}
                <ExternalLink class="size-3 shrink-0" aria-hidden="true" />
              </a>
            </dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>
