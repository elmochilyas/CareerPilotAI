<script setup lang="ts">
import { ref } from 'vue'
import SkillSearchCombobox from './SkillSearchCombobox.vue'
import ProficiencySelect from './ProficiencySelect.vue'
import StateChip from './StateChip.vue'
import type { Skill, SkillState, ProficiencyLevel, CandidateSkill } from '../types'
import Button from '@/components/ui/Button.vue'

const props = defineProps<{
  saving: boolean
  existingSkills: CandidateSkill[]
}>()
const emit = defineEmits<{
  save: [
    value: {
      skillId?: number | null
      customName?: string | null
      state: SkillState
      proficiency: ProficiencyLevel
    },
  ]
  cancel: []
}>()

const step = ref<'search' | 'custom' | 'details'>('search')
const selectedSkill = ref<Skill | null>(null)
const customName = ref('')
const selectedState = ref<SkillState>('claimed')
const selectedProficiency = ref<ProficiencyLevel>('intermediate')
const duplicateError = ref('')

function onSkillSelect(skill: Skill) {
  duplicateError.value = ''
  const dup = props.existingSkills.find((cs) => cs.skill?.id === skill.id)
  if (dup) {
    duplicateError.value = `"${skill.name}" is already in your profile.`
    return
  }
  selectedSkill.value = skill
  step.value = 'details'
}

function chooseCustom() {
  selectedSkill.value = null
  step.value = 'custom'
}

function proceedFromCustom() {
  if (!customName.value.trim()) return
  duplicateError.value = ''
  step.value = 'details'
}

function submit() {
  emit('save', {
    skillId: selectedSkill.value?.id ?? null,
    customName: selectedSkill.value ? null : customName.value.trim() || null,
    state: selectedState.value,
    proficiency: selectedProficiency.value,
  })
}

function reset() {
  step.value = 'search'
  selectedSkill.value = null
  customName.value = ''
  selectedState.value = 'claimed'
  selectedProficiency.value = 'intermediate'
  duplicateError.value = ''
}

defineExpose({ reset })
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white p-5">
    <template v-if="step === 'search'">
      <h3 class="mb-1 text-sm font-semibold text-slate-900">Add a Skill</h3>
      <p class="mb-4 text-xs text-slate-500">Search the catalog or enter a custom skill.</p>
      <SkillSearchCombobox :model-value="selectedSkill" @select="onSkillSelect" />

      <p v-if="duplicateError" class="mt-2 text-xs text-amber-600" role="alert">
        {{ duplicateError }}
      </p>

      <div class="mt-3 text-center">
        <button
          type="button"
          class="text-xs font-medium text-primary-600 hover:underline"
          @click="chooseCustom"
        >
          Or add a custom skill
        </button>
      </div>

      <div class="mt-4 flex justify-end">
        <Button variant="ghost" @click="emit('cancel')">Cancel</Button>
      </div>
    </template>

    <template v-if="step === 'custom'">
      <h3 class="mb-1 text-sm font-semibold text-slate-900">Custom Skill</h3>
      <p class="mb-4 text-xs text-slate-500">Enter the name of your custom skill.</p>
      <input
        v-model="customName"
        type="text"
        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm transition-all focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
        placeholder="e.g. Specific Technology X"
        maxlength="150"
      />
      <div class="mt-4 flex justify-between">
        <Button variant="ghost" @click="step = 'search'">Back</Button>
        <Button :disabled="!customName.trim()" @click="proceedFromCustom">Next</Button>
      </div>
    </template>

    <template v-if="step === 'details'">
      <h3 class="mb-1 text-sm font-semibold text-slate-900">Skill Details</h3>

      <div v-if="selectedSkill" class="mb-4 rounded-lg bg-slate-50 px-3 py-2">
        <p class="text-sm font-medium text-slate-900">{{ selectedSkill.name }}</p>
        <p v-if="selectedSkill.category" class="text-xs text-slate-500">
          {{ selectedSkill.category }}
        </p>
      </div>
      <div v-else class="mb-4 rounded-lg bg-slate-50 px-3 py-2">
        <p class="text-sm font-medium text-slate-900">{{ customName }}</p>
        <p class="text-xs text-slate-500">Custom skill</p>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700">State</label>
          <div class="mt-2 flex flex-wrap gap-2">
            <button
              v-for="s in ['claimed', 'learning'] as SkillState[]"
              :key="s"
              type="button"
              :class="[
                'rounded-lg border px-3 py-1.5 text-sm transition-all',
                selectedState === s
                  ? 'border-primary-500 bg-primary-50'
                  : 'border-slate-300 hover:bg-slate-50',
              ]"
              @click="selectedState = s"
            >
              <StateChip :state="s" />
            </button>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700">Proficiency</label>
          <div class="mt-1">
            <ProficiencySelect v-model="selectedProficiency" />
          </div>
        </div>

        <p v-if="duplicateError" class="text-xs text-amber-600" role="alert">
          {{ duplicateError }}
        </p>
      </div>

      <div class="mt-6 flex justify-between">
        <Button
          variant="ghost"
          :disabled="saving"
          @click="step = selectedSkill ? 'search' : 'custom'"
        >
          Back
        </Button>
        <Button :disabled="saving" @click="submit">
          {{ saving ? 'Adding...' : 'Add to Profile' }}
        </Button>
      </div>
    </template>
  </div>
</template>
