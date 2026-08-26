<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import type { ClarificationAnswerInput, ClarificationQuestion } from '../types'

const props = defineProps<{
  question: ClarificationQuestion
  busy: boolean
  error: string | null
  canGoBack: boolean
}>()

const emit = defineEmits<{
  submit: [payload: ClarificationAnswerInput]
  skip: []
  back: []
}>()

const choice = ref<'yes' | 'no' | null>(null)
const evidenceUrl = ref('')
const noEvidence = ref(false)
const textValue = ref('')
const selectValue = ref('')
const numberValue = ref('')
const validationError = ref('')

const radioGroup = ref<HTMLElement | null>(null)
const urlInput = ref<HTMLInputElement | null>(null)
const textInput = ref<HTMLTextAreaElement | null>(null)
const numberInput = ref<HTMLInputElement | null>(null)

const isYesNo = computed(() => props.question.question_type === 'yes_no')
const isYesNoWithDetails = computed(() => props.question.question_type === 'yes_no_with_details')
const isText = computed(() => props.question.question_type === 'text')
const isSelect = computed(() => props.question.question_type === 'select')
const isNumber = computed(() => props.question.question_type === 'number')
const isYesFamily = computed(() => isYesNo.value || isYesNoWithDetails.value)

const unsafeSchemes = ['javascript:', 'data:', 'file:', 'vbscript:']

function evidenceUrlError(value: string): string | null {
  const trimmed = value.trim()
  if (!trimmed) return 'Evidence URL is required.'
  let parsed: URL
  try {
    parsed = new URL(trimmed)
  } catch {
    return 'Enter a valid URL.'
  }
  if (unsafeSchemes.includes(parsed.protocol)) return 'This URL scheme is not allowed.'
  if (parsed.protocol !== 'https:') return 'Only HTTPS URLs are accepted as evidence.'
  return null
}

function focusFirstInvalid(): void {
  if (urlInput.value !== null) {
    urlInput.value.focus()
    return
  }
  if (radioGroup.value !== null) {
    const radio = radioGroup.value.querySelector<HTMLInputElement>('input[type="radio"]')
    radio?.focus()
    return
  }
  if (textInput.value !== null) {
    textInput.value.focus()
    return
  }
  if (numberInput.value !== null) {
    numberInput.value.focus()
  }
}

