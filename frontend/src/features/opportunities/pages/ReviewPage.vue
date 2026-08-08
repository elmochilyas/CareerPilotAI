<script setup lang="ts">
import { computed, onMounted, ref, shallowRef } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import OpportunityPageHeader from '../components/OpportunityPageHeader.vue'
import OpportunityIdentitySummary from '../components/OpportunityIdentitySummary.vue'
import ResponsibilityReviewItem from '../components/ResponsibilityReviewItem.vue'
import ScalarReviewItem from '../components/ScalarReviewItem.vue'
import SkillChipEditor from '../components/SkillChipEditor.vue'
import PreviewSummary from '../components/PreviewSummary.vue'
import ReviewTimeline from '../components/ReviewTimeline.vue'
import ReviewActionFooter from '../components/ReviewActionFooter.vue'
import {
  addManualSuggestion,
  batchUpdateSuggestions,
  confirmIngestion,
  fetchIngestion,
  fetchSuggestions,
  generatePreview,
  opportunityKeys,
  updateSuggestion,
} from '../api'
import type {
  JobOpportunity,
  JobSuggestion,
  PreviewData,
  ReviewDecisionValue,
  SuggestionType,
} from '../types'
import { getSuggestionField } from '../utils/suggestionFormatters'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()

const ingestionId = computed(() => Number(route.params.id))
const hasValidIngestionId = computed(
  () => Number.isInteger(ingestionId.value) && ingestionId.value > 0,
)
const currentStep = shallowRef(0)
const announcement = shallowRef('')
const previewData = ref<PreviewData | null>(null)
const previewIsStale = shallowRef(false)
const isConfirming = shallowRef(false)
const pendingMutationCount = shallowRef(0)
const hasMutationError = shallowRef(false)
const acceptAllArmed = shallowRef(false)
const acceptAllDisarmTimer = shallowRef<ReturnType<typeof setTimeout> | null>(null)
const manualSuggestionType = shallowRef<
  'responsibility' | 'required_skill' | 'preferred_skill' | null
>(null)
const manualSuggestionValue = shallowRef('')
const confirmedOpportunity = shallowRef<JobOpportunity | null>(null)
const confirmedRequirementsLabel = computed(() => {
  const opportunity = confirmedOpportunity.value

  if (!opportunity) return ''

  const requirementCount = opportunity.requirements.length
  const skillCount = opportunity.skills.length

  return `${requirementCount} requirement${requirementCount === 1 ? '' : 's'} and ${skillCount} skill${
    skillCount === 1 ? '' : 's'
  } saved`
})

const ingestionQuery = useQuery({
  queryKey: computed(() => opportunityKeys.ingestion(ingestionId.value)),
  queryFn: () => fetchIngestion(ingestionId.value),
  enabled: hasValidIngestionId,
})

const suggestionsQuery = useQuery({
  queryKey: computed(() => opportunityKeys.suggestions(ingestionId.value)),
  queryFn: () => fetchSuggestions(ingestionId.value),
  enabled: hasValidIngestionId,
})
const suggestions = suggestionsQuery.data
const refetchSuggestions = suggestionsQuery.refetch
const refetchIngestion = ingestionQuery.refetch

const allSugs = computed(() => suggestions.value ?? [])

type StepKey = string

const SUGGESTION_TYPE_TO_STEP: Record<SuggestionType, StepKey> = {
  job_title: 'overview',
  company: 'overview',
  department: 'overview',
  external_reference: 'overview',
  summary: 'overview',
  application_url: 'overview',
  city: 'work_details',
  region: 'work_details',
  country: 'work_details',
  work_mode: 'work_details',
  contract_type: 'work_details',
  seniority_level: 'work_details',
  working_hours: 'work_details',
  travel_required: 'work_details',
  relocation_required: 'work_details',
  responsibility: 'responsibilities',
  required_experience: 'experience_education',
  preferred_experience: 'experience_education',
  education: 'experience_education',
  required_skill: 'required_skills',
  preferred_skill: 'preferred_skills',
  language: 'languages_certifications',
  certification: 'languages_certifications',
  compensation: 'compensation',
  benefit: 'compensation',
  publication_date: 'compensation',
  application_deadline: 'compensation',
  expected_start_date: 'compensation',
  employment_duration: 'compensation',
  additional_requirement: 'compensation',
}

const STEP_DEFINITIONS: Array<{ key: StepKey; label: string; types: SuggestionType[] }> = [
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
    label: 'Compensation & Details',
    types: [
      'compensation',
      'benefit',
      'publication_date',
      'application_deadline',
      'expected_start_date',
      'employment_duration',
      'additional_requirement',
    ],
  },
]

function isSupportedType(type: string): type is SuggestionType {
  return type in SUGGESTION_TYPE_TO_STEP
}

const visibleSuggestions = computed(() => allSugs.value.filter((s) => isSupportedType(s.type)))

const groupedSteps = computed(() => {
  return STEP_DEFINITIONS.filter((g) =>
    visibleSuggestions.value.some((s) => (g.types as readonly string[]).includes(s.type)),
  )
})

