<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { Eye, CheckCircle2 } from '@lucide/vue'
import type { CvSuggestion, ImportPreview, ReviewDecision, SuggestionType } from '../types'
import CvReviewStepper from './CvReviewStepper.vue'
import type { StepDef, StepStatus } from './CvReviewStepper.vue'
import CvReviewFooter from './CvReviewFooter.vue'
import type { FinalStepSummary } from './CvReviewFinal.vue'
import CvReviewPersonalInfo from './CvReviewPersonalInfo.vue'
import CvReviewProfileStep from './CvReviewProfileStep.vue'
import CvReviewExperience from './CvReviewExperience.vue'
import CvReviewProjects from './CvReviewProjects.vue'
import CvReviewEducation from './CvReviewEducation.vue'
import CvReviewCertifications from './CvReviewCertifications.vue'
import CvReviewSkills from './CvReviewSkills.vue'
import CvReviewLinks from './CvReviewLinks.vue'
import CvReviewFinal from './CvReviewFinal.vue'
import CvUnsupportedSuggestion from './CvUnsupportedSuggestion.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  suggestions: CvSuggestion[]
  preview: ImportPreview | null
  allReviewed: boolean
  readonly?: boolean
  applyPending: boolean
  updateSuggestionPending: boolean
  batchSavePending: boolean
  batchSaveError: boolean
  documentReviewable: boolean
  initialStep?: number
}>()

const emit = defineEmits<{
  decision: [suggestionId: number, decision: ReviewDecision]
  batchSave: [decisions: Array<{ id: number; decision: ReviewDecision['decision'] }>]
  proceedToImport: []
  uploadNew: []
  stepChange: [index: number]
}>()

const activeStepIndex = ref(0)
const visitedSteps = ref<Set<number>>(new Set([0]))
const mobileDropdownOpen = ref(false)
const hasUnsavedSkillChanges = ref(false)

// ---- Step definitions ----
const STEP_MAP: Record<SuggestionType, string> = {
  basic_information: 'personal',
  headline: 'profile',
  summary: 'profile',
  experience: 'experience',
  education: 'education',
  project: 'projects',
  certification: 'certifications',
  skill: 'skills',
  language: 'skills',
  social_link: 'links',
}

const STEP_LABELS: Record<string, string> = {
  personal: 'Personal',
  profile: 'Profile',
  experience: 'Experience',
  education: 'Education',
  projects: 'Projects',
  certifications: 'Certifications',
  skills: 'Skills & Languages',
  links: 'Links',
}

const STEPS_ORDER = [
  'personal',
  'profile',
  'experience',
  'education',
  'projects',
  'certifications',
  'skills',
  'links',
  'final',
] as const

const steps = computed<StepDef[]>(() => {
  const presentKeys = new Set<string>()
  for (const s of props.suggestions) {
    const key = STEP_MAP[s.type]
    if (key) presentKeys.add(key)
  }
  const result: StepDef[] = []
  for (const key of STEPS_ORDER) {
    if (key === 'final') {
      if (props.suggestions.length > 0) result.push({ key: 'final', label: 'Final Review' })
    } else if (presentKeys.has(key)) {
      result.push({ key, label: STEP_LABELS[key] ?? key })
    }
  }
  return result
})

// ---- Suggestions per step ----
function suggestionsForStep(step: StepDef): CvSuggestion[] {
  if (step.key === 'final') return []
  const types = Object.entries(STEP_MAP)
    .filter(([, v]) => v === step.key)
    .map(([k]) => k as SuggestionType)
  const typeSet = new Set(types)
  return props.suggestions.filter((s) => typeSet.has(s.type))
}

// ---- Derive active step from persisted decisions on mount ----
function findFirstIncomplete(): number {
  for (let i = 0; i < steps.value.length; i++) {
    const step = steps.value[i]
    if (!step || step.key === 'final') continue
    const sg = suggestionsForStep(step)
    if (sg.some((s) => s.review_status === 'pending')) return i
  }
  return steps.value.length - 1
}

onMounted(() => {
  if (
    props.initialStep !== undefined &&
    props.initialStep >= 0 &&
    props.initialStep < steps.value.length
  ) {
    activeStepIndex.value = props.initialStep
    visitedSteps.value = new Set([props.initialStep])
  } else if (!props.readonly) {
    const idx = findFirstIncomplete()
    activeStepIndex.value = idx
    visitedSteps.value = new Set([idx])
  } else {
    activeStepIndex.value = 0
    visitedSteps.value = new Set([0])
  }
  emit('stepChange', activeStepIndex.value)
})

