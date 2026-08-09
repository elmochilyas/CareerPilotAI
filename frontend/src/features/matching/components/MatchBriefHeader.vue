<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { ChevronDown } from '@lucide/vue'
import type { JobOpportunity } from '@/features/opportunities/types'

const props = defineProps<{
  opportunity: JobOpportunity | null
  classifierUnavailable?: boolean
}>()

const location = computed(() =>
  [props.opportunity?.city, props.opportunity?.region, props.opportunity?.country]
    .filter((part): part is string => Boolean(part))
    .join(', '),
)

const contextParts = computed(() =>
  [props.opportunity?.company_name, location.value, props.opportunity?.work_mode].filter(
    (part): part is string => Boolean(part),
  ),
)
</script>

<template>
  <header class="space-y-4">
    <RouterLink
      v-if="opportunity"
      :to="{ name: 'opportunities-detail', params: { id: opportunity.id } }"
      class="inline-flex items-center gap-1 rounded-[var(--radius-md)] px-3 py-1.5 text-sm font-medium text-[var(--color-primary-700)] shadow-[var(--shadow-neo-raised-sm)] transition-all hover:text-[var(--color-primary-800)] hover:shadow-[var(--shadow-neo-button)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
    >
      &larr; Back to opportunity
    </RouterLink>

    <div>
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
        {{ opportunity?.title ?? 'Career Intelligence Brief' }}
      </h1>
      <p v-if="contextParts.length" class="mt-1 text-sm text-slate-500">
        {{ contextParts.join(' · ') }}
      </p>
    </div>

    <details
      v-if="classifierUnavailable"
      role="status"
      aria-live="polite"
      class="group max-w-2xl rounded-[var(--radius-xl)] bg-[var(--color-warning-50)] px-4 py-3 text-sm text-amber-900 shadow-[var(--shadow-neo-raised-sm)]"
    >
      <p class="flex items-center gap-2">
        <span class="font-medium">
          Some requirements couldn't be verified automatically. Your score is based on the
          information we could confirm.
        </span>
      </p>
      <summary
        class="mt-2 inline-flex cursor-pointer list-none items-center gap-1 text-xs font-medium text-amber-800 hover:text-amber-900 focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:outline-none"
      >
        Learn more
        <ChevronDown
          class="size-3.5 transition-transform group-open:rotate-180"
          aria-hidden="true"
        />
      </summary>
      <p class="mt-1.5 text-xs leading-relaxed text-amber-800">
        The semantic comparison was unavailable, so some requirements were left unevaluated.
      </p>
    </details>
  </header>
</template>