const visibleSteps = computed(() => {
  const steps = [
    ...groupedSteps.value,
    { key: 'final' as const, label: 'Final review', types: [] as SuggestionType[] },
  ]
  return steps
})

const timelineSteps = computed(() =>
  visibleSteps.value.map((step) => {
    const stepSuggestions =
      step.key === 'final'
        ? visibleSuggestions.value
        : visibleSuggestions.value.filter((s) => (step.types as readonly string[]).includes(s.type))
    const reviewedCount = stepSuggestions.filter(
      (suggestion) => (suggestion.review_decision as string) !== 'pending',
    ).length
    const unresolvedCount = stepSuggestions.filter(
      (suggestion) =>
        (suggestion.review_decision as string) === 'pending' ||
        (suggestion.resolution === 'ambiguous' &&
          suggestion.review_decision !== 'rejected' &&
          suggestion.review_decision !== 'keep_blank'),
    ).length

    return {
      key: step.key,
      label: step.label,
      itemCount: stepSuggestions.length,
      reviewedCount,
      unresolvedCount,
      kind: step.key === 'final' ? ('summary' as const) : ('review' as const),
    }
  }),
)

const currentStepMeta = computed(() => visibleSteps.value[currentStep.value] ?? null)
const isLastStep = computed(() => currentStep.value === visibleSteps.value.length - 1)

const pendingCount = computed(
  () => visibleSuggestions.value.filter((s) => (s.review_decision as string) === 'pending').length,
)
const reviewedCount = computed(() =>
  Math.max(visibleSuggestions.value.length - pendingCount.value, 0),
)
const totalCount = computed(() => visibleSuggestions.value.length)
const allReviewed = computed(() => totalCount.value > 0 && pendingCount.value === 0)
const hasAmbiguous = computed(() =>
  visibleSuggestions.value.some(
    (s) =>
      s.resolution === 'ambiguous' &&
      s.review_decision !== 'rejected' &&
      s.review_decision !== 'keep_blank',
  ),
)
const overallProgressLabel = computed(() => {
  if (totalCount.value === 0) return 'No extracted items to review'
  return `${reviewedCount.value} of ${totalCount.value} reviewed`
})

const ambiguousCount = computed(
  () =>
    visibleSuggestions.value.filter(
      (s) =>
        s.resolution === 'ambiguous' &&
        s.review_decision !== 'rejected' &&
        s.review_decision !== 'keep_blank',
    ).length,
)

function getSuggestionsByGroup(types: readonly string[]) {
  return visibleSuggestions.value.filter((s) => types.includes(s.type))
}

function selectReviewStep(index: number): void {
  currentStep.value = index
}

async function handleDecision(
  suggestionId: number,
  decision: ReviewDecisionValue,
  edited_value?: Record<string, unknown>,
  resolved_skill_id?: number | null,
): Promise<void> {
  const suggestion = allSugs.value.find((candidate) => candidate.id === suggestionId)

  if (!suggestion) {
    announcement.value = 'This extracted item is no longer available. Refresh and try again.'
    return
  }

  const hadPreview = previewData.value !== null
  pendingMutationCount.value++
  hasMutationError.value = false

  try {
    await updateSuggestion(ingestionId.value, suggestionId, {
      decision,
      version: suggestion.version,
      edited_value,
      resolved_skill_id,
    })
    previewData.value = null
    previewIsStale.value = hadPreview
    announcement.value = hadPreview
      ? 'Decision saved. Preview is outdated — regenerate to confirm.'
      : 'Decision saved.'
    await Promise.all([refetchSuggestions(), refetchIngestion()])
  } catch (err: unknown) {
    hasMutationError.value = true
    const detail = (err as { response?: { data?: { code?: string; detail?: string } } })?.response
      ?.data
    announcement.value = detail?.detail ?? 'Failed to save decision.'

    if (detail?.code === 'stale_mutation') {
      await Promise.all([refetchSuggestions(), refetchIngestion()])
    }
  } finally {
    pendingMutationCount.value--
  }
}

async function generatePreviewAction() {
  try {
    const result = await generatePreview(ingestionId.value)
    previewData.value = result
    previewIsStale.value = false
    hasMutationError.value = false
    announcement.value = 'Preview is ready to review.'
  } catch (err: unknown) {
    const detail = (err as { response?: { data?: { detail?: string } } })?.response?.data?.detail
    announcement.value = detail ?? 'Failed to generate preview.'
  }
}

function startManualAdd(type: 'responsibility' | 'required_skill' | 'preferred_skill'): void {
  manualSuggestionType.value = type
  manualSuggestionValue.value = ''
}

function cancelManualAdd(): void {
  manualSuggestionType.value = null
  manualSuggestionValue.value = ''
}