// Re-derive when suggestions change or readonly toggles
watch(
  () => [props.suggestions, props.readonly] as const,
  ([, readonly]) => {
    if (!readonly) {
      const idx = findFirstIncomplete()
      if (idx !== activeStepIndex.value) {
        activeStepIndex.value = idx
        visitedSteps.value = new Set([idx])
      }
    }
  },
  { deep: false },
)

// ---- Step status ----
const stepStatuses = computed<StepStatus[]>(() => {
  const statuses: StepStatus[] = steps.value.map((step) => {
    if (step.key === 'final') return 'in_progress'
    const sg = suggestionsForStep(step)
    if (sg.length === 0) return 'completed'
    const allReviewed = sg.every((s) => s.review_status !== 'pending')
    const someReviewed = sg.some((s) => s.review_status !== 'pending')
    const hasConflict =
      props.preview?.conflicts?.some((c) =>
        sg.some((s) => s.field_name === c.field || s.type === c.type),
      ) ?? false
    if (hasConflict) return 'conflict'
    if (allReviewed) return 'completed'
    if (someReviewed) return 'in_progress'
    return 'not_reviewed'
  })

  const finalIdx = steps.value.findIndex((s) => s.key === 'final')
  if (finalIdx !== -1) {
    const allDone = statuses.slice(0, finalIdx).every((s) => s === 'completed')
    const hasConflicts = (props.preview?.conflicts?.length ?? 0) > 0
    statuses[finalIdx] = hasConflicts
      ? 'conflict'
      : allDone && props.allReviewed
        ? 'completed'
        : 'in_progress'
  }

  return statuses
})

// ---- Navigation ----
const isFirstStep = computed(() => activeStepIndex.value === 0)
const isLastStep = computed(() => activeStepIndex.value === steps.value.length - 1)
const isFinalStep = computed(() => steps.value[activeStepIndex.value]?.key === 'final')

function canNavigateTo(index: number): boolean {
  if (props.readonly) return true
  if (index === activeStepIndex.value) return false
  if (index === steps.value.length - 1) {
    return (
      props.allReviewed &&
      (props.preview?.conflicts?.length ?? 0) === 0 &&
      !hasUnsavedSkillChanges.value
    )
  }
  return visitedSteps.value.has(index)
}

function goToStep(index: number): void {
  if (!canNavigateTo(index)) return
  if (hasUnsavedSkillChanges.value) {
    if (!window.confirm('You have unsaved skill changes. Leave this step without saving?')) return
  }
  activeStepIndex.value = index
  visitedSteps.value = new Set([...visitedSteps.value, index])
  mobileDropdownOpen.value = false
  emit('stepChange', index)
}

function goNext(): void {
  if (isLastStep.value) return
  goToStep(activeStepIndex.value + 1)
}

function goPrevious(): void {
  if (isFirstStep.value) return
  goToStep(activeStepIndex.value - 1)
}

// ---- Skills batch save ----
function handleSkillSave(
  decisions?: Array<{ id: number; decision: ReviewDecision['decision'] }>,
): void {
  if (!decisions) return
  emit('batchSave', decisions)
}

watch(
  () => props.batchSavePending,
  (pending, wasPending) => {
    if (wasPending && !pending && !props.batchSaveError) {
      hasUnsavedSkillChanges.value = false
      goNext()
    }
  },
)

function handleSkillsUnsaved(val: boolean): void {
  hasUnsavedSkillChanges.value = val
}

// ---- Apply gating ----
const applyDisabledReason = computed(() => {
  if (!props.documentReviewable) return 'Document is not in a reviewable state.'
  if (!props.allReviewed) return 'Some suggestions have not been reviewed yet.'
  if (hasUnsavedSkillChanges.value) return 'Save or undo skill changes before applying.'
  if (props.updateSuggestionPending) return 'A decision is being saved.'
  if (props.batchSavePending) return 'Skill changes are being saved.'
  if (props.batchSaveError) return 'Skill changes failed to save. Please retry.'
  if (props.preview === null) return 'Import preview is not ready yet.'
  if (props.preview.conflicts.length > 0)
    return `${props.preview.conflicts.length} conflict(s) must be resolved.`
  return null
})

