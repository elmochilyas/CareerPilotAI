<script setup lang="ts">
import { computed } from 'vue'
import type { MatchAnalysis } from '../types'
import { countImportant, gapSentence, importantSentence } from '../utils/matchPresentation'
import ScoreAnchor from './ScoreAnchor.vue'

const props = defineProps<{
  analysis: MatchAnalysis
}>()

const score = computed(() => props.analysis.overall_score ?? 0)

const important = computed(() => countImportant(props.analysis.findings))

const sentence = computed(() => importantSentence(important.value))
const gaps = computed(() => gapSentence(important.value.gaps))
</script>

<template>
  <section aria-labelledby="match-summary" class="space-y-6">
    <div class="grid gap-6 sm:grid-cols-[auto_1fr] sm:items-center sm:gap-10">
      <ScoreAnchor :score="score" />

      <div class="min-w-0 sm:border-l sm:border-slate-200 sm:pl-10">
        <h2 id="match-summary" class="text-lg font-semibold tracking-tight text-slate-900">
          {{ sentence }}
        </h2>
        <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-500">{{ gaps }}</p>
      </div>
    </div>
  </section>
</template>
