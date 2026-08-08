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
    <div class="px-6 py-5">
      <div class="rounded-lg border border-blue-100 bg-blue-50 p-3.5 text-sm text-blue-700">
        Paste the full job description below. The system will extract the key information which you
        can review and edit before saving.
      </div>

      <form id="add-opportunity-form" class="mt-5 space-y-4" @submit.prevent="submit">
        <div>
          <label for="add-opp-description" class="block text-sm font-medium text-slate-700">
            Job description
          </label>
          <textarea
            id="add-opp-description"
            ref="descriptionInput"
            v-model="description"
            name="source_description"
            autocomplete="off"
            class="mt-1.5 block w-full rounded-lg border border-slate-300 p-3 text-sm transition-colors placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
            rows="12"
            :maxlength="maxLength"
            placeholder="Paste the full job description here..."
          />
          <div class="mt-1.5 flex justify-between text-xs text-slate-400">
            <span v-if="charCount < minLength && charCount > 0" class="text-amber-500">
              Minimum {{ minLength }} characters
            </span>
            <span v-else-if="charCount > maxLength * 0.9" class="text-amber-500">
              {{ charCount }} / {{ maxLength }}
            </span>
            <span v-else>{{ charCount }} / {{ maxLength }}</span>
          </div>
        </div>

        <div>
          <label
            for="add-opp-source-url"
            class="flex items-center gap-1.5 text-sm font-medium text-slate-700"
          >
            <ExternalLink class="size-3.5 text-slate-400" aria-hidden="true" />
            Source URL
            <span class="text-xs font-normal text-slate-400">(optional)</span>
          </label>
          <input
            id="add-opp-source-url"
            v-model="sourceUrl"
            name="source_url"
            type="url"
            autocomplete="off"
            class="mt-1.5 block w-full rounded-lg border border-slate-300 p-2.5 text-sm transition-colors placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
            placeholder="https://example.com/job-posting"
          />
        </div>

        <div>
          <label
            for="add-opp-label"
            class="flex items-center gap-1.5 text-sm font-medium text-slate-700"
          >
            <Tag class="size-3.5 text-slate-400" aria-hidden="true" />
            Personal label
            <span class="text-xs font-normal text-slate-400">(optional)</span>
          </label>
          <input
            id="add-opp-label"
            v-model="personalLabel"
            name="personal_label"
            type="text"
            autocomplete="off"
            class="mt-1.5 block w-full rounded-lg border border-slate-300 p-2.5 text-sm transition-colors placeholder:text-slate-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30"
            placeholder="e.g., Frontend role at ACME"
            maxlength="255"
          />
        </div>

        <div
          v-if="validationError && description.trim().length > 0"
          class="flex items-start gap-2 text-sm text-red-600"
        >
          <AlertTriangle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
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
              class="font-medium text-primary-700 hover:text-primary-800 focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
              @click="navigateToExisting"
            >
              View existing {{ duplicateInfo.opportunityId ? 'opportunity' : 'analysis' }}
            </button>
            <button
              type="button"
              class="font-medium text-slate-600 hover:text-slate-900 focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600"
              @click="startDifferentImport"
            >
              Import a different job
            </button>
          </div>
        </div>
      </form>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3">
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