async function saveManualSuggestion(): Promise<void> {
  const type = manualSuggestionType.value
  const value = manualSuggestionValue.value.trim()
  const ingestionVersion = ingestionQuery.data.value?.version

  if (!type || !value || ingestionVersion === undefined) return

  const hadPreview = previewData.value !== null
  pendingMutationCount.value++
  hasMutationError.value = false

  try {
    await addManualSuggestion(ingestionId.value, {
      type,
      value,
      ingestion_version: ingestionVersion,
    })
    previewData.value = null
    previewIsStale.value = hadPreview
    cancelManualAdd()
    announcement.value = hadPreview
      ? 'Item added. Preview is outdated — regenerate to confirm.'
      : 'Item added to the review.'
    await Promise.all([refetchSuggestions(), refetchIngestion()])
  } catch (err: unknown) {
    hasMutationError.value = true
    const problem = (err as { response?: { data?: { code?: string; detail?: string } } })?.response
      ?.data
    announcement.value = problem?.detail ?? 'Failed to add the item.'

    if (problem?.code === 'stale_mutation') {
      await Promise.all([refetchSuggestions(), refetchIngestion()])
    }
  } finally {
    pendingMutationCount.value--
  }
}

async function retryReviewQueries(): Promise<void> {
  await Promise.all([refetchSuggestions(), refetchIngestion()])
}

function disarmAcceptAll(): void {
  acceptAllArmed.value = false

  if (acceptAllDisarmTimer.value !== null) {
    clearTimeout(acceptAllDisarmTimer.value)
    acceptAllDisarmTimer.value = null
  }
}

function handleAcceptAllClick(): void {
  if (pendingCount.value === 0 || pendingMutationCount.value > 0) return

  if (!acceptAllArmed.value) {
    acceptAllArmed.value = true
    acceptAllDisarmTimer.value = setTimeout(disarmAcceptAll, 4000)
    return
  }

  disarmAcceptAll()
  void executeAcceptAll()
}

async function executeAcceptAll(): Promise<void> {
  const pending = visibleSuggestions.value.filter(
    (suggestion) => suggestion.review_decision === 'pending',
  )

  if (pending.length === 0) return

  const decisions = pending.map((suggestion) => {
    const isAmbiguousSkill =
      (suggestion.type === 'required_skill' || suggestion.type === 'preferred_skill') &&
      suggestion.resolution === 'ambiguous'

    if (isAmbiguousSkill) {
      return {
        id: suggestion.id,
        decision: 'resolved' as const,
        version: suggestion.version,
        resolved_skill_id: null,
      }
    }

    return { id: suggestion.id, decision: 'accepted' as const, version: suggestion.version }
  })

  const hadPreview = previewData.value !== null
  pendingMutationCount.value++
  hasMutationError.value = false

  try {
    await batchUpdateSuggestions(ingestionId.value, decisions)
    previewData.value = null
    previewIsStale.value = hadPreview
    announcement.value = hadPreview
      ? `${decisions.length} items accepted. Preview is outdated — regenerate to confirm.`
      : `${decisions.length} items accepted.`
    await Promise.all([refetchSuggestions(), refetchIngestion()])
  } catch (err: unknown) {
    hasMutationError.value = true
    const detail = (err as { response?: { data?: { code?: string; detail?: string } } })?.response
      ?.data
    announcement.value = detail?.detail ?? 'Failed to accept the remaining items.'

    if (detail?.code === 'stale_mutation') {
      await Promise.all([refetchSuggestions(), refetchIngestion()])
    }
  } finally {
    pendingMutationCount.value--
  }
}

async function confirmAction() {
  if (!previewData.value) return

  isConfirming.value = true
  try {
    const result = await confirmIngestion(ingestionId.value, previewData.value.version_token)
    confirmedOpportunity.value = result
    announcement.value = 'Job opportunity confirmed!'
    queryClient.invalidateQueries({ queryKey: opportunityKeys.all })
  } catch (err: unknown) {
    const problem = (err as { response?: { data?: { code?: string; detail?: string } } })?.response
      ?.data
    announcement.value = problem?.detail ?? 'Confirmation failed.'

    if (problem?.code === 'stale_preview') {
      previewData.value = null
    }
  } finally {
    isConfirming.value = false
  }
}

function displayField(s: {
  type: string
  extracted_value: Record<string, unknown>
  edited_value: Record<string, unknown> | null
  review_decision: string
}): string {
  return getSuggestionField({
    ...s,
    review_decision:
      s.review_decision === 'rejected' || s.review_decision === 'keep_blank'
        ? 'accepted'
        : s.review_decision,
  })
}

function suggestionLabel(type: string): string {
  return type.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())
}

