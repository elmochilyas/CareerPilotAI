<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useRouter } from 'vue-router'
import { extractProblemDetail } from '@/api/client'
import { createIngestion } from '../api'
import type { IngestionStatus } from '../types'

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
          : 'We couldn’t start the analysis. Please try again in a moment.'
    }
  } finally {
    isSubmitting.value = false
  }
}

function navigateToExisting(): void {
  if (!duplicateInfo.value) return

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
  <div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-semibold">Import job description</h1>

    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-700">
      Paste the full job description below. The system will extract the key information which you
      can review and edit before saving.
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label for="description" class="block text-sm font-medium text-gray-700">
          Job description
        </label>
        <textarea
          id="description"
          ref="descriptionInput"
          v-model="description"
          name="source_description"
          autocomplete="off"
          class="mt-1 block w-full rounded-lg border border-gray-300 p-3 text-sm focus-visible:border-blue-500 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-500"
          rows="12"
          :maxlength="maxLength"
          placeholder="Paste the full job description here…"
        />
        <div class="mt-1 flex justify-between text-xs text-gray-400">
          <span v-if="charCount < minLength" class="text-amber-500">
            Minimum {{ minLength }} characters
          </span>
          <span v-else-if="charCount > maxLength * 0.9" class="text-amber-500">
            {{ charCount }} / {{ maxLength }}
          </span>
          <span v-else>{{ charCount }} / {{ maxLength }}</span>
        </div>
      </div>

      <div>
        <label for="source_url" class="block text-sm font-medium text-gray-700">
          Source URL (optional)
        </label>
        <input
          id="source_url"
          v-model="sourceUrl"
          name="source_url"
          type="url"
          autocomplete="off"
          class="mt-1 block w-full rounded-lg border border-gray-300 p-2.5 text-sm focus-visible:border-blue-500 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-500"
          placeholder="https://example.com/job-posting"
        />
      </div>

      <div>
        <label for="personal_label" class="block text-sm font-medium text-gray-700">
          Personal label (optional)
        </label>
        <input
          id="personal_label"
          v-model="personalLabel"
          name="personal_label"
          type="text"
          autocomplete="off"
          class="mt-1 block w-full rounded-lg border border-gray-300 p-2.5 text-sm focus-visible:border-blue-500 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-blue-500"
          placeholder="e.g., Frontend role at ACME"
          maxlength="255"
        />
      </div>

      <div v-if="validationError && description.trim().length > 0" class="text-sm text-red-600">
        {{ validationError }}
      </div>

      <div
        v-if="errorMessage && !duplicateInfo"
        role="alert"
        class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
      >
        {{ errorMessage }}
      </div>

      <div
        v-if="duplicateInfo"
        role="alert"
        aria-live="polite"
        class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
      >
        <p class="font-medium">This job was already imported</p>
        <p class="mt-1 text-amber-800">{{ duplicateMessage }}</p>
        <div class="mt-3 flex flex-wrap gap-3">
          <button
            type="button"
            class="font-medium text-blue-700 hover:text-blue-900 focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
            @click="navigateToExisting"
          >
            View existing {{ duplicateInfo.opportunityId ? 'opportunity' : 'analysis' }}
          </button>
          <button
            type="button"
            class="font-medium text-gray-700 hover:text-gray-950 focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
            @click="startDifferentImport"
          >
            Import a different job
          </button>
        </div>
      </div>

      <div class="flex gap-3">
        <button
          type="submit"
          :disabled="!canSubmit"
          class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50 hover:enabled:bg-blue-700"
        >
          {{ isSubmitting ? 'Submitting…' : 'Start analysis' }}
        </button>
        <button
          type="button"
          class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none"
          @click="router.push({ name: 'opportunities' })"
        >
          Back to opportunities
        </button>
      </div>
    </form>
  </div>
</template>