// ---- Final review summary ----
const finalSummary = computed<FinalStepSummary>(() => {
  const fieldsToUpdate: { label: string; stepIndex: number }[] = []
  const newItems: { count: number; label: string; stepIndex: number }[] = []
  const skillsToAdd: string[] = []
  const itemsIgnored: { count: number; label: string; stepIndex: number }[] = []

  for (let i = 0; i < steps.value.length; i++) {
    const step = steps.value[i]
    if (!step || step.key === 'final') continue
    const sg = suggestionsForStep(step)
    const accepted = sg.filter(
      (s) => s.review_status === 'accepted' || s.review_status === 'edited',
    )
    const ignored = sg.filter((s) => s.review_status === 'rejected')

    if (step.key === 'personal') {
      for (const s of accepted) {
        const label = s.field_name ? s.field_name.replace(/_/g, ' ') : 'Field'
        fieldsToUpdate.push({ label, stepIndex: i })
      }
    } else if (step.key === 'profile') {
      for (const s of accepted) {
        const label = s.type === 'headline' ? 'Headline' : 'Professional summary'
        fieldsToUpdate.push({ label, stepIndex: i })
      }
    } else if (step.key === 'skills') {
      const acceptedSkills = accepted as CvSuggestion<'skill'>[]
      for (const s of acceptedSkills) {
        const v = s.suggested_value
        if ('name' in v) skillsToAdd.push((v as { name: string }).name)
      }
    } else if (['experience', 'education', 'projects', 'certifications'].includes(step.key)) {
      if (accepted.length > 0) {
        newItems.push({ count: accepted.length, label: step.label.toLowerCase(), stepIndex: i })
      }
    }

    if (ignored.length > 0) {
      itemsIgnored.push({ count: ignored.length, label: step.label.toLowerCase(), stepIndex: i })
    }
  }

  // Skills that are still pending (not reviewed) — show as ignored
  const pendingSkills = props.suggestions.filter(
    (s) => s.type === 'skill' && s.review_status === 'pending',
  )
  if (pendingSkills.length > 0) {
    itemsIgnored.push({
      count: pendingSkills.length,
      label: 'skills (not reviewed)',
      stepIndex: steps.value.findIndex((s) => s.key === 'skills'),
    })
  }

  return {
    fieldsToUpdate,
    newItems,
    skillsToAdd,
    itemsIgnored,
    conflicts: props.preview?.conflicts?.length ?? 0,
  }
})

// ---- Current step rendering ----
const currentStep = computed(() => steps.value[activeStepIndex.value])
const currentSuggestions = computed(() => {
  if (!currentStep.value || currentStep.value.key === 'final') return []
  return suggestionsForStep(currentStep.value)
})

// ---- Unsupported types check ----
const unsupportedSuggestions = computed(() => {
  if (!currentStep.value || currentStep.value.key === 'final') return []
  const supportedTypes = new Set(Object.keys(STEP_MAP) as SuggestionType[])
  return currentSuggestions.value.filter((s) => !supportedTypes.has(s.type))
})

const normalSuggestions = computed(() => {
  if (!currentStep.value || currentStep.value.key === 'final') return []
  const supportedTypes = new Set(Object.keys(STEP_MAP) as SuggestionType[])
  return currentSuggestions.value.filter((s) => supportedTypes.has(s.type))
})

const reviewedCount = computed(
  () => props.suggestions.filter((s) => s.review_status !== 'pending').length,
)
const totalCount = computed(() => props.suggestions.length)
const conflictCount = computed(() => props.preview?.conflicts?.length ?? 0)
</script>