function handleScalarEdit(suggestion: JobSuggestion, value: string): void {
  const current =
    suggestion.review_decision === 'edited' && suggestion.edited_value
      ? suggestion.edited_value
      : suggestion.extracted_value
  let editedValue: Record<string, unknown>

  switch (suggestion.type) {
    case 'travel_required':
    case 'relocation_required': {
      const normalized = value.trim().toLowerCase()

      if (!['yes', 'no', 'true', 'false'].includes(normalized)) {
        announcement.value = 'Use Yes or No for this field.'
        return
      }

      editedValue = { value: normalized === 'yes' || normalized === 'true' }
      break
    }
    case 'required_experience':
    case 'preferred_experience':
      editedValue = { ...current, summary: value }
      break
    case 'education':
      editedValue = { ...current, degree: value }
      break
    case 'language':
      editedValue = { ...current, language: value }
      break
    case 'certification':
      editedValue = { ...current, name: value }
      break
    case 'compensation':
      editedValue = { ...current, text: value }
      break
    case 'benefit':
      editedValue = { name: value }
      break
    case 'additional_requirement':
      editedValue = { text: value }
      break
    default:
      editedValue = { value }
  }

  handleEdit(suggestion.id, editedValue)
}

function handleInclude(suggestionId: number): void {
  handleDecision(suggestionId, 'accepted')
}

function handleEdit(suggestionId: number, editedValue: Record<string, unknown>): void {
  void handleDecision(suggestionId, 'edited', editedValue)
}

function handleExclude(suggestionId: number): void {
  handleDecision(suggestionId, 'rejected')
}

function handleRestore(suggestionId: number): void {
  void handleDecision(suggestionId, 'accepted')
}

function handleResolveSkill(suggestionId: number, resolvedSkillId: number | null): void {
  void handleDecision(suggestionId, 'resolved', undefined, resolvedSkillId)
}

function handleViewOriginal(suggestionId: number): void {
  void suggestionId
  // Placeholder — comparison view can be added later
}

function handlePrimaryAction(): void {
  if (isLastStep.value) {
    if (previewData.value) {
      confirmAction()
    } else {
      generatePreviewAction()
    }
  } else {
    currentStep.value++
  }
}

onMounted(() => {
  if (!hasValidIngestionId.value) {
    void router.replace('/opportunities')
    return
  }

  void refetchSuggestions()
})

const primaryButtonLabel = computed(() => {
  if (isLastStep.value) {
    if (!previewData.value) return 'Generate preview'
    return isConfirming.value ? 'Confirming…' : 'Confirm opportunity'
  }
  return 'Save and continue'
})

const isPrimaryDisabled = computed(() => {
  if (isLastStep.value) {
    if (!previewData.value) {
      return (
        !allReviewed.value ||
        hasAmbiguous.value ||
        pendingMutationCount.value > 0 ||
        hasMutationError.value
      )
    }
    return (
      isConfirming.value ||
      !allReviewed.value ||
      hasAmbiguous.value ||
      pendingMutationCount.value > 0 ||
      hasMutationError.value
    )
  }
  return false
})
</script>