function onSubmit(): void {
  validationError.value = ''

  if (isYesNo.value) {
    if (choice.value === null) {
      validationError.value = 'Choose an answer before submitting.'
      focusFirstInvalid()
      return
    }
    emit('submit', {
      answer_type: choice.value === 'yes' ? 'yes' : 'no',
      value: '',
      acknowledged_no_evidence: false,
    })
    return
  }

  if (isYesNoWithDetails.value) {
    if (choice.value === null) {
      validationError.value = 'Choose an answer before submitting.'
      focusFirstInvalid()
      return
    }
    if (choice.value === 'yes') {
      if (!noEvidence.value) {
        const urlError = evidenceUrlError(evidenceUrl.value)
        if (urlError !== null) {
          validationError.value = urlError
          focusFirstInvalid()
          return
        }
      }
      emit('submit', {
        answer_type: 'yes',
        value: evidenceUrl.value.trim(),
        acknowledged_no_evidence: noEvidence.value,
      })
      return
    }
    emit('submit', {
      answer_type: 'no_with_ack',
      value: '',
      acknowledged_no_evidence: true,
    })
    return
  }

  if (isText.value) {
    const trimmed = textValue.value.trim()
    if (!trimmed) {
      validationError.value = 'Enter a value before submitting.'
      focusFirstInvalid()
      return
    }
    if (trimmed.length > 2000) {
      validationError.value = 'The value is too long (maximum 2000 characters).'
      focusFirstInvalid()
      return
    }
    emit('submit', { answer_type: 'text', value: trimmed, acknowledged_no_evidence: false })
    return
  }

  if (isSelect.value) {
    if (!selectValue.value) {
      validationError.value = 'Choose an option before submitting.'
      focusFirstInvalid()
      return
    }
    emit('submit', {
      answer_type: 'select_option',
      value: selectValue.value,
      acknowledged_no_evidence: false,
    })
    return
  }

  const trimmed = numberValue.value.trim()
  if (!trimmed) {
    validationError.value = 'Enter a value before submitting.'
    focusFirstInvalid()
    return
  }
  const numeric = Number(trimmed)
  if (!Number.isFinite(numeric) || numeric < 0 || numeric > 100) {
    validationError.value = 'Enter a number between 0 and 100.'
    focusFirstInvalid()
    return
  }
  emit('submit', { answer_type: 'number', value: trimmed, acknowledged_no_evidence: false })
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
    <div class="grid gap-1">
      <p
        v-if="question.requirement?.label"
        class="text-xs font-medium uppercase tracking-wide text-[var(--text-muted)]"
      >
        {{ question.requirement.label }}
      </p>
      <p v-if="question.requirement" class="text-sm text-[var(--text-secondary)]">
        {{ question.requirement.text }}
      </p>
    </div>

    <h3 class="text-base font-semibold text-slate-900">{{ question.prompt }}</h3>

    <details
      v-if="question.detail"
      class="rounded-[var(--radius-xl)] bg-[var(--surface-secondary)] px-4 py-3 shadow-[var(--shadow-neo-inset)]"
    >
      <summary
        class="cursor-pointer text-sm font-medium text-slate-700 focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)] focus-visible:outline-none"
      >
        Why is this asked?
      </summary>
      <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ question.detail }}</p>
    </details>

    <div v-if="isYesFamily" class="grid gap-2">
      <p id="answer-choice-label" class="text-sm font-medium text-slate-700">Your answer</p>
      <div
        ref="radioGroup"
        role="radiogroup"
        aria-labelledby="answer-choice-label"
        class="grid gap-2 sm:grid-cols-2"
      >
        <label
          :class="
            choice === 'yes'
              ? 'bg-[var(--color-primary-50)] shadow-[var(--shadow-neo-inset)]'
              : 'bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised-sm)] hover:bg-[var(--surface-secondary)]'
          "
          class="flex cursor-pointer items-center gap-3 rounded-[var(--radius-xl)] px-3.5 py-3 transition-colors focus-within:ring-2 focus-within:ring-[var(--color-primary-500)]/40"
        >
          <input
            v-model="choice"
            type="radio"
            name="answer-choice"
            value="yes"
            class="size-4 accent-[var(--color-primary-600)]"
          />
          <span class="text-sm text-slate-700">Yes</span>
        </label>
        <label
          :class="
            choice === 'no'
              ? 'bg-[var(--color-primary-50)] shadow-[var(--shadow-neo-inset)]'
              : 'bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised-sm)] hover:bg-[var(--surface-secondary)]'
          "
          class="flex cursor-pointer items-center gap-3 rounded-[var(--radius-xl)] px-3.5 py-3 transition-colors focus-within:ring-2 focus-within:ring-[var(--color-primary-500)]/40"
        >
          <input
            v-model="choice"
            type="radio"
            name="answer-choice"
            value="no"
            class="size-4 accent-[var(--color-primary-600)]"
          />
          <span class="text-sm text-slate-700">No</span>
        </label>
      </div>
    </div>

    <div
      v-if="isYesNoWithDetails && choice === 'yes'"
      class="grid gap-4 rounded-[var(--radius-xl)] bg-[var(--surface-secondary)] p-4 shadow-[var(--shadow-neo-inset)]"
    >
      <Input
        ref="urlInput"
        v-model="evidenceUrl"
        label="Evidence URL"
        name="evidence-url"
        type="url"
        placeholder="https://example.com/certificate"
        :disabled="noEvidence"
        :required="!noEvidence"
        hint="A link that backs up your claim, for example a certificate or project."
      />
      <Checkbox
        :id="`no-evidence-${question.id}`"
        v-model="noEvidence"
        label="I don't have evidence to share"
      />
    </div>

    <p
      v-if="isYesNoWithDetails && choice === 'no'"
      class="rounded-[var(--radius-xl)] bg-[var(--surface-secondary)] px-4 py-3 text-sm text-slate-600 shadow-[var(--shadow-neo-inset)]"
    >
      You can still move forward — we'll record that this item is missing and let you decide what
      happens next.
    </p>

    <Textarea
      v-if="isText"
      ref="textInput"
      v-model="textValue"
      label="Your answer"
      name="clarification-text"
      :maxlength="2000"
      :rows="4"
      placeholder="Type your answer…"
    />

    <div v-if="isSelect && question.options?.length" class="grid gap-2">
      <p id="select-option-label" class="text-sm font-medium text-slate-700">Choose one</p>
      <div
        ref="radioGroup"
        role="radiogroup"
        aria-labelledby="select-option-label"
        class="grid gap-2"
      >
        <label
          v-for="option in question.options"
          :key="option"
          :class="
            selectValue === option
              ? 'bg-[var(--color-primary-50)] shadow-[var(--shadow-neo-inset)]'
              : 'bg-[var(--surface-primary)] shadow-[var(--shadow-neo-raised-sm)] hover:bg-[var(--surface-secondary)]'
          "
          class="flex cursor-pointer items-center gap-3 rounded-[var(--radius-xl)] px-3.5 py-2.5 transition-colors focus-within:ring-2 focus-within:ring-[var(--color-primary-500)]/40"
        >
          <input
            v-model="selectValue"
            type="radio"
            name="select-option"
            :value="option"
            class="size-4 accent-[var(--color-primary-600)]"
          />
          <span class="text-sm text-slate-700">{{ option }}</span>
        </label>
      </div>
    </div>

    <Input
      v-if="isNumber"
      ref="numberInput"
      v-model="numberValue"
      :label="question.unit ? `Value (${question.unit})` : 'Value'"
      name="clarification-number"
      type="number"
      inputmode="numeric"
      placeholder="0–100"
      hint="Enter a number between 0 and 100."
    />

    <p
      v-if="validationError"
      role="alert"
      class="rounded-[var(--radius-xl)] bg-[var(--color-error-50)] px-3 py-2 text-sm text-[var(--color-error-700)] shadow-[var(--shadow-neo-raised-sm)]"
    >
      {{ validationError }}
    </p>
    <p
      v-if="error"
      role="alert"
      class="rounded-[var(--radius-xl)] bg-[var(--color-error-50)] px-3 py-2 text-sm text-[var(--color-error-700)] shadow-[var(--shadow-neo-raised-sm)]"
    >
      {{ error }}
    </p>

    <div class="flex flex-wrap items-center gap-2 border-t border-[var(--border-subtle)] pt-4">
      <Button v-if="canGoBack" variant="outline" :disabled="busy" @click="back">Back</Button>
      <Button variant="ghost" :disabled="busy" @click="skip">Skip question</Button>
      <Button class="ml-auto" :loading="busy" @click="onSubmit">Submit answer</Button>
    </div>
  </div>
</template>
