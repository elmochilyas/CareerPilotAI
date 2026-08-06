<script setup lang="ts">
import { computed } from 'vue'
import { AlertCircle, Check, ChevronRight } from '@lucide/vue'
import Button from '@/components/ui/Button.vue'
import type { MatchFinding } from '../types'
import { countUnknown, findingKey, gapsOf, labelOf, strengthsOf } from '../utils/matchPresentation'

const props = defineProps<{
  findings: MatchFinding[]
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
    class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8"
  >
    <header class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 id="at-a-glance" class="text-lg font-semibold tracking-tight text-slate-900">
          Your match at a glance
        </h2>
        <p class="mt-0.5 text-sm text-slate-500">The requirements that matter most.</p>
      </div>
      <Button v-if="gaps.length > 0" @click="emit('view-gaps')">Review gaps</Button>
      <Button v-else variant="outline" @click="emit('expand-all')">View full analysis</Button>
    </header>

    <div class="mt-6 grid gap-8 sm:grid-cols-2 sm:gap-10">
      <div aria-label="Strengths">
        <h3 class="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
          <Check class="size-4 text-emerald-600" aria-hidden="true" />
          Strengths
        </h3>
        <ul v-if="topStrengths.length" class="mt-3 space-y-2.5">
          <li
            v-for="finding in topStrengths"
            :key="findingKey(finding)"
            class="text-sm text-slate-700"
          >
            {{ labelOf(finding) }}
          </li>
        </ul>
        <p v-else class="mt-3 text-sm text-slate-400">No confirmed strengths yet.</p>

        <p v-if="remainingStrengths > 0" class="mt-3 text-sm text-slate-500">
          + {{ remainingStrengths }} more strength{{ remainingStrengths === 1 ? '' : 's' }}
          <button
            type="button"
            class="inline-flex items-center gap-0.5 font-medium text-primary-700 hover:text-primary-800 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none"
            @click="emit('expand-all')"
          >
            View all
            <ChevronRight class="size-3.5" aria-hidden="true" />
          </button>
        </p>
      </div>

      <div aria-label="Needs attention">
        <h3 class="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
          <AlertCircle class="size-4 text-red-500" aria-hidden="true" />
          Needs attention
        </h3>
        <ul v-if="topGaps.length" class="mt-3 space-y-2.5">
          <li v-for="finding in topGaps" :key="findingKey(finding)" class="text-sm text-slate-700">
            {{ labelOf(finding) }}
          </li>
        </ul>
        <p v-else class="mt-3 text-sm text-slate-400">No gaps to address.</p>

        <p v-if="remainingGaps > 0" class="mt-3 text-sm text-slate-500">
          <button
            type="button"
            class="inline-flex items-center gap-0.5 font-medium text-primary-700 hover:text-primary-800 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none"
            @click="emit('view-gaps')"
          >
            View all {{ remainingGaps }} gap{{ remainingGaps === 1 ? '' : 's' }}
            <ChevronRight class="size-3.5" aria-hidden="true" />
          </button>
        </p>
      </div>
    </div>

    <p v-if="unknownCount > 0" class="mt-6 border-t border-slate-100 pt-4 text-sm text-slate-500">
      {{ unknownCount }} requirement{{ unknownCount === 1 ? '' : 's' }} could not be fully
      evaluated.
      <button
        type="button"
        class="font-medium text-slate-700 underline decoration-slate-300 underline-offset-2 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none"
        @click="emit('expand-unknown')"
      >
        Review them
      </button>
    </p>
  </section>
</template>