<template>
  <div class="review-page">
    <OpportunityPageHeader
      title="Review job information"
      description="Review each extracted detail before saving this job opportunity."
    >
      <template #meta>
        <span class="review-page-meta">
          <span>Step {{ currentStep + 1 }} of {{ visibleSteps.length }}</span>
          <span aria-hidden="true">&middot;</span>
          <strong v-if="totalCount > 0">
            {{ overallProgressLabel }}
          </strong>
          <strong v-else>No extracted items to review</strong>
        </span>
      </template>
    </OpportunityPageHeader>

    <OpportunityIdentitySummary
      :suggestions="visibleSuggestions"
      :reviewed-count="reviewedCount"
      :total-count="totalCount"
    />

    <div
      v-if="ingestionQuery.isPending.value || suggestionsQuery.isPending.value"
      class="review-state"
      role="status"
      aria-live="polite"
    >
      Loading extracted job information…
    </div>

    <div
      v-else-if="ingestionQuery.isError.value || suggestionsQuery.isError.value"
      class="review-state review-state-error"
      role="alert"
    >
      <p>We couldn’t load this review. Check your connection and try again.</p>
      <button type="button" class="confirmed-btn-secondary" @click="retryReviewQueries">
        Retry
      </button>
    </div>

    <div v-else-if="confirmedOpportunity" class="confirmed-banner" aria-live="polite">
      <h2 class="confirmed-title">Job opportunity confirmed!</h2>
      <p class="confirmed-summary">
        <strong>{{ confirmedOpportunity.title }}</strong>
        <span v-if="confirmedOpportunity.company_name">
          at {{ confirmedOpportunity.company_name }}
        </span>
      </p>
      <p class="confirmed-requirements">{{ confirmedRequirementsLabel }}</p>
      <div class="confirmed-actions">
        <RouterLink class="confirmed-btn-primary" :to="`/opportunities/${confirmedOpportunity.id}`">
          View opportunity
        </RouterLink>
        <RouterLink class="confirmed-btn-secondary" to="/opportunities"> Back to list </RouterLink>
      </div>
    </div>

    <div v-else class="review-layout">
      <div class="review-navigator">
        <ReviewTimeline
          :steps="timelineSteps"
          :current-index="currentStep"
          @select="selectReviewStep"
        />
      </div>

      <div class="review-workspace">
        <div v-if="announcement" role="status" aria-live="polite" class="announcement-banner">
          {{ announcement }}
        </div>

        <div v-if="totalCount > 0" class="accept-all-toolbar">
          <span class="accept-all-status" aria-live="polite">
            {{
              pendingCount === 0
                ? 'All decisions made'
                : `${pendingCount} decision${pendingCount === 1 ? '' : 's'} remaining`
            }}
          </span>
          <button
            type="button"
            class="accept-all-btn"
            :class="{ 'accept-all-btn-armed': acceptAllArmed }"
            :disabled="pendingCount === 0 || pendingMutationCount > 0 || hasMutationError"
            @click="handleAcceptAllClick"
          >
            {{
              acceptAllArmed
                ? `Accept all — click again to confirm (${pendingCount})`
                : `Accept all remaining (${pendingCount})`
            }}
          </button>
        </div>

        <section
          v-if="currentStepMeta?.key === 'overview'"
          class="step-section"
          aria-labelledby="step-overview-heading"
        >
          <h2 id="step-overview-heading" class="step-heading">Overview</h2>
          <div class="step-items">
            <ScalarReviewItem
              v-for="s in getSuggestionsByGroup([
                'job_title',
                'company',
                'department',
                'external_reference',
                'summary',
                'application_url',
              ])"
              :key="s.id"
              :suggestion="s"
              :label="suggestionLabel(s.type)"
              :value="displayField(s)"
              :saving="pendingMutationCount > 0"
              @keep="handleInclude"
              @edit="(_id, value) => handleScalarEdit(s, value)"
              @exclude="handleExclude"
              @restore="handleRestore"
            />
          </div>
        </section>

        <section
          v-if="currentStepMeta?.key === 'work_details'"
          class="step-section"
          aria-labelledby="step-workdetails-heading"
        >
          <h2 id="step-workdetails-heading" class="step-heading">Work details</h2>
          <div class="step-items">
            <ScalarReviewItem
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
              :suggestion="s"
              :label="suggestionLabel(s.type)"
              :value="displayField(s)"
              :saving="pendingMutationCount > 0"
              @keep="handleInclude"
              @edit="(_id, value) => handleScalarEdit(s, value)"
              @exclude="handleExclude"
              @restore="handleRestore"
            />
          </div>
        </section>

        <section
          v-if="currentStepMeta?.key === 'responsibilities'"
          class="step-section"
          aria-labelledby="step-responsibilities-heading"
        >
          <h2 id="step-responsibilities-heading" class="step-heading">Responsibilities</h2>
          <p class="step-description">
            Keep only responsibilities clearly supported by the job description.
          </p>
          <div class="responsibility-list" role="list">
            <ResponsibilityReviewItem
              v-for="(s, idx) in getSuggestionsByGroup(['responsibility'])"
              :key="s.id"
              :suggestion="s"
              :index="idx"
              :saving="pendingMutationCount > 0"
              role="listitem"
              @include="handleInclude"
              @edit="handleEdit"
              @exclude="handleExclude"
              @restore="handleRestore"
              @view-original="handleViewOriginal"
            />
          </div>
          <form
            v-if="manualSuggestionType === 'responsibility'"
            class="manual-add-form"
            @submit.prevent="saveManualSuggestion"
          >
            <label for="manual-responsibility">Add responsibility</label>
            <textarea
              id="manual-responsibility"
              v-model="manualSuggestionValue"
              name="manual_responsibility"
              rows="4"
              maxlength="2000"
              autocomplete="off"
              required
            />
            <div class="manual-add-actions">
              <button
                type="submit"
                class="preview-btn"
                :disabled="!manualSuggestionValue.trim() || pendingMutationCount > 0"
              >
                Add responsibility
              </button>
              <button type="button" class="confirmed-btn-secondary" @click="cancelManualAdd">
                Cancel
              </button>
            </div>
          </form>
          <button
            v-else
            type="button"
            class="add-item-button"
            @click="startManualAdd('responsibility')"
          >
            Add responsibility
          </button>
        </section>

        <section
          v-if="currentStepMeta?.key === 'experience_education'"
          class="step-section"
          aria-labelledby="step-experience-heading"
        >
          <h2 id="step-experience-heading" class="step-heading">Experience & Education</h2>
          <div class="step-items">
            <ScalarReviewItem
              v-for="s in getSuggestionsByGroup([
                'required_experience',
                'preferred_experience',
                'education',
              ])"
              :key="s.id"
              :suggestion="s"
              :label="suggestionLabel(s.type)"
              :value="displayField(s)"
              :saving="pendingMutationCount > 0"
              @keep="handleInclude"
              @edit="(_id, value) => handleScalarEdit(s, value)"
              @exclude="handleExclude"
              @restore="handleRestore"
            />
          </div>
        </section>

        <section
          v-if="currentStepMeta?.key === 'required_skills'"
          class="step-section"
          aria-labelledby="step-reqskills-heading"
        >
          <h2 id="step-reqskills-heading" class="step-heading">Required skills</h2>
          <div class="step-items">
            <SkillChipEditor
              v-for="s in getSuggestionsByGroup(['required_skill'])"
              :key="s.id"
              :suggestion="s"
              :saving="pendingMutationCount > 0"
              @keep="handleDecision(s.id, 'accepted')"
              @remove="handleDecision(s.id, 'rejected')"
              @restore="handleRestore"
              @resolve="handleResolveSkill"
            />
          </div>
          <form
            v-if="manualSuggestionType === 'required_skill'"
            class="manual-add-form"
            @submit.prevent="saveManualSuggestion"
          >
            <label for="manual-required-skill">Add required skill</label>
            <input
              id="manual-required-skill"
              v-model="manualSuggestionValue"
              name="manual_required_skill"
              type="text"
              maxlength="255"
              autocomplete="off"
              required
            />
            <div class="manual-add-actions">
              <button
                type="submit"
                class="preview-btn"
                :disabled="!manualSuggestionValue.trim() || pendingMutationCount > 0"
              >
                Add skill
              </button>
              <button type="button" class="confirmed-btn-secondary" @click="cancelManualAdd">
                Cancel
              </button>
            </div>
          </form>
          <button
            v-else
            type="button"
            class="add-item-button"
            @click="startManualAdd('required_skill')"
          >
            Add skill
          </button>
        </section>

        <section
          v-if="currentStepMeta?.key === 'preferred_skills'"
          class="step-section"
          aria-labelledby="step-prefskills-heading"
        >
          <h2 id="step-prefskills-heading" class="step-heading">Preferred skills</h2>
          <div class="step-items">
            <SkillChipEditor
              v-for="s in getSuggestionsByGroup(['preferred_skill'])"
              :key="s.id"
              :suggestion="s"
              :saving="pendingMutationCount > 0"
              @keep="handleDecision(s.id, 'accepted')"
              @remove="handleDecision(s.id, 'rejected')"
              @restore="handleRestore"
              @resolve="handleResolveSkill"
            />
          </div>
          <form
            v-if="manualSuggestionType === 'preferred_skill'"
            class="manual-add-form"
            @submit.prevent="saveManualSuggestion"
          >
            <label for="manual-preferred-skill">Add preferred skill</label>
            <input
              id="manual-preferred-skill"
              v-model="manualSuggestionValue"
              name="manual_preferred_skill"
              type="text"
              maxlength="255"
              autocomplete="off"
              required
            />
            <div class="manual-add-actions">
              <button
                type="submit"
                class="preview-btn"
                :disabled="!manualSuggestionValue.trim() || pendingMutationCount > 0"
              >
                Add skill
              </button>
              <button type="button" class="confirmed-btn-secondary" @click="cancelManualAdd">
                Cancel
              </button>
            </div>
          </form>
          <button
            v-else
            type="button"
            class="add-item-button"
            @click="startManualAdd('preferred_skill')"
          >
            Add skill
          </button>
        </section>

        <section
          v-if="currentStepMeta?.key === 'languages_certifications'"
          class="step-section"
          aria-labelledby="step-lang-heading"
        >
          <h2 id="step-lang-heading" class="step-heading">Languages & Certifications</h2>
          <div class="step-items">
            <ScalarReviewItem
              v-for="s in getSuggestionsByGroup(['language', 'certification'])"
              :key="s.id"
              :suggestion="s"
              :label="suggestionLabel(s.type)"
              :value="displayField(s)"
              :saving="pendingMutationCount > 0"
              @keep="handleInclude"
              @edit="(_id, value) => handleScalarEdit(s, value)"
              @exclude="handleExclude"
              @restore="handleRestore"
            />
          </div>
        </section>

        <section
          v-if="currentStepMeta?.key === 'compensation'"
          class="step-section"
          aria-labelledby="step-comp-heading"
        >
          <h2 id="step-comp-heading" class="step-heading">Compensation & Details</h2>
          <div class="step-items">
            <ScalarReviewItem
              v-for="s in getSuggestionsByGroup([
                'compensation',
                'benefit',
                'publication_date',
                'application_deadline',
                'expected_start_date',
                'employment_duration',
                'additional_requirement',
              ])"
              :key="s.id"
              :suggestion="s"
              :label="suggestionLabel(s.type)"
              :value="displayField(s)"
              :saving="pendingMutationCount > 0"
              @keep="handleInclude"
              @edit="(_id, value) => handleScalarEdit(s, value)"
              @exclude="handleExclude"
              @restore="handleRestore"
            />
          </div>
        </section>

        <section
          v-if="currentStepMeta?.key === 'final'"
          class="step-section"
          aria-labelledby="step-final-heading"
        >
          <h2 id="step-final-heading" class="step-heading">Final Review</h2>

          <div class="final-summary">
            <div class="final-summary-row">
              <span>Decisions remaining</span>
              <span
                class="final-summary-count"
                :class="allReviewed ? 'text-success' : 'text-warning'"
              >
                {{ pendingCount }}
              </span>
            </div>
            <div v-if="ambiguousCount > 0" class="final-warning">
              {{ ambiguousCount }} skill mapping{{ ambiguousCount === 1 ? '' : 's' }} unresolved.
            </div>
            <div v-if="pendingCount > 0" class="final-warning">
              Complete all decisions to confirm.
            </div>
            <div v-if="previewIsStale" class="final-warning">
              Preview is outdated — regenerate to confirm.
            </div>
            <div v-if="pendingMutationCount > 0" class="final-warning" role="status">
              Saving your latest review change…
            </div>
            <div v-if="hasMutationError" class="final-warning" role="alert">
              A review change could not be saved. Retry it before confirming.
            </div>
          </div>

          <button
            v-if="!previewData"
            class="preview-btn"
            type="button"
            :disabled="!allReviewed || hasAmbiguous || pendingMutationCount > 0 || hasMutationError"
            @click="generatePreviewAction"
          >
            {{ previewIsStale ? 'Regenerate preview' : 'Generate preview' }}
          </button>

          <div v-if="previewData" class="final-preview">
            <PreviewSummary :preview="previewData" />
          </div>
        </section>

        <ReviewActionFooter
          :show-previous="currentStep > 0"
          :current-step="currentStep + 1"
          :total-steps="visibleSteps.length"
          :reviewed-count="reviewedCount"
          :total-count="totalCount"
          :primary-label="primaryButtonLabel"
          :primary-disabled="isPrimaryDisabled"
          :pending="isConfirming"
          pending-label="Saving…"
          @previous="currentStep--"
          @primary="handlePrimaryAction"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.review-page {
  width: 100%;
  max-width: 74rem;
  margin: 0 auto;
  color: #111827;
}