<template>
  <div class="space-y-6">
    <!-- Readonly banner -->
    <div
      v-if="readonly"
      class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4"
    >
      <Eye class="mt-0.5 h-5 w-5 shrink-0 text-blue-600" aria-hidden="true" />
      <div>
        <p class="text-sm font-medium text-blue-800">Viewing imported CV</p>
        <p class="text-xs text-blue-700">
          This CV has already been imported. The information below shows what was extracted &mdash;
          no changes can be made.
        </p>
      </div>
    </div>

    <!-- Header -->
    <div class="flex items-start justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">
          {{ readonly ? 'Extracted information' : 'Review extracted information' }}
        </h2>
        <p class="text-sm text-slate-500">
          {{
            readonly
              ? 'View the data that was extracted from this CV.'
              : 'Review each area one step at a time.'
          }}
        </p>
      </div>
      <Button v-if="!readonly" variant="outline" size="sm" @click="emit('uploadNew')"
        >Upload different CV</Button
      >
    </div>

    <!-- Stepper -->
    <CvReviewStepper
      :steps="steps"
      :active-index="activeStepIndex"
      :step-statuses="stepStatuses"
      :readonly="readonly"
      v:mobile-dropdown-open="mobileDropdownOpen"
      @navigate="goToStep"
    />

    <!-- No suggestions -->
    <div
      v-if="!readonly && !currentStep"
      class="rounded-lg border border-slate-200 bg-white p-8 text-center"
    >
      <CheckCircle2 class="mx-auto h-8 w-8 text-green-500" aria-hidden="true" />
      <p class="mt-2 text-sm font-medium text-slate-700">No suggestions to review</p>
      <p class="mt-1 text-xs text-slate-500">No new information was extracted from this CV.</p>
      <div class="mt-4">
        <Button size="sm" @click="emit('proceedToImport')">Continue to import</Button>
      </div>
    </div>

    <!-- Step content -->
    <div v-if="currentStep" class="min-h-[200px]">
      <!-- Personal info -->
      <CvReviewPersonalInfo
        v-if="currentStep.key === 'personal'"
        :suggestions="normalSuggestions"
        :readonly="readonly"
        @decision="(id, d) => emit('decision', id, d)"
      />

      <!-- Profile -->
      <CvReviewProfileStep
        v-else-if="currentStep.key === 'profile'"
        :suggestions="normalSuggestions"
        :readonly="readonly"
        @decision="(id, d) => emit('decision', id, d)"
      />

      <!-- Experience -->
      <CvReviewExperience
        v-else-if="currentStep.key === 'experience'"
        :suggestions="normalSuggestions"
        :readonly="readonly"
        @decision="(id, d) => emit('decision', id, d)"
      />

      <!-- Education -->
      <CvReviewEducation
        v-else-if="currentStep.key === 'education'"
        :suggestions="normalSuggestions"
        :readonly="readonly"
        @decision="(id, d) => emit('decision', id, d)"
      />

      <!-- Projects -->
      <CvReviewProjects
        v-else-if="currentStep.key === 'projects'"
        :suggestions="normalSuggestions"
        :readonly="readonly"
        @decision="(id, d) => emit('decision', id, d)"
      />

      <!-- Certifications -->
      <CvReviewCertifications
        v-else-if="currentStep.key === 'certifications'"
        :suggestions="normalSuggestions"
        :readonly="readonly"
        @decision="(id, d) => emit('decision', id, d)"
      />

      <!-- Skills & Languages -->
      <CvReviewSkills
        v-else-if="currentStep.key === 'skills'"
        :suggestions="normalSuggestions"
        :readonly="readonly"
        @decision="(id, d) => emit('decision', id, d)"
        @save-batch="handleSkillSave"
        @unsaved-change="handleSkillsUnsaved"
      />

      <!-- Links -->
      <CvReviewLinks
        v-else-if="currentStep.key === 'links'"
        :suggestions="normalSuggestions"
        :readonly="readonly"
        @decision="(id, d) => emit('decision', id, d)"
      />

      <!-- Final review -->
      <CvReviewFinal
        v-else-if="currentStep.key === 'final'"
        :suggestions="suggestions"
        :preview="preview"
        :summary="finalSummary"
        :steps="steps"
        :step-statuses="stepStatuses"
        :apply-disabled-reason="applyDisabledReason"
        :apply-pending="applyPending"
        :readonly="readonly"
        @navigate-to-step="goToStep"
        @apply="emit('proceedToImport')"
      />

      <!-- Unsupported suggestions -->
      <div v-if="unsupportedSuggestions.length > 0" class="mt-4 space-y-3">
        <p class="text-xs font-medium text-slate-400">
          {{ unsupportedSuggestions.length }} item{{ unsupportedSuggestions.length > 1 ? 's' : '' }}
          could not be reviewed
        </p>
        <CvUnsupportedSuggestion
          v-for="s in unsupportedSuggestions"
          :key="s.id"
          :suggestion="s"
          @decision="(id, d) => emit('decision', id, d)"
        />
      </div>
    </div>

    <!-- Footer -->
    <CvReviewFooter
      :reviewed="reviewedCount"
      :total="totalCount"
      :conflicts="conflictCount"
      :is-first-step="isFirstStep"
      :is-last-step="isLastStep"
      :is-final-step="isFinalStep"
      :apply-disabled-reason="applyDisabledReason"
      :apply-pending="applyPending"
      :save-pending="batchSavePending"
      :show-save-and-continue="currentStep?.key === 'skills' && hasUnsavedSkillChanges && !readonly"
      :readonly="readonly"
      @previous="goPrevious"
      @next="goNext"
      @save-and-continue="handleSkillSave"
      @apply="emit('proceedToImport')"
    />
  </div>
</template>
