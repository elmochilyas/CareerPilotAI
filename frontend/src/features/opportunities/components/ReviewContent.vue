<script setup lang="ts">
import { computed, ref } from 'vue'
import type { JobSuggestion, PreviewData, SuggestionType } from '../types'
import { getSuggestionField, suggestionLabel } from '../utils/suggestionFormatters'
import ReviewSection from './ReviewSection.vue'
import SkillChipEditor from './SkillChipEditor.vue'
import ResponsibilityReviewItem from './ResponsibilityReviewItem.vue'
import ScalarReviewItem from './ScalarReviewItem.vue'
import PreviewSummary from './PreviewSummary.vue'

const props = defineProps<{
  suggestions: JobSuggestion[]
  stepKey: string
  stepLabel: string
  pendingMutationCount: number
  previewData: PreviewData | null
}>()

const emit = defineEmits<{
  keep: [id: number]
  edit: [id: number, value: Record<string, unknown>]
  exclude: [id: number]
  restore: [id: number]
  resolveSkill: [id: number, resolvedSkillId: number | null]
}>()

const manualSuggestionType = ref<'responsibility' | 'required_skill' | 'preferred_skill' | null>(
  null,
)
const manualSuggestionValue = ref('')

const STEP_TYPE_MAP: Record<string, SuggestionType[]> = {
  overview: [
    'job_title',
    'company',
    'department',
    'external_reference',
    'summary',
    'application_url',
  ],
  work_details: [
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
  responsibilities: ['responsibility'],
  experience_education: ['required_experience', 'preferred_experience', 'education'],
  required_skills: ['required_skill'],
  preferred_skills: ['preferred_skill'],
  languages_certifications: ['language', 'certification'],
  compensation: [
    'compensation',
    'benefit',
    'publication_date',
    'application_deadline',
    'expected_start_date',
    'employment_duration',
    'additional_requirement',
  ],
}

const groupedSuggestions = computed(() => {
  const types = STEP_TYPE_MAP[props.stepKey] ?? []
  return props.suggestions.filter((s) => types.includes(s.type))
})

const scalarSuggestions = computed(() =>
  groupedSuggestions.value.filter(
    (s) => !['responsibility', 'required_skill', 'preferred_skill'].includes(s.type),
  ),
)

const responsibilitySuggestions = computed(() =>
  groupedSuggestions.value.filter((s) => s.type === 'responsibility'),
)

const skillSuggestions = computed(() =>
  groupedSuggestions.value.filter(
    (s) => s.type === 'required_skill' || s.type === 'preferred_skill',
  ),
)

const isFinalStep = computed(() => props.stepKey === 'final')

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

  emit('edit', suggestion.id, editedValue)
}

function startManualAdd(type: 'responsibility' | 'required_skill' | 'preferred_skill'): void {
  manualSuggestionType.value = type
  manualSuggestionValue.value = ''
}

function cancelManualAdd(): void {
  manualSuggestionType.value = null
  manualSuggestionValue.value = ''
}
</script>

