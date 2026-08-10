<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, Check, CheckCircle2, ChevronRight } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'
import type { MatchFinding } from '../types'
import { countUnknown, findingKey, gapsOf, labelOf, strengthsOf } from '../utils/matchPresentation'

const props = defineProps<{
  findings: MatchFinding[]
  gapReviewCompleted?: boolean
}>()

const emit = defineEmits<{
  'view-gaps': []
  'expand-all': []
  'expand-unknown': []
}>()

const LIMIT = 3

const strengths = computed(() => strengthsOf(props.findings))
const gaps = computed(() => gapsOf(props.findings))

const topStrengths = computed(() => strengths.value.slice(0, LIMIT))
const topGaps = computed(() => gaps.value.slice(0, LIMIT))

const remainingStrengths = computed(() => Math.max(0, strengths.value.length - LIMIT))
const remainingGaps = computed(() => Math.max(0, gaps.value.length - LIMIT))

const unknownCount = computed(() => countUnknown(props.findings))
</script>

<template>
  <section
    aria-labelledby="at-a-glance"
    class="rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-6 shadow-[var(--shadow-neo-raised)] sm:p-8"
  >
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2
          id="at-a-glance"
          class="text-lg font-semibold tracking-tight text-[var(--text-primary)]"
        >
          Your match at a glance
        </h2>
        <p class="mt-0.5 text-sm text-[var(--text-secondary)]">
          The requirements that matter most.
        </p>
      </div>
      <div v-if="gaps.length > 0" class="flex flex-wrap items-center gap-2">
        <span
          v-if="gapReviewCompleted"
          role="status"
          class="inline-flex items-center gap-1.5 rounded-full bg-[var(--color-success-50)] px-3 py-1.5 text-sm font-semibold text-[var(--color-success-700)]"
        >
          <CheckCircle2 class="size-4" aria-hidden="true" />
          Gap review complete
        </span>
        <Button :variant="gapReviewCompleted ? 'outline' : 'primary'" @click="emit('view-gaps')">
          {{ gapReviewCompleted ? 'Review gaps again' : 'Review gaps' }}
        </Button>
      </div>
      <Button v-else variant="outline" @click="emit('expand-all')">View full analysis</Button>
    </header>

    <p
      v-if="gapReviewCompleted && gaps.length > 0"
      class="mt-4 rounded-lg bg-[var(--color-success-50)] px-4 py-3 text-sm text-[var(--color-success-800)]"
    >
      You reviewed these gaps. They remain listed until your profile changes and the match is
      recalculated.
    </p>

    <div class="mt-6 grid gap-8 sm:grid-cols-2 sm:gap-10">
      <div aria-label="Strengths">
        <h3 class="flex items-center gap-1.5 text-sm font-semibold text-[var(--text-primary)]">
          <Check class="size-4 text-[var(--color-success-600)]" aria-hidden="true" />
          Strengths
        </h3>
        <ul v-if="topStrengths.length" class="mt-3 space-y-2.5">
          <li
            v-for="finding in topStrengths"
            :key="findingKey(finding)"
            class="text-sm text-[var(--text-primary)]"
          >
            {{ labelOf(finding) }}
          </li>
        </ul>
        <p v-else class="mt-3 text-sm text-[var(--text-muted)]">No confirmed strengths yet.</p>

        <p v-if="remainingStrengths > 0" class="mt-3 text-sm text-[var(--text-secondary)]">
          + {{ remainingStrengths }} more strength{{ remainingStrengths === 1 ? '' : 's' }}
          <button
            type="button"
            class="inline-flex items-center gap-0.5 font-medium text-[var(--color-primary-700)] hover:text-[var(--color-primary-800)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
            @click="emit('expand-all')"
          >
            View all
            <ChevronRight class="size-3.5" aria-hidden="true" />
          </button>
        </p>
      </div>

      <div aria-label="Needs attention">
        <h3 class="flex items-center gap-1.5 text-sm font-semibold text-[var(--text-primary)]">
          <AlertCircle class="size-4 text-[var(--color-error-500)]" aria-hidden="true" />
          Needs attention
        </h3>
        <ul v-if="topGaps.length" class="mt-3 space-y-2.5">
          <li v-for="finding in topGaps" :key="findingKey(finding)" class="text-sm text-slate-700">
            {{ labelOf(finding) }}
          </li>
        </ul>
        <p v-else class="mt-3 text-sm text-[var(--text-muted)]">No gaps to address.</p>

        <p v-if="remainingGaps > 0" class="mt-3 text-sm text-[var(--text-secondary)]">
          <button
            type="button"
            class="inline-flex items-center gap-0.5 font-medium text-[var(--color-primary-700)] hover:text-[var(--color-primary-800)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
            @click="emit('view-gaps')"
          >
            View all {{ remainingGaps }} gap{{ remainingGaps === 1 ? '' : 's' }}
            <ChevronRight class="size-3.5" aria-hidden="true" />
          </button>
        </p>
      </div>
    </div>

    <p
      v-if="unknownCount > 0"
      class="mt-6 border-t border-[var(--border-subtle)] pt-4 text-sm text-[var(--text-secondary)]"
    >
      {{ unknownCount }} requirement{{ unknownCount === 1 ? '' : 's' }} could not be fully
      evaluated.
      <button
        type="button"
        class="font-medium text-[var(--text-primary)] underline decoration-[var(--border-default)] underline-offset-2 hover:text-[var(--text-primary)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
        @click="emit('expand-unknown')"
      >
        Review them
      </button>
    </p>
  </section>
</template>
