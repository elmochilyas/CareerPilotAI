<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import SkillChipEditor from '../components/SkillChipEditor.vue'
import ResponsibilityEditor from '../components/ResponsibilityEditor.vue'
import PreviewSummary from '../components/PreviewSummary.vue'
import {
  confirmIngestion,
  fetchIngestion,
  fetchSuggestions,
  generatePreview,
  opportunityKeys,
  updateSuggestion,
} from '../api'
import type { PreviewData } from '../types'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()

const ingestionId = computed(() => Number(route.params.id))
const currentStep = ref(0)
const announcement = ref('')
const previewData = ref<PreviewData | null>(null)
const isConfirming = ref(false)
const confirmedOpportunityId = ref<number | null>(null)

useQuery({
  queryKey: computed(() => opportunityKeys.ingestion(ingestionId.value)),
  queryFn: () => fetchIngestion(ingestionId.value),
})

const { data: suggestions, refetch: refetchSuggestions } = useQuery({
  queryKey: computed(() => opportunityKeys.suggestions(ingestionId.value)),
  queryFn: () => fetchSuggestions(ingestionId.value),
  enabled: true,
})

const allSugs = computed(() => suggestions.value ?? [])

const groupedSteps = computed(() => {
  const groups = [
    {
      key: 'overview',
      label: 'Overview',
      types: [
        'job_title',
        'company',
        'department',
        'external_reference',
        'summary',
        'application_url',
      ],
    },
    {
      key: 'work_details',
      label: 'Work details',
      types: [
        'city',
        'region',
        'country',
        'work_mode',
        'contract_type',
        'seniority_level',
        'working_hours',
        'travel_required',
        'relocation_required',
      ],
    },
    { key: 'responsibilities', label: 'Responsibilities', types: ['responsibility'] },
    {
      key: 'experience_education',
      label: 'Experience & Education',
      types: ['required_experience', 'preferred_experience', 'education'],
    },
    { key: 'required_skills', label: 'Required skills', types: ['required_skill'] },
    { key: 'preferred_skills', label: 'Preferred skills', types: ['preferred_skill'] },
    {
      key: 'languages_certifications',
      label: 'Languages & Certifications',
      types: ['language', 'certification'],
    },
    {
      key: 'compensation',
      label: 'Compensation & Dates',
      types: [
        'compensation',
        'benefit',
        'publication_date',
        'application_deadline',
        'expected_start_date',
        'employment_duration',
      ],
    },
  ]

  return groups.filter((g) => allSugs.value.some((s) => g.types.includes(s.type)))
})

const visibleSteps = computed(() => {
  const steps = [...groupedSteps.value, { key: 'final', label: 'Final review', types: [] }]
  return steps
})

const currentStepMeta = computed(() => visibleSteps.value[currentStep.value] ?? null)
const isLastStep = computed(() => currentStep.value === visibleSteps.value.length - 1)

const pendingCount = computed(
  () => allSugs.value.filter((s) => (s.review_decision as string) === 'pending').length,
)
const allReviewed = computed(() => pendingCount.value === 0)
const hasAmbiguous = computed(() => allSugs.value.some((s) => s.resolution === 'ambiguous'))

function getSuggestionsByGroup(types: string[]) {
  return allSugs.value.filter((s) => types.includes(s.type))
}

async function handleDecision(
  suggestionId: number,
  decision: string,
  edited_value?: Record<string, unknown>,
) {
  try {
    await updateSuggestion(ingestionId.value, suggestionId, { decision, edited_value })
    await refetchSuggestions()
  } catch {
    announcement.value = 'Failed to save decision.'
  }
}

async function generatePreviewAction() {
  try {
    const result = await generatePreview(ingestionId.value)
    previewData.value = result
  } catch (err: unknown) {
    const detail = (err as { response?: { data?: { detail?: string } } })?.response?.data?.detail
    announcement.value = detail ?? 'Failed to generate preview.'
  }
}

async function confirmAction() {
  if (!previewData.value) return

  isConfirming.value = true
  try {
    const result = await confirmIngestion(ingestionId.value, previewData.value.version_token)
    confirmedOpportunityId.value = result.id
    announcement.value = 'Job opportunity confirmed!'
    queryClient.invalidateQueries({ queryKey: opportunityKeys.all })
  } catch (err: unknown) {
    const detail = (err as { response?: { data?: { detail?: string } } })?.response?.data?.detail
    announcement.value = detail ?? 'Confirmation failed.'
  } finally {
    isConfirming.value = false
  }
}

