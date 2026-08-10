<script setup lang="ts">
import { computed } from 'vue'
import {
  ArrowRight,
  Briefcase,
  FileSearch,
  Globe,
  Layers,
  MessageCircle,
  Wrench,
} from '@lucide/vue'
import { RouterLink, useRouter } from 'vue-router'
import type { ClarificationQuestion } from '@/features/clarification/types'
import EmptyState from '@/components/ui/EmptyState.vue'
import type { MatchCategory, MatchFinding } from '../types'
import { groupFindings } from '../utils/matchPresentation'
import GapItem from './GapItem.vue'

const props = defineProps<{
  findings: MatchFinding[]
  questions: ClarificationQuestion[]
  opportunityId: number
}>()

const router = useRouter()

const grouped = computed(() => groupFindings(props.findings))

const totalGaps = computed(() => props.findings.filter((f) => f.match_state === 'gap').length)

const categoryCounts = computed(() => {
  const gaps = props.findings.filter((f) => f.match_state === 'gap')
  const counts: Record<string, number> = {}
  for (const f of gaps) {
    const key = f.category ?? 'other'
    counts[key] = (counts[key] ?? 0) + 1
  }
  return counts
})

const categoryMeta: Record<MatchCategory, { icon: typeof Wrench; label: string; color: string }> = {
  required_skills: { icon: Wrench, label: 'Skills', color: 'text-[var(--color-primary-600)]' },
  preferred_skills: { icon: Wrench, label: 'Skills', color: 'text-[var(--color-primary-600)]' },
  experience_education: {
    icon: Briefcase,
    label: 'Experience',
    color: 'text-[var(--color-info-600)]',
  },
  language_soft: { icon: Globe, label: 'Languages', color: 'text-[var(--color-success-600)]' },
  evidence: { icon: FileSearch, label: 'Evidence', color: 'text-[var(--color-warning-600)]' },
}

const overviewItems = computed(() => {
  return Object.entries(categoryCounts.value)
    .filter(([key]) => key !== 'other')
    .map(([key, count]) => {
      const meta = categoryMeta[key as MatchCategory]
      return { key, count, ...meta }
    })
    .sort((a, b) => b.count - a.count)
})

function findQuestionForFinding(finding: MatchFinding): ClarificationQuestion | null {
  return (
    props.questions.find(
      (q) => q.requirement?.text === finding.requirement_text && q.status === 'pending',
    ) ?? null
  )
}

function answerQuestion(): void {
  void router.push({
    name: 'opportunities-match-clarifications',
    params: { id: props.opportunityId },
  })
}
</script>

<template>
  <div class="space-y-8">
    <header class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-[var(--text-primary)]">Gaps to review</h1>
        <p class="mt-1 text-sm leading-relaxed text-[var(--text-secondary)]">
          {{ totalGaps }} requirement{{ totalGaps === 1 ? '' : 's' }} need attention before you're a
          stronger match.
        </p>
      </div>
      <RouterLink
        :to="{
          name: 'opportunities-match-clarifications',
          params: { id: opportunityId },
        }"
        class="inline-flex shrink-0 items-center gap-2 rounded-[var(--radius-lg)] bg-[var(--color-primary-600)] px-4 py-2.5 text-sm font-semibold text-white shadow-[var(--shadow-neo-raised-sm)] transition-shadow hover:bg-[var(--color-primary-700)] hover:shadow-[var(--shadow-neo-raised)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:ring-offset-2"
      >
        <MessageCircle class="size-4" aria-hidden="true" />
        Validate gaps
        <ArrowRight class="size-3.5" aria-hidden="true" />
      </RouterLink>
    </header>

    <!-- Overview card with category breakdown -->
    <div
      v-if="overviewItems.length > 0"
      class="rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-5 shadow-[var(--shadow-neo-raised)] sm:p-6"
    >
      <div class="flex items-center gap-2 mb-4">
        <Layers class="size-4 text-[var(--text-muted)]" aria-hidden="true" />
        <h2 class="text-sm font-semibold text-[var(--text-primary)]">Gap breakdown</h2>
      </div>

      <div class="flex flex-wrap gap-3">
        <div
          v-for="item in overviewItems"
          :key="item.key"
          class="flex items-center gap-2 rounded-[var(--radius-lg)] bg-[var(--surface-secondary)] px-3.5 py-2 shadow-[var(--shadow-neo-raised-sm)]"
        >
          <component
            :is="item.icon"
            class="size-4 shrink-0"
            :class="item.color"
            aria-hidden="true"
          />
          <span class="text-sm font-medium text-[var(--text-primary)]">{{ item.count }}</span>
          <span class="text-xs text-[var(--text-muted)]">{{ item.label }}</span>
        </div>
      </div>

      <!-- Visual bar -->
      <div
        class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-[var(--surface-secondary)] shadow-[var(--shadow-neo-inset)]"
      >
        <div
          v-for="item in overviewItems"
          :key="item.key"
          class="transition-all"
          :class="{
            'bg-[var(--color-primary-500)]':
              item.key === 'required_skills' || item.key === 'preferred_skills',
            'bg-[var(--color-info-500)]': item.key === 'experience_education',
            'bg-[var(--color-success-500)]': item.key === 'language_soft',
            'bg-[var(--color-warning-500)]': item.key === 'evidence',
          }"
          :style="{ width: `${(item.count / totalGaps) * 100}%` }"
        />
      </div>
    </div>

    <!-- Gap sections by category -->
    <template v-if="grouped.length > 0">
      <section v-for="section in grouped" :key="section.group.key" class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <h2 class="text-sm font-semibold text-[var(--text-primary)]">
              {{ section.group.label }}
            </h2>
            <span
              class="inline-flex items-center justify-center rounded-full bg-[var(--color-neutral-100)] px-2 py-0.5 text-xs font-bold tabular-nums text-[var(--text-tertiary)]"
            >
              {{ section.items.length }}
            </span>
          </div>
        </div>

        <div class="space-y-4">
          <GapItem
            v-for="(finding, itemIndex) in section.items"
            :key="`${finding.source_type}:${finding.source_id}`"
            :finding="finding"
            :index="itemIndex + 1"
            :question="findQuestionForFinding(finding)"
            @answer-question="answerQuestion"
          />
        </div>
      </section>
    </template>

    <EmptyState
      v-else
      title="No gaps to review"
      description="This analysis has no gap requirements. You're in good shape!"
    />

    <!-- Bottom CTA -->
    <div
      class="flex items-center justify-between rounded-[var(--radius-xl)] bg-[var(--surface-secondary)] px-6 py-4 shadow-[var(--shadow-neo-raised-sm)]"
    >
      <p class="text-sm text-[var(--text-secondary)]">Want the full picture?</p>
      <RouterLink
        :to="{
          name: 'opportunities-match',
          params: { id: opportunityId },
          query: { view: 'analysis' },
        }"
        class="inline-flex items-center gap-1.5 rounded-[var(--radius-lg)] bg-[var(--surface-primary)] px-4 py-2 text-sm font-medium text-[var(--text-primary)] shadow-[var(--shadow-neo-raised-sm)] transition-all hover:shadow-[var(--shadow-neo-raised)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
      >
        View full analysis
        <ArrowRight class="size-3.5 text-[var(--text-muted)]" aria-hidden="true" />
      </RouterLink>
    </div>
  </div>
</template>
