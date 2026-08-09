<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import type { ClarificationProposal, ClarificationQuestion } from '../types'

const props = defineProps<{
  question: ClarificationQuestion
  proposal: ClarificationProposal
  busy: boolean
  error: string | null
  canGoBack: boolean
}>()

const emit = defineEmits<{
  accept: []
  edit: [value: string]
  skip: []
  back: []
}>()

const editing = ref(false)
const editedValue = ref('')
const editError = ref('')

function humanize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1).replace(/_/g, ' ')
}

function valueToLabel(value: unknown): string {
  if (value === null || value === undefined) return 'None'
  if (typeof value === 'string') return value.trim() || 'None'
  if (typeof value === 'number' || typeof value === 'boolean') return String(value)
  if (Array.isArray(value)) {
    const parts = value.map((item) => valueToLabel(item))
    return parts.filter(Boolean).join(', ') || 'None'
  }
  if (typeof value === 'object') {
    const record = value as Record<string, unknown>
    if (typeof record.state === 'string') return humanize(record.state)
    if (typeof record.value === 'string') return record.value || 'None'
    if (typeof record.url === 'string') return record.url || 'None'
    if (record.years_experience !== undefined && record.years_experience !== null) {
      const years = Number(record.years_experience)
      const label = Number.isInteger(years) ? String(years) : String(record.years_experience)
      return `${label} ${years === 1 ? 'year' : 'years'}`
    }
    const text = JSON.stringify(value)
    return text === '{}' ? 'None' : text
  }
  return String(value)
}

function initialEditValue(proposal: ClarificationProposal): string {
  const after = proposal.after_value
  if (after === null || after === undefined) return ''
  if (typeof after === 'string') return after
  if (typeof after === 'number') return String(after)
  if (typeof after === 'object' && !Array.isArray(after)) {
    const record = after as Record<string, unknown>
    if (typeof record.url === 'string') return record.url
    if (typeof record.value === 'string') return record.value
  }
  return ''
}

const answer = computed(() => props.question.answer)
const editable = computed(() => props.proposal.field !== 'state')
const targetLabel = computed(() => {
  const type = props.proposal.target.type.replace(/_/g, ' ')
  const id = props.proposal.target.id
  return id !== null ? `${type} #${id}` : type
})
const fieldLabel = computed(() => humanize(props.proposal.field))
const beforeLabel = computed(() => valueToLabel(props.proposal.before_value))
const afterLabel = computed(() => valueToLabel(props.proposal.after_value))
const evidenceLabel = computed(() => {
  if (answer.value?.acknowledged_no_evidence) {
    return 'No evidence shared — acknowledged by the candidate.'
  }
  const value = answer.value?.value.trim()
  return value ? value : 'No evidence provided.'
})

function startEdit(): void {
  if (!editable.value || props.busy) return
  editedValue.value = initialEditValue(props.proposal)
  editError.value = ''
  editing.value = true
}

function cancelEdit(): void {
  editing.value = false
  editError.value = ''
}

function saveEdit(): void {
  const trimmed = editedValue.value.trim()
  if (!trimmed) {
    editError.value = 'Enter a value before saving.'
    return
  }
  if (props.proposal.field === 'years_experience') {
    const numeric = Number(trimmed)
    if (!Number.isFinite(numeric) || numeric < 0 || numeric > 100) {
      editError.value = 'Enter a number between 0 and 100.'
      return
    }
  }
  editError.value = ''
  emit('edit', trimmed)
}

function accept(): void {
  if (props.busy) return
  emit('accept')
}

function skip(): void {
  if (props.busy) return
  emit('skip')
}

function back(): void {
  if (props.busy) return
  emit('back')
}
</script>

<template>
  <div class="grid gap-5">
    <div>
      <h3 class="text-base font-semibold text-slate-900">Review the proposed change</h3>
      <p class="mt-1 text-sm text-slate-600">{{ question.prompt }}</p>
    </div>

    <dl
      class="grid gap-3 rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-4 text-sm shadow-[var(--shadow-neo-raised)]"
    >
      <div class="flex items-start justify-between gap-4">
        <dt class="text-slate-500">Item</dt>
        <dd class="text-right font-medium text-slate-900">{{ targetLabel }}</dd>
      </div>
      <div class="flex items-start justify-between gap-4">
        <dt class="text-slate-500">Field</dt>
        <dd class="text-right font-medium text-slate-900">{{ fieldLabel }}</dd>
      </div>
      <div class="flex items-start justify-between gap-4">
        <dt class="text-slate-500">Current value</dt>
        <dd class="max-w-[60%] text-right text-slate-700">{{ beforeLabel }}</dd>
      </div>
      <div class="flex items-start justify-between gap-4">
        <dt class="text-slate-500">Proposed value</dt>
        <dd class="max-w-[60%] text-right font-medium text-[var(--color-primary-700)]">
          {{ afterLabel }}
        </dd>
      </div>
    </dl>

    <div
      class="rounded-[var(--radius-xl)] bg-[var(--surface-secondary)] px-4 py-3 text-sm text-slate-600 shadow-[var(--shadow-neo-inset)]"
    >
      <span class="font-medium text-slate-700">Evidence basis: </span>{{ evidenceLabel }}
    </div>

    <div v-if="editing" class="grid gap-3">
      <Input v-model="editedValue" label="Edited value" name="edited-value" :error="editError" />
      <div class="flex gap-2">
        <Button :loading="busy" @click="saveEdit">Save edit</Button>
        <Button variant="ghost" :disabled="busy" @click="cancelEdit">Cancel</Button>
      </div>
    </div>

    <p
      v-if="error"
      role="alert"
      class="rounded-[var(--radius-xl)] bg-red-50 px-3 py-2 text-sm text-red-700 shadow-[var(--shadow-neo-raised-sm)]"
    >
      {{ error }}
    </p>

    <div
      v-if="!editing"
      class="flex flex-wrap items-center gap-2 border-t border-[var(--border-subtle)] pt-4"
    >
      <Button v-if="canGoBack" variant="outline" :disabled="busy" @click="back">Back</Button>
      <Button v-if="editable" variant="outline" :disabled="busy" @click="startEdit">
        Edit value
      </Button>
      <Button variant="ghost" :disabled="busy" @click="skip">Skip</Button>
      <Button class="ml-auto" :loading="busy" @click="accept">Accept change</Button>
    </div>
  </div>
</template>
