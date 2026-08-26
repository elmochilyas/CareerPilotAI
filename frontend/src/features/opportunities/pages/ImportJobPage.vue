<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useRouter } from 'vue-router'
import { extractProblemDetail } from '@/api/client'
import BackButton from '@/components/ui/BackButton.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import Alert from '@/components/ui/Alert.vue'
import { createIngestion } from '../api'
import type { IngestionStatus } from '../types'

const router = useRouter()

const description = ref('')
const sourceUrl = ref('')
const personalLabel = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')
const descriptionRef = ref<InstanceType<typeof Textarea> | null>(null)
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

    router.push({ name: 'opportunities-processing', params: { id: result.id } })
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
          : 'We couldn’t start the analysis. Please try again in a moment.'
    }
  } finally {
    isSubmitting.value = false
  }
}

function navigateToExisting(): void {
  if (!duplicateInfo.value) return

  if (duplicateInfo.value.opportunityId) {
    router.push({ name: 'opportunities-detail', params: { id: duplicateInfo.value.opportunityId } })
    return
  }

  if (duplicateInfo.value.status === 'review_ready') {
    router.push({ name: 'opportunities-review', params: { id: duplicateInfo.value.ingestionId } })
  } else {
    router.push({
      name: 'opportunities-processing',
      params: { id: duplicateInfo.value.ingestionId },
    })
  }
}

async function startDifferentImport(): Promise<void> {
  description.value = ''
  sourceUrl.value = ''
  personalLabel.value = ''
  duplicateInfo.value = null
  errorMessage.value = ''

  await nextTick()
  descriptionRef.value?.focus()
}
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6">
    <BackButton :to="{ name: 'opportunities' }" label="Back to opportunities" class="mb-4" />

    <PageHeader title="Import job description" />

    <Alert variant="info">
      Paste the full job description below. The system will extract the key information which you
      can review and edit before saving.
    </Alert>

    <form @submit.prevent="submit" class="space-y-4">
      <Textarea
        ref="descriptionRef"
        v-model="description"
        name="source_description"
        label="Job description"
        :rows="12"
        :maxlength="maxLength"
        placeholder="Paste the full job description here…"
        autocomplete="off"
      />
      <div class="flex justify-between text-xs text-[var(--text-muted)]">
        <span v-if="charCount < minLength" class="text-[var(--color-warning-500)]">
          Minimum {{ minLength }} characters
        </span>
        <span v-else-if="charCount > maxLength * 0.9" class="text-[var(--color-warning-500)]">
          {{ charCount }} / {{ maxLength }}
        </span>
        <span v-else>{{ charCount }} / {{ maxLength }}</span>
      </div>

      <Input
        v-model="sourceUrl"
        name="source_url"
        label="Source URL (optional)"
        type="url"
        placeholder="https://example.com/job-posting"
        autocomplete="off"
      />

      <Input
        v-model="personalLabel"
        name="personal_label"
        label="Personal label (optional)"
        type="text"
        placeholder="e.g., Frontend role at ACME"
        :maxlength="255"
        autocomplete="off"
      />

      <div
        v-if="validationError && description.trim().length > 0"
        class="text-sm text-[var(--color-error-600)]"
      >
        {{ validationError }}
      </div>

      <Alert v-if="errorMessage && !duplicateInfo" variant="error">
        {{ errorMessage }}
      </Alert>

      <div v-if="duplicateInfo" role="alert" aria-live="polite">
        <Alert variant="warning" title="This job was already imported">
          <p>{{ duplicateMessage }}</p>
          <div class="mt-3 flex flex-wrap gap-3">
            <button
              type="button"
              class="font-medium text-[var(--color-primary-600)] hover:text-[var(--color-primary-800)] focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary-600)]"
              @click="navigateToExisting"
            >
              View existing {{ duplicateInfo.opportunityId ? 'opportunity' : 'analysis' }}
            </button>
            <button
              type="button"
              class="font-medium text-[var(--text-secondary)] hover:text-[var(--text-primary)] focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary-600)]"
              @click="startDifferentImport"
            >
              Import a different job
            </button>
          </div>
        </Alert>
      </div>

      <div class="flex gap-3">
        <Button type="submit" :disabled="!canSubmit" :loading="isSubmitting">
          {{ isSubmitting ? 'Submitting…' : 'Start analysis' }}
        </Button>
      </div>
    </form>
  </div>
</template>