.review-page-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
  font-variant-numeric: tabular-nums;
}

.review-page-meta strong {
  color: #111827;
  font-weight: 650;
}

.review-layout {
  display: grid;
  min-width: 0;
  gap: 1.5rem;
  margin-top: 1.5rem;
  align-items: start;
}

.review-navigator {
  min-width: 0;
}

.review-workspace {
  display: grid;
  width: 100%;
  max-width: 60rem;
  min-width: 0;
  gap: 1.5rem;
  margin: 0 auto;
}

.announcement-banner {
  border: 1px solid #fedf89;
  border-radius: 0.75rem;
  background: #fffaeb;
  padding: 0.75rem 1rem;
  color: #b54708;
  font-size: 0.875rem;
}

.accept-all-toolbar {
  position: sticky;
  z-index: 4;
  top: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border: 1px solid #e4e7ec;
  border-radius: 0.75rem;
  background: #ffffff;
  padding: 0.625rem 0.75rem;
  box-shadow: 0 4px 12px rgb(16 24 40 / 0.06);
}

.accept-all-status {
  color: #667085;
  font-size: 0.8125rem;
  font-weight: 640;
  font-variant-numeric: tabular-nums;
}

.accept-all-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  min-height: 2.25rem;
  border: 1px solid #c7d2fe;
  border-radius: 0.5rem;
  padding: 0.375rem 0.75rem;
  background: #eef2ff;
  color: #315ee7;
  font-size: 0.8125rem;
  font-weight: 650;
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.accept-all-btn:hover:not(:disabled) {
  background: #c7d2fe;
}

