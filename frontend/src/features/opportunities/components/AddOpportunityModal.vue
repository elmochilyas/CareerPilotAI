<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ExternalLink, Tag, AlertTriangle } from '@lucide/vue'
import { extractProblemDetail } from '@/api/client'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'
import { createIngestion } from '../api'
import type { IngestionStatus } from '../types'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
  created: []
}>()

const router = useRouter()

const description = ref('')
const sourceUrl = ref('')
const personalLabel = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')
const descriptionInput = ref<HTMLTextAreaElement | null>(null)
const duplicateInfo = ref<{
  ingestionId: number
  opportunityId?: number
  status?: IngestionStatus
} | null>(null)

const maxLength = 100000
const minLength = 50

const charCount = computed(() => description.value.length)

const validationError = computed(() => {
  const trimmed = description.value.trim()
  if (trimmed.length > 0 && trimmed.length < minLength) {
    return `Description must be at least ${minLength} characters (${trimmed.length}/${minLength}).`
  }
  if (description.value.length > maxLength) {
    return `Description exceeds ${maxLength} characters.`
  }
  if (sourceUrl.value && !/^https?:\/\//i.test(sourceUrl.value.trim())) {
    return 'Source URL must start with http:// or https://'
  }
  return null
})

const canSubmit = computed(() => {
  if (isSubmitting.value) return false
  if (description.value.trim().length < minLength) return false
  if (description.value.length > maxLength) return false
  if (validationError.value && sourceUrl.value && !/^https?:\/\//i.test(sourceUrl.value.trim()))
    return false
  return true
})

const duplicateMessage = computed(() => {
  if (duplicateInfo.value?.opportunityId || duplicateInfo.value?.status === 'confirmed') {
    return 'You already saved an opportunity from this job description.'
  }
  if (duplicateInfo.value?.status === 'cancelled') {
    return 'This job description is already in your history as a cancelled analysis.'
  }
  return 'This job description is already being analyzed or reviewed.'
})

watch(
  () => props.open,
  (v) => {
    if (v) {
      description.value = ''
      sourceUrl.value = ''
      personalLabel.value = ''
      errorMessage.value = ''
      duplicateInfo.value = null
    }
  },
)

async function submit(): Promise<void> {
  if (!canSubmit.value) return

  isSubmitting.value = true
  errorMessage.value = ''
  duplicateInfo.value = null

  try {
    const result = await createIngestion({
      source_description: description.value,
      source_url: sourceUrl.value.trim() || undefined,
      personal_label: personalLabel.value.trim() || undefined,
    })

    emit('created')
    emit('close')
    await nextTick()
    router.push(`/opportunities/ingestions/${result.id}`)
  } catch (err) {
    const detail = extractProblemDetail(err as never)

    if (detail?.code === 'duplicate_ingestion') {
      const extra = detail.errors
      duplicateInfo.value = {
        ingestionId: extra.existing_ingestion_id as number,
        opportunityId: extra.existing_opportunity_id as number | undefined,
        status: extra.existing_status as IngestionStatus | undefined,
      }
      errorMessage.value = ''
    } else {
      errorMessage.value =
        detail && detail.status < 500
          ? detail.detail
          : "We couldn't start the analysis. Please try again in a moment."
    }
  } finally {
    isSubmitting.value = false
  }
}

function navigateToExisting(): void {
  if (!duplicateInfo.value) return

  emit('close')

  if (duplicateInfo.value.opportunityId) {
    router.push(`/opportunities/${duplicateInfo.value.opportunityId}`)
    return
  }

  const suffix = duplicateInfo.value.status === 'review_ready' ? '/review' : ''
  router.push(`/opportunities/ingestions/${duplicateInfo.value.ingestionId}${suffix}`)
}

async function startDifferentImport(): Promise<void> {
  description.value = ''
  sourceUrl.value = ''
  personalLabel.value = ''
  duplicateInfo.value = null
  errorMessage.value = ''

  await nextTick()
  descriptionInput.value?.focus()
}
</script>

<template>
  <Modal :open="open" title="Add opportunity" size="lg" @close="emit('close')">
    <div class="modal-body">
      <div class="modal-info">
        Paste the full job description below. The system will extract the key information which you
        can review and edit before saving.
      </div>

      <form id="add-opportunity-form" class="modal-form" @submit.prevent="submit">
        <div class="form-group">
          <label for="add-opp-description" class="form-label"> Job description </label>
          <textarea
            id="add-opp-description"
            ref="descriptionInput"
            v-model="description"
            name="source_description"
            autocomplete="off"
            class="form-textarea"
            rows="12"
            :maxlength="maxLength"
            placeholder="Paste the full job description here..."
          />
          <div class="form-hint">
            <span v-if="charCount < minLength && charCount > 0" class="hint-warning">
              Minimum {{ minLength }} characters
            </span>
            <span v-else-if="charCount > maxLength * 0.9" class="hint-warning">
              {{ charCount }} / {{ maxLength }}
            </span>
            <span v-else>{{ charCount }} / {{ maxLength }}</span>
          </div>
        </div>

        <div class="form-group">
          <label for="add-opp-source-url" class="form-label form-label-inline">
            <ExternalLink class="label-icon" aria-hidden="true" />
            Source URL
            <span class="label-optional">(optional)</span>
          </label>
          <input
            id="add-opp-source-url"
            v-model="sourceUrl"
            name="source_url"
            type="url"
            autocomplete="off"
            class="form-input"
            placeholder="https://example.com/job-posting"
          />
        </div>

        <div class="form-group">
          <label for="add-opp-label" class="form-label form-label-inline">
            <Tag class="label-icon" aria-hidden="true" />
            Personal label
            <span class="label-optional">(optional)</span>
          </label>
          <input
            id="add-opp-label"
            v-model="personalLabel"
            name="personal_label"
            type="text"
            autocomplete="off"
            class="form-input"
            placeholder="e.g., Frontend role at ACME"
            maxlength="255"
          />
        </div>

        <div v-if="validationError && description.trim().length > 0" class="validation-error">
          <AlertTriangle class="error-icon" aria-hidden="true" />
          {{ validationError }}
        </div>

        <div v-if="errorMessage && !duplicateInfo" role="alert" class="error-alert">
          {{ errorMessage }}
        </div>

        <div v-if="duplicateInfo" role="alert" aria-live="polite" class="duplicate-alert">
          <p class="duplicate-title">This job was already imported</p>
          <p class="duplicate-message">{{ duplicateMessage }}</p>
          <div class="duplicate-actions">
            <button
              type="button"
              class="action-link action-link-primary"
              @click="navigateToExisting"
            >
              View existing {{ duplicateInfo.opportunityId ? 'opportunity' : 'analysis' }}
            </button>
            <button
              type="button"
              class="action-link action-link-secondary"
              @click="startDifferentImport"
            >
              Import a different job
            </button>
          </div>
        </div>
      </form>
    </div>

    <template #footer>
      <div class="modal-footer">
        <Button variant="outline" @click="emit('close')">Cancel</Button>
        <Button
          type="submit"
          form="add-opportunity-form"
          :disabled="!canSubmit"
          :loading="isSubmitting"
        >
          {{ isSubmitting ? 'Submitting...' : 'Start analysis' }}
        </Button>
      </div>
    </template>
  </Modal>
</template>

<style scoped>
.modal-body {
  padding: 1.25rem 1.5rem;
}

.modal-info {
  border-radius: var(--radius-md);
  padding: 0.875rem;
  background: var(--color-info-50);
  color: var(--color-info-700);
  font-size: 0.875rem;
  line-height: 1.5;
}

.modal-form {
  margin-top: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-primary);
}

