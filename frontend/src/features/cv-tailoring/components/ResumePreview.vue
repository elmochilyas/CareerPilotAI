<script setup lang="ts">
import { computed } from 'vue'
import type { Resume, ResumePreview as PreviewData } from '../types'

const props = defineProps<{
  resume: Resume
  preview: PreviewData | null
  isLoading: boolean
  isGenerating: boolean
  generationFailed: boolean
  showActions?: boolean
}>()

const emit = defineEmits<{
  next: []
  generate: []
}>()

const hasPreviewContent = computed(
  () =>
    !!props.preview &&
    (!!props.preview.headline || !!props.preview.summary || props.preview.sections.length > 0),
)

function sectionIcon(type: string): string {
  const icons: Record<string, string> = {
    summary: 'M4 6h16M4 12h16M4 18h7',
    experience:
      'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    education:
      'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z',
    skills:
      'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
    projects: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    certifications:
      'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
  }
  return (icons[type] ?? icons.summary) as string
}
</script>

<template>
  <div class="space-y-6">
    <div
      v-if="isLoading || isGenerating"
      class="flex items-center justify-center py-12"
      role="status"
      aria-live="polite"
    >
      <div class="flex items-center gap-3 text-sm text-[var(--text-secondary)]">
        <div
          class="size-5 animate-spin rounded-full border-2 border-slate-200 border-t-[var(--color-primary-600)]"
          aria-hidden="true"
        />
        {{ isGenerating ? 'Generating your tailored CV…' : 'Loading preview…' }}
      </div>
    </div>

    <template v-else-if="hasPreviewContent && preview">
      <div v-if="preview.headline" class="rounded-xl bg-[var(--color-primary-50)] p-4">
        <p class="text-sm font-semibold text-[var(--color-primary-800)]">{{ preview.headline }}</p>
      </div>

      <div v-if="preview.summary" class="space-y-2">
        <h3 class="text-sm font-semibold text-[var(--text-primary)]">Professional Summary</h3>
        <p class="text-sm leading-relaxed text-[var(--text-secondary)]">{{ preview.summary }}</p>
      </div>

      <div v-for="section in preview.sections" :key="section.type" class="space-y-3">
        <div class="flex items-center gap-2">
          <div
            class="flex size-7 items-center justify-center rounded-lg bg-[var(--color-primary-100)]"
          >
            <svg
              class="size-4 text-[var(--color-primary-600)]"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path :d="sectionIcon(section.type)" />
            </svg>
          </div>
          <h3 class="text-sm font-semibold text-[var(--text-primary)]">{{ section.title }}</h3>
          <span
            v-if="section.has_changes"
            class="inline-flex items-center rounded-md bg-[var(--color-warning-50)] px-1.5 py-0.5 text-[10px] font-semibold text-[var(--color-warning-700)]"
          >
            Edited
          </span>
        </div>

        <div class="ml-9 space-y-2">
          <div
            v-for="(item, itemIndex) in section.items"
            :key="item.source_ref + '-' + itemIndex"
            class="rounded-lg border border-[var(--color-neutral-100)] bg-[var(--surface-page)] p-3"
          >
            <p class="text-sm text-[var(--text-primary)]">{{ item.current_text }}</p>
            <p
              v-if="item.original_text !== item.current_text"
              class="mt-1 text-xs text-[var(--text-muted)] line-through"
            >
              {{ item.original_text }}
            </p>
          </div>
        </div>
      </div>

      <div v-if="showActions" class="flex justify-end pt-4">
        <button
          class="inline-flex items-center gap-2 rounded-xl bg-[var(--color-primary-600)] px-4 py-2 text-sm font-medium text-white shadow-[var(--shadow-neo-button)] transition-all hover:bg-[var(--color-primary-700)] active:scale-[0.97]"
          @click="emit('next')"
        >
          Continue to Review
          <svg
            class="size-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M5 12h14M12 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </template>

    <div v-else class="py-12 text-center">
      <p class="text-sm font-medium text-[var(--text-primary)]">
        Your tailored CV hasn’t been generated yet.
      </p>
      <p v-if="generationFailed" class="mt-2 text-sm text-[var(--color-error-700)]" role="alert">
        Generation failed. Your existing CV data is safe. Please try again.
      </p>
      <button
        class="mt-4 inline-flex items-center rounded-xl bg-[var(--color-primary-600)] px-4 py-2 text-sm font-medium text-white shadow-[var(--shadow-neo-button)] transition-all hover:bg-[var(--color-primary-700)] active:scale-[0.97]"
        @click="emit('generate')"
      >
        {{ generationFailed ? 'Try generation again' : 'Generate tailored CV' }}
      </button>
    </div>
  </div>
</template>
