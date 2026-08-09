<script setup lang="ts">
import { ArrowRight, Briefcase, FileSearch, MessageCircle, Wrench, Globe } from '@lucide/vue'
import type { ClarificationQuestion } from '@/features/clarification/types'
import type { MatchCategory, MatchFinding } from '../types'
import { labelOf, whatWeFound, suggestedNextStep } from '../utils/matchPresentation'

const props = defineProps<{
  finding: MatchFinding
  index: number
  question: ClarificationQuestion | null
}>()

const emit = defineEmits<{
  'answer-question': []
}>()

const { finding } = props
const formattedIndex = String(props.index).padStart(2, '0')

const isRequired = finding.importance === 'required'

const categoryIcons: Record<MatchCategory, typeof Wrench> = {
  required_skills: Wrench,
  preferred_skills: Wrench,
  experience_education: Briefcase,
  language_soft: Globe,
  evidence: FileSearch,
}

const categoryLabels: Record<MatchCategory, string> = {
  required_skills: 'Skill',
  preferred_skills: 'Skill',
  experience_education: 'Experience',
  language_soft: 'Language',
  evidence: 'Evidence',
}

const CategoryIcon = finding.category ? categoryIcons[finding.category] : FileSearch
const categoryLabel = finding.category ? categoryLabels[finding.category] : 'Requirement'

const hasEvidence = finding.evidence_refs.length > 0
</script>

<template>
  <article
    class="gap-card rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-5 shadow-[var(--shadow-neo-raised)] transition-shadow hover:shadow-[var(--shadow-neo-raised-lg)] sm:p-6"
  >
    <!-- Header row: importance badge + category + index -->
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <span
          class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest shadow-[var(--shadow-neo-raised-sm)]"
          :class="
            isRequired
              ? 'bg-[var(--color-error-50)]/60 text-[var(--color-error-600)]'
              : 'bg-[var(--color-warning-50)]/60 text-[var(--color-warning-600)]'
          "
        >
          {{ isRequired ? 'Required' : 'Preferred' }}
        </span>
        <span class="flex items-center gap-1 text-xs text-[var(--text-muted)]">
          <CategoryIcon class="size-3.5" aria-hidden="true" />
          {{ categoryLabel }}
        </span>
      </div>
      <span
        class="flex size-7 shrink-0 items-center justify-center rounded-full bg-[var(--color-neutral-100)] text-xs font-bold tabular-nums text-[var(--text-tertiary)]"
        aria-hidden="true"
      >
        {{ formattedIndex }}
      </span>
    </div>

    <!-- Requirement label -->
    <h3 class="mt-3 text-base font-semibold leading-snug text-[var(--text-primary)]">
      {{ labelOf(finding) }}
    </h3>

    <!-- Job needs + What we found side by side -->
    <div class="mt-4 grid grid-cols-2 gap-3">
      <div
        class="rounded-[var(--radius-lg)] bg-[var(--surface-primary)] px-4 py-3 shadow-[var(--shadow-neo-raised-sm)]"
      >
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--text-muted)]">
          What the job needs
        </p>
        <p class="mt-1.5 text-sm leading-relaxed text-[var(--text-primary)]">
          {{ finding.requirement_label ?? finding.requirement_text }}
        </p>
      </div>
      <div
        class="rounded-[var(--radius-lg)] bg-[var(--surface-primary)] px-4 py-3 shadow-[var(--shadow-neo-raised-sm)]"
      >
        <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--text-muted)]">
          What we found
        </p>
        <p
          class="mt-1.5 text-sm leading-relaxed"
          :class="hasEvidence ? 'text-[var(--color-success-700)]' : 'text-[var(--text-tertiary)]'"
        >
          {{ whatWeFound(finding) }}
        </p>
      </div>
    </div>

    <!-- Suggested next step callout -->
    <div
      class="mt-3 flex items-start gap-2.5 rounded-[var(--radius-lg)] px-4 py-3"
      :class="
        isRequired
          ? 'bg-[var(--color-error-50)] ring-1 ring-[var(--color-error-100)]'
          : 'bg-[var(--color-warning-50)] ring-1 ring-[var(--color-warning-100)]'
      "
    >
      <ArrowRight
        class="mt-0.5 size-4 shrink-0"
        :class="isRequired ? 'text-[var(--color-error-500)]' : 'text-[var(--color-warning-500)]'"
        aria-hidden="true"
      />
      <p
        class="text-sm font-medium leading-snug"
        :class="isRequired ? 'text-[var(--color-error-700)]' : 'text-[var(--color-warning-700)]'"
      >
        {{ suggestedNextStep(finding) }}
      </p>
    </div>

    <!-- Answer question button -->
    <button
      v-if="question"
      type="button"
      class="mt-4 inline-flex items-center gap-2 rounded-[var(--radius-lg)] bg-[var(--surface-secondary)] px-4 py-2 text-sm font-medium text-[var(--text-primary)] shadow-[var(--shadow-neo-raised-sm)] transition-all hover:shadow-[var(--shadow-neo-raised)] hover:bg-[var(--surface-primary)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
      @click="emit('answer-question')"
    >
      <MessageCircle class="size-4 text-[var(--color-primary-500)]" aria-hidden="true" />
      Answer clarification question
      <ArrowRight class="size-3.5 text-[var(--text-muted)]" aria-hidden="true" />
    </button>
  </article>
</template>