.accept-all-btn-armed {
  border-color: #efc7c3;
  background: #fff2f0;
  color: #ae3b35;
}

.accept-all-btn-armed:hover:not(:disabled) {
  background: #efc7c3;
}

.accept-all-btn:focus-visible {
  outline: 2px solid #315ee7;
  outline-offset: 2px;
}

.accept-all-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.step-section {
  display: grid;
  gap: 1.25rem;
}

.step-heading {
  color: #111827;
  font-size: 1.375rem;
  font-weight: 670;
  letter-spacing: -0.025em;
  line-height: 1.875rem;
}

.step-description {
  margin-top: -0.75rem;
  color: #667085;
  font-size: 0.875rem;
  line-height: 1.375rem;
}

.step-items {
  display: grid;
  gap: 0.75rem;
}

.step-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  border: 1px solid #e4e7ec;
  border-radius: 0.75rem;
  padding: 0.75rem 1rem;
}

.step-item-content {
  min-width: 0;
}

.step-item-label {
  color: #98a2b3;
  font-size: 0.6875rem;
  font-weight: 560;
  line-height: 1rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.step-item-value {
  margin-top: 0.125rem;
  color: #111827;
  font-size: 0.875rem;
  line-height: 1.375rem;
  overflow-wrap: anywhere;
}

.step-item-actions {
  display: flex;
  flex: 0 0 auto;
  gap: 0.25rem;
  align-items: center;
}

.action-btn-sm {
  display: inline-flex;
  align-items: center;
  min-height: 2.25rem;
  border: 0;
  border-radius: 0.5rem;
  padding: 0.375rem 0.625rem;
  background: transparent;
  color: #667085;
  font-size: 0.75rem;
  font-weight: 620;
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.action-btn-sm:hover {
  background: #f2f4f7;
  color: #111827;
}

.action-btn-sm-active {
  color: #18705a;
}

.action-btn-sm-active:hover {
  background: #edf8f4;
  color: #18705a;
}

.action-btn-sm-remove {
  color: #ae3b35;
}

.action-btn-sm-remove:hover {
  background: #fff2f0;
  color: #ae3b35;
}

.action-btn-sm:focus-visible {
  outline: 2px solid #315ee7;
  outline-offset: 2px;
}

.responsibility-list {
  display: grid;
}

.manual-add-form {
  display: grid;
  gap: 0.625rem;
  border: 1px solid #c7d2fe;
  border-radius: 0.75rem;
  background: #eef2ff;
  padding: 1rem;
}

.manual-add-form label {
  color: #111827;
  font-size: 0.8125rem;
  font-weight: 650;
}

.manual-add-form input,
.manual-add-form textarea {
  width: 100%;
  min-height: 2.75rem;
  resize: vertical;
  border: 1px solid #d0d5dd;
  border-radius: 0.5rem;
  background: #ffffff;
  padding: 0.625rem 0.75rem;
  color: #111827;
  font: inherit;
}

.manual-add-form textarea {
  min-height: 7rem;
}

.manual-add-form input:focus-visible,
.manual-add-form textarea:focus-visible,
.add-item-button:focus-visible {
  outline: 2px solid #315ee7;
  outline-offset: 2px;
}

.manual-add-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.manual-add-actions .preview-btn,
.manual-add-actions .confirmed-btn-secondary {
  min-height: 2.75rem;
}

.add-item-button {
  justify-self: start;
  min-height: 2.75rem;
  border: 1px dashed #c7d2fe;
  border-radius: 0.5rem;
  padding: 0.5rem 0.875rem;
  background: #ffffff;
  color: #315ee7;
  font-size: 0.8125rem;
  font-weight: 650;
  cursor: pointer;
}

.add-item-button:hover {
  background: #eef2ff;
}

.final-summary {
  display: grid;
  gap: 0.75rem;
}

.final-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-radius: 0.75rem;
  background: #f2f4f7;
  padding: 0.75rem 1rem;
  color: #263244;
  font-size: 0.875rem;
}

.final-summary-count {
  font-weight: 650;
  font-variant-numeric: tabular-nums;
}

.text-success {
  color: #18705a;
}

.text-warning {
  color: #b54708;
}

.final-warning {
  border-radius: 0.75rem;
  background: #fffaeb;
  padding: 0.75rem 1rem;
  color: #b54708;
  font-size: 0.875rem;
  line-height: 1.375rem;
}

.preview-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  border: none;
  border-radius: 0.5rem;
  padding: 0.625rem 1.25rem;
  background: #315ee7;
  color: #ffffff;
  font-size: 0.875rem;
  font-weight: 650;
  cursor: pointer;
  touch-action: manipulation;
  -webkit-tap-highlight-color: transparent;
}