function getFieldValue(
  suggestion: {
    review_decision: string
    edited_value: Record<string, unknown> | null
    extracted_value: Record<string, unknown>
  },
  field = 'value',
) {
  if (suggestion.review_decision === 'edited' && suggestion.edited_value) {
    return suggestion.edited_value[field]
  }
  if (suggestion.review_decision === 'rejected' || suggestion.review_decision === 'keep_blank')
    return null
  return suggestion.extracted_value[field]
}

function extractSummaryOrDegree(ev: Record<string, unknown>): unknown {
  return ev.summary || ev.degree
}

function extractLanguageOrName(ev: Record<string, unknown>): unknown {
  return ev.language || ev.name
}

onMounted(() => {
  refetchSuggestions()
})
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Review job information</h1>
      <span class="text-sm text-gray-400">
        Step {{ currentStep + 1 }} of {{ visibleSteps.length }}
      </span>
    </div>

    <div
      v-if="confirmedOpportunityId"
      class="rounded-lg border border-green-200 bg-green-50 p-6 text-center"
    >
      <h2 class="text-lg font-semibold text-green-800">Job opportunity confirmed!</h2>
      <button
        class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        @click="router.push(`/opportunities/${confirmedOpportunityId}`)"
      >
        View opportunity
      </button>
      <button
        class="ml-3 rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        @click="router.push('/opportunities')"
      >
        Back to list
      </button>
    </div>

    <div v-else class="space-y-6">
      <div class="flex gap-2 overflow-x-auto pb-2">
        <button
          v-for="(step, i) in visibleSteps"
          :key="step.key"
          class="flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium"
          :class="
            i === currentStep
              ? 'bg-blue-600 text-white'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
          "
          @click="currentStep = i"
        >
          {{ step.label }}
        </button>
      </div>

      <div
        v-if="announcement"
        class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700"
      >
        {{ announcement }}
      </div>

      <div v-if="currentStepMeta?.key === 'overview'" class="space-y-4">
        <h2 class="text-lg font-medium">Overview</h2>
        <div
          v-for="s in getSuggestionsByGroup([
            'job_title',
            'company',
            'department',
            'external_reference',
            'summary',
            'application_url',
          ])"
          :key="s.id"
          class="rounded-lg border border-gray-200 p-3"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs text-gray-400">{{ s.type }}</p>
              <p class="text-sm">{{ getFieldValue(s) ?? '(blank)' }}</p>
            </div>
            <div class="flex gap-1">
              <button
                class="rounded px-2 py-1 text-xs hover:bg-gray-100"
                :class="
                  s.review_decision === 'accepted' ? 'bg-green-100 text-green-700' : 'text-gray-500'
                "
                @click="handleDecision(s.id, 'accepted')"
              >
                Keep
              </button>
              <button
                class="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
                @click="handleDecision(s.id, 'rejected')"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="currentStepMeta?.key === 'work_details'" class="space-y-4">
        <h2 class="text-lg font-medium">Work details</h2>
        <div
          v-for="s in getSuggestionsByGroup([
            'city',
            'region',
            'country',
            'work_mode',
            'contract_type',
            'seniority_level',
            'working_hours',
            'travel_required',
            'relocation_required',
          ])"
          :key="s.id"
          class="rounded-lg border border-gray-200 p-3"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs text-gray-400">{{ s.type }}</p>
              <p class="text-sm">{{ getFieldValue(s) ?? '(blank)' }}</p>
            </div>
            <div class="flex gap-1">
              <button
                class="rounded px-2 py-1 text-xs hover:bg-gray-100"
                :class="
                  s.review_decision === 'accepted' ? 'bg-green-100 text-green-700' : 'text-gray-500'
                "
                @click="handleDecision(s.id, 'accepted')"
              >
                Keep
              </button>
              <button
                class="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
                @click="handleDecision(s.id, 'rejected')"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="currentStepMeta?.key === 'responsibilities'" class="space-y-4">
        <h2 class="text-lg font-medium">Responsibilities</h2>
        <ResponsibilityEditor
          v-for="s in getSuggestionsByGroup(['responsibility'])"
          :key="s.id"
          :suggestion="s"
          @keep="handleDecision(s.id, 'accepted')"
          @remove="handleDecision(s.id, 'rejected')"
        />
      </div>

      <div v-if="currentStepMeta?.key === 'experience_education'" class="space-y-4">
        <h2 class="text-lg font-medium">Experience & Education</h2>
        <div
          v-for="s in getSuggestionsByGroup([
            'required_experience',
            'preferred_experience',
            'education',
          ])"
          :key="s.id"
          class="rounded-lg border border-gray-200 p-3"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs text-gray-400">{{ s.type }}</p>
              <p class="text-sm">{{ extractSummaryOrDegree(s.extracted_value) }}</p>
            </div>
            <div class="flex gap-1">
              <button
                class="rounded px-2 py-1 text-xs hover:bg-gray-100"
                :class="
                  s.review_decision === 'accepted' ? 'bg-green-100 text-green-700' : 'text-gray-500'
                "
                @click="handleDecision(s.id, 'accepted')"
              >
                Keep
              </button>
              <button
                class="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
                @click="handleDecision(s.id, 'rejected')"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="currentStepMeta?.key === 'preferred_skills'" class="space-y-4">
        <h2 class="text-lg font-medium">Preferred skills</h2>
        <SkillChipEditor
          v-for="s in getSuggestionsByGroup(['preferred_skill'])"
          :key="s.id"
          :suggestion="s"
          @keep="handleDecision(s.id, 'accepted')"
          @remove="handleDecision(s.id, 'rejected')"
        />
      </div>

      <div v-if="currentStepMeta?.key === 'required_skills'" class="space-y-4">
        <h2 class="text-lg font-medium">Required skills</h2>
        <SkillChipEditor
          v-for="s in getSuggestionsByGroup(['required_skill'])"
          :key="s.id"
          :suggestion="s"
          @keep="handleDecision(s.id, 'accepted')"
          @remove="handleDecision(s.id, 'rejected')"
        />
      </div>

      <div v-if="currentStepMeta?.key === 'languages_certifications'" class="space-y-4">
        <h2 class="text-lg font-medium">Languages & Certifications</h2>
        <div
          v-for="s in getSuggestionsByGroup(['language', 'certification'])"
          :key="s.id"
          class="rounded-lg border border-gray-200 p-3"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs text-gray-400">{{ s.type }}</p>
              <p class="text-sm">{{ extractLanguageOrName(s.extracted_value) }}</p>
            </div>
            <div class="flex gap-1">
              <button
                class="rounded px-2 py-1 text-xs hover:bg-gray-100"
                :class="
                  s.review_decision === 'accepted' ? 'bg-green-100 text-green-700' : 'text-gray-500'
                "
                @click="handleDecision(s.id, 'accepted')"
              >
                Keep
              </button>
              <button
                class="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
                @click="handleDecision(s.id, 'rejected')"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="currentStepMeta?.key === 'compensation'" class="space-y-4">
        <h2 class="text-lg font-medium">Compensation & Dates</h2>
        <div
          v-for="s in getSuggestionsByGroup([
            'compensation',
            'benefit',
            'publication_date',
            'application_deadline',
            'expected_start_date',
            'employment_duration',
          ])"
          :key="s.id"
          class="rounded-lg border border-gray-200 p-3"
        >
          <div class="flex items-start justify-between">
            <div>
              <p class="text-xs text-gray-400">{{ s.type }}</p>
              <p class="text-sm">{{ getFieldValue(s) ?? '(blank)' }}</p>
            </div>
            <div class="flex gap-1">
              <button
                class="rounded px-2 py-1 text-xs hover:bg-gray-100"
                :class="
                  s.review_decision === 'accepted' ? 'bg-green-100 text-green-700' : 'text-gray-500'
                "
                @click="handleDecision(s.id, 'accepted')"
              >
                Keep
              </button>
              <button
                class="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
                @click="handleDecision(s.id, 'rejected')"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="currentStepMeta?.key === 'final'" class="space-y-4">
        <h2 class="text-lg font-medium">Final Review</h2>

        <div class="space-y-2">
          <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3">
            <span class="text-sm">Decisions completed</span>
            <span
              class="text-sm font-medium"
              :class="allReviewed ? 'text-green-600' : 'text-amber-600'"
            >
              {{ allSugs.length - pendingCount }}/{{ allSugs.length }}
            </span>
          </div>
          <div v-if="hasAmbiguous" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-700">
            Some skills need resolution before confirming.
          </div>
        </div>

        <button
          v-if="!previewData"
          class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
          :disabled="!allReviewed || hasAmbiguous"
          @click="generatePreviewAction"
        >
          Generate preview
        </button>

        <div v-if="previewData" class="space-y-4">
          <PreviewSummary :preview="previewData" />

          <button
            class="rounded-lg bg-green-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="isConfirming || !allReviewed || hasAmbiguous"
            @click="confirmAction"
          >
            {{ isConfirming ? 'Confirming...' : 'Confirm opportunity' }}
          </button>
        </div>
      </div>

      <div class="flex justify-between border-t border-gray-200 pt-4">
        <button
          v-if="currentStep > 0"
          class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="currentStep--"
        >
          Previous
        </button>
        <div v-else />
        <button
          v-if="!isLastStep"
          class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
          @click="currentStep++"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>