.form-label-inline {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.label-icon {
  width: 0.875rem;
  height: 0.875rem;
  color: var(--text-muted);
}

.label-optional {
  font-size: 0.75rem;
  font-weight: 400;
  color: var(--text-muted);
}

.form-textarea,
.form-input {
  margin-top: 0.375rem;
  width: 100%;
  border-radius: var(--radius-lg);
  padding: 0.875rem;
  font-size: 0.875rem;
  background: var(--surface-secondary);
  color: var(--text-primary);
  box-shadow: var(--shadow-neo-inset);
  transition: all 200ms ease;
}

.form-textarea:focus,
.form-input:focus {
  outline: none;
  ring: 2px;
  ring-color: var(--color-primary-500);
}

.form-textarea::placeholder,
.form-input::placeholder {
  color: var(--text-muted);
}

.form-hint {
  margin-top: 0.375rem;
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: var(--text-muted);
}

.hint-warning {
  color: var(--color-warning-600);
}

.validation-error {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--color-error-600);
}

.error-icon {
  margin-top: 0.125rem;
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}

.error-alert {
  border-radius: var(--radius-lg);
  padding: 0.75rem;
  background: var(--color-error-50);
  color: var(--color-error-700);
  font-size: 0.875rem;
}

.duplicate-alert {
  border-radius: var(--radius-lg);
  padding: 1rem;
  background: var(--color-warning-50);
  color: var(--color-warning-900);
  font-size: 0.875rem;
}

.duplicate-title {
  font-weight: 500;
}

.duplicate-message {
  margin-top: 0.25rem;
  color: var(--color-warning-800);
}

.duplicate-actions {
  margin-top: 0.75rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.action-link {
  font-weight: 500;
  border-radius: var(--radius-md);
  padding: 0.25rem 0.5rem;
  transition: color 150ms ease;
}

.action-link-primary {
  color: var(--color-primary-700);
}

.action-link-primary:hover {
  color: var(--color-primary-800);
}

.action-link-secondary {
  color: var(--text-secondary);
}

.action-link-secondary:hover {
  color: var(--text-primary);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}
</style>