.preview-btn:hover:not(:disabled) {
  background: #2449bc;
}

.preview-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.preview-btn:focus-visible {
  outline: 2px solid #315ee7;
  outline-offset: 2px;
}

.final-preview {
  display: grid;
  gap: 1.5rem;
}

.confirmed-banner {
  margin-top: 1.5rem;
  border: 1px solid #acd6c8;
  border-radius: 0.75rem;
  background: #edf8f4;
  padding: 1.5rem;
  text-align: center;
}

.confirmed-title {
  color: #18705a;
  font-size: 1.125rem;
  font-weight: 650;
}

.confirmed-summary {
  margin-top: 0.5rem;
  color: #111827;
}

.confirmed-requirements {
  margin-top: 0.25rem;
  color: #667085;
  font-size: 0.875rem;
}

.review-state {
  margin-top: 1.5rem;
  border: 1px solid #e4e7ec;
  border-radius: 0.75rem;
  background: #ffffff;
  padding: 1.5rem;
  color: #667085;
  text-align: center;
}

.review-state-error {
  border-color: #efc7c3;
  background: #fff2f0;
  color: #ae3b35;
}

.review-state-error .confirmed-btn-secondary {
  margin-top: 0.75rem;
}

.confirmed-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-top: 1rem;
}

.confirmed-btn-primary {
  border-radius: 0.5rem;
  background: #315ee7;
  padding: 0.5rem 1rem;
  color: #ffffff;
  font-size: 0.875rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
}

.confirmed-btn-secondary {
  border-radius: 0.5rem;
  border: 1px solid #e4e7ec;
  background: #ffffff;
  padding: 0.5rem 1rem;
  color: #263244;
  font-size: 0.875rem;
  cursor: pointer;
}

.confirmed-btn-primary:focus-visible,
.confirmed-btn-secondary:focus-visible {
  outline: 2px solid #315ee7;
  outline-offset: 2px;
}

@media (min-width: 64rem) {
  .review-layout {
    gap: 2rem;
  }

  .step-heading {
    font-size: 1.5rem;
    line-height: 2rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  * {
    transition: none !important;
  }
}
</style>
