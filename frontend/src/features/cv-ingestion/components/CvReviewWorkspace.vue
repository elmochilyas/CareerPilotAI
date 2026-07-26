<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'
import { Eye, CheckCircle2, ArrowRight, FileText } from '@lucide/vue'
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

const STEP_DESCRIPTIONS: Record<string, string> = {
  personal: 'Review your name, contact details, and location.',
  profile: 'Review your professional headline and summary.',
  experience: 'Review work experiences extracted from your CV.',
  education: 'Review your educational background.',
  projects: 'Review your projects.',
  certifications: 'Review your certifications and licenses.',
  skills: 'Review technical skills and languages found in your CV.',
  links: 'Review social links and online profiles.',
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
  <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <!-- Readonly banner -->
    <div
      v-if="readonly"
      class="flex items-start gap-3 bg-gradient-to-r from-blue-50 to-blue-50/50 border-b border-blue-100 px-8 py-4"
    >
      <Eye class="mt-0.5 h-5 w-5 shrink-0 text-blue-600" aria-hidden="true" />
      <div>
        <p class="text-sm font-semibold text-blue-800">Viewing imported CV</p>
        <p class="text-xs text-blue-600">
          This CV has already been imported &mdash; no changes can be made.
        </p>
      </div>
    </div>

    <!-- Header -->
    <div
      class="flex items-start justify-between border-b border-slate-100 bg-gradient-to-b from-white to-slate-50/50 px-8 py-5"
    >
      <div class="flex items-start gap-4">
        <div
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 shadow-sm"
        >
          <FileText class="h-5 w-5 text-primary-600" aria-hidden="true" />
        </div>
        <div>
          <h2 class="text-lg font-bold text-slate-900">
            {{ readonly ? 'Extracted information' : 'Review extracted information' }}
          </h2>
          <p class="mt-0.5 text-sm text-slate-500">
            {{
              currentStep?.key && currentStep.key !== 'final'
                ? STEP_DESCRIPTIONS[currentStep.key]
                : ''
            }}
            <template v-if="!currentStep?.key || currentStep.key === 'final'">
              {{
                readonly
                  ? 'View the data that was extracted from this CV.'
                  : 'Review each area one step at a time.'
              }}
            </template>
          </p>
        </div>
      </div>
      <Button
        v-if="!readonly"
        variant="outline"
        size="sm"
        class="shrink-0"
        @click="emit('uploadNew')"
      >
        <ArrowRight class="mr-1 h-3.5 w-3.5 rotate-180" aria-hidden="true" />
        Upload different CV
      </Button>
    </div>

    <!-- Stepper -->
    <div class="border-b border-slate-100 bg-slate-50/30 px-8 py-5">
      <CvReviewStepper
        :steps="steps"
        :active-index="activeStepIndex"
        :step-statuses="stepStatuses"
        :reviewed="reviewedCount"
        :total="totalCount"
        :readonly="readonly"
        v:mobile-dropdown-open="mobileDropdownOpen"
        @navigate="goToStep"
      />
    </div>

    <!-- Step content area -->
    <div class="bg-slate-50/50 px-8 py-6">
      <!-- No suggestions -->
      <div v-if="!readonly && !currentStep" class="flex flex-col items-center py-14 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-50 shadow-sm">
          <CheckCircle2 class="h-8 w-8 text-emerald-500" aria-hidden="true" />
        </div>
        <h3 class="mt-4 text-lg font-semibold text-slate-900">All clear!</h3>
        <p class="mt-1 text-sm text-slate-500 max-w-sm">
          No suggestions to review. Your CV didn't extract any new information.
        </p>
        <div class="mt-6">
          <Button size="sm" @click="emit('proceedToImport')">
            Continue to import
            <ArrowRight class="ml-1 h-4 w-4" aria-hidden="true" />
          </Button>
        </div>
      </div>

      <!-- Step content with transitions -->
      <Transition name="review-step" mode="out-in">
        <div :key="activeStepIndex" class="min-h-[280px]">
          <!-- Personal info -->
          <CvReviewPersonalInfo
            v-if="currentStep?.key === 'personal'"
            :suggestions="normalSuggestions"
            :readonly="readonly"
            @decision="(id, d) => emit('decision', id, d)"
          />

          <!-- Profile -->
          <CvReviewProfileStep
            v-else-if="currentStep?.key === 'profile'"
            :suggestions="normalSuggestions"
            :readonly="readonly"
            @decision="(id, d) => emit('decision', id, d)"
          />

          <!-- Experience -->
          <CvReviewExperience
            v-else-if="currentStep?.key === 'experience'"
            :suggestions="normalSuggestions"
            :readonly="readonly"
            @decision="(id, d) => emit('decision', id, d)"
          />

          <!-- Education -->
          <CvReviewEducation
            v-else-if="currentStep?.key === 'education'"
            :suggestions="normalSuggestions"
            :readonly="readonly"
            @decision="(id, d) => emit('decision', id, d)"
          />

          <!-- Projects -->
          <CvReviewProjects
            v-else-if="currentStep?.key === 'projects'"
            :suggestions="normalSuggestions"
            :readonly="readonly"
            @decision="(id, d) => emit('decision', id, d)"
          />

          <!-- Certifications -->
          <CvReviewCertifications
            v-else-if="currentStep?.key === 'certifications'"
            :suggestions="normalSuggestions"
            :readonly="readonly"
            @decision="(id, d) => emit('decision', id, d)"
          />

          <!-- Skills & Languages -->
          <CvReviewSkills
            v-else-if="currentStep?.key === 'skills'"
            :suggestions="normalSuggestions"
            :readonly="readonly"
            @decision="(id, d) => emit('decision', id, d)"
            @save-batch="handleSkillSave"
            @unsaved-change="handleSkillsUnsaved"
          />

          <!-- Links -->
          <CvReviewLinks
            v-else-if="currentStep?.key === 'links'"
            :suggestions="normalSuggestions"
            :readonly="readonly"
            @decision="(id, d) => emit('decision', id, d)"
          />

          <!-- Final review -->
          <CvReviewFinal
            v-else-if="currentStep?.key === 'final'"
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
          <div v-if="unsupportedSuggestions.length > 0" class="mt-6 space-y-3">
            <div class="flex items-center gap-2 border-b border-slate-100 pb-2">
              <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                {{ unsupportedSuggestions.length }} item{{
                  unsupportedSuggestions.length > 1 ? 's' : ''
                }}
                could not be reviewed
              </p>
            </div>
            <CvUnsupportedSuggestion
              v-for="s in unsupportedSuggestions"
              :key="s.id"
              :suggestion="s"
              @decision="(id, d) => emit('decision', id, d)"
            />
          </div>
        </div>
      </Transition>
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

<style scoped>
.review-step-enter-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.review-step-leave-active {
  transition: all 0.18s cubic-bezier(0.55, 0, 1, 0.45);
}
.review-step-enter-from {
  opacity: 0;
  transform: translateY(12px);
}
.review-step-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