<template>
  <div class="grid gap-4">
    <!-- Final step: show preview and summary -->
    <template v-if="isFinalStep">
      <ReviewSection title="Final Review">
        <div class="rounded-lg bg-slate-50 p-4">
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">Decisions remaining</span>
            <span
              class="font-semibold"
              :class="pendingMutationCount === 0 ? 'text-emerald-600' : 'text-amber-600'"
            >
              {{ suggestions.filter((s) => s.review_decision === 'pending').length }}
            </span>
          </div>
          <div
            v-if="suggestions.some((s) => s.review_decision === 'pending')"
            class="mt-2 rounded-md bg-amber-50 p-2 text-sm text-amber-700"
          >
            Complete all decisions to confirm.
          </div>
        </div>
        <div v-if="previewData" class="mt-2">
          <PreviewSummary :preview="previewData" />
        </div>
      </ReviewSection>
    </template>

    <!-- Scalar fields (overview, work_details, experience, languages, compensation) -->
    <template
      v-else-if="
        stepKey === 'overview' ||
        stepKey === 'work_details' ||
        stepKey === 'experience_education' ||
        stepKey === 'languages_certifications' ||
        stepKey === 'compensation'
      "
    >
      <ReviewSection :title="stepLabel">
        <ScalarReviewItem
          v-for="s in scalarSuggestions"
          :key="s.id"
          :suggestion="s"
          :label="suggestionLabel(s.type)"
          :value="displayField(s)"
          :saving="pendingMutationCount > 0"
          @keep="(id) => emit('keep', id)"
          @edit="(_id, value) => handleScalarEdit(s, value)"
          @exclude="(id) => emit('exclude', id)"
          @restore="(id) => emit('restore', id)"
        />
        <div
          v-if="scalarSuggestions.length === 0"
          class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500"
        >
          No items in this section.
        </div>
      </ReviewSection>
    </template>

    <!-- Responsibilities -->
    <template v-else-if="stepKey === 'responsibilities'">
      <ReviewSection
        title="Responsibilities"
        description="Keep only responsibilities clearly supported by the job description."
      >
        <ResponsibilityReviewItem
          v-for="(s, idx) in responsibilitySuggestions"
          :key="s.id"
          :suggestion="s"
          :index="idx"
          :saving="pendingMutationCount > 0"
          @include="(id) => emit('keep', id)"
          @edit="(id, value) => emit('edit', id, value)"
          @exclude="(id) => emit('exclude', id)"
          @restore="(id) => emit('restore', id)"
        />
        <div
          v-if="responsibilitySuggestions.length === 0"
          class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500"
        >
          No responsibilities extracted.
        </div>
        <template #footer>
          <div
            v-if="manualSuggestionType === 'responsibility'"
            class="rounded-lg border border-primary-200 bg-primary-50 p-3"
          >
            <label for="manual-responsibility" class="block text-sm font-medium text-slate-700">
              Add responsibility
            </label>
            <textarea
              id="manual-responsibility"
              v-model="manualSuggestionValue"
              rows="3"
              maxlength="2000"
              autocomplete="off"
              required
              class="mt-1 block w-full rounded-md border border-slate-300 p-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
            />
            <div class="mt-2 flex gap-2">
              <button
                type="button"
                :disabled="!manualSuggestionValue.trim() || pendingMutationCount > 0"
                class="rounded-md bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="cancelManualAdd"
              >
                Add responsibility
              </button>
              <button
                type="button"
                class="rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50"
                @click="cancelManualAdd"
              >
                Cancel
              </button>
            </div>
          </div>
          <button
            v-else
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md border border-dashed border-primary-300 bg-white px-3 py-2 text-sm font-medium text-primary-600 transition-colors hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
            @click="startManualAdd('responsibility')"
          >
            Add responsibility
          </button>
        </template>
      </ReviewSection>
    </template>

    <!-- Required / Preferred Skills -->
    <template v-else-if="stepKey === 'required_skills' || stepKey === 'preferred_skills'">
      <ReviewSection
        :title="stepKey === 'required_skills' ? 'Required skills' : 'Preferred skills'"
      >
        <SkillChipEditor
          v-for="s in skillSuggestions"
          :key="s.id"
          :suggestion="s"
          :saving="pendingMutationCount > 0"
          @keep="emit('keep', s.id)"
          @remove="emit('exclude', s.id)"
          @restore="emit('restore', s.id)"
          @resolve="(skillId) => emit('resolveSkill', s.id, skillId)"
        />
        <div
          v-if="skillSuggestions.length === 0"
          class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500"
        >
          No skills extracted.
        </div>
        <template #footer>
          <div
            v-if="
              manualSuggestionType ===
              (stepKey === 'required_skills' ? 'required_skill' : 'preferred_skill')
            "
            class="rounded-lg border border-primary-200 bg-primary-50 p-3"
          >
            <label
              :for="`manual-${stepKey}-skill`"
              class="block text-sm font-medium text-slate-700"
            >
              {{ stepKey === 'required_skills' ? 'Add required skill' : 'Add preferred skill' }}
            </label>
            <input
              :id="`manual-${stepKey}-skill`"
              v-model="manualSuggestionValue"
              type="text"
              maxlength="255"
              autocomplete="off"
              required
              class="mt-1 block w-full rounded-md border border-slate-300 p-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
            />
            <div class="mt-2 flex gap-2">
              <button
                type="button"
                :disabled="!manualSuggestionValue.trim() || pendingMutationCount > 0"
                class="rounded-md bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="cancelManualAdd"
              >
                Add skill
              </button>
              <button
                type="button"
                class="rounded-md border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50"
                @click="cancelManualAdd"
              >
                Cancel
              </button>
            </div>
          </div>
          <button
            v-else
            type="button"
            class="inline-flex items-center gap-1.5 rounded-md border border-dashed border-primary-300 bg-white px-3 py-2 text-sm font-medium text-primary-600 transition-colors hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
            @click="
              startManualAdd(stepKey === 'required_skills' ? 'required_skill' : 'preferred_skill')
            "
          >
            Add skill
          </button>
        </template>
      </ReviewSection>
    </template>
  </div>
</template>
