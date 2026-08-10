<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import { extractProblemDetail } from '@/api/client'
import ResumeDocumentPreview from '../components/ResumeDocumentPreview.vue'
import StalenessBanner from '../components/StalenessBanner.vue'
import StepNavigation from '../components/StepNavigation.vue'
import { useTailoringWorkspace } from '../composables/useTailoringWorkspace'
import type { TailoringProposalStatus } from '../types'

const route = useRoute()
const opportunityId = computed(() => Number(route.params.id))
const workspace = useTailoringWorkspace(opportunityId)
const {
  resume,
  isCreating,
  currentStep,
  currentStepIndex,
  steps,
  resumeQuery,
  createMutation,
  tailorMutation,
  updateMutation,
  approveMutation,
} = workspace

const isResumeError = computed(() => resumeQuery.isError?.value ?? false)
const proposals = computed(() => resume.value?.content.proposals ?? [])
const unresolvedCount = computed(
  () => proposals.value.filter((proposal) => proposal.status === 'proposed').length,
)
const hasGeneratedVersion = computed(
  () => (resume.value?.content.sections ?? []).some((section) => section.items.length > 0),
)
const enabledThrough = computed(() => {
  if (resume.value?.status === 'approved') return 2
  return hasGeneratedVersion.value ? 1 : 0
})
const requestError = computed(() => {
  const error =
    tailorMutation.error?.value ??
    createMutation.error?.value ??
    updateMutation.error?.value ??
    approveMutation.error?.value
  return error
    ? (extractProblemDetail(error as never)?.detail ?? 'The request failed. Please try again.')
    : null
})

async function resolveGeneratedWording(status: TailoringProposalStatus): Promise<void> {
  if (!resume.value || unresolvedCount.value === 0) return

  await updateMutation.mutateAsync({
    resumeId: resume.value.id,
    content: resume.value.content,
    proposal_decisions: proposals.value
      .filter((proposal) => proposal.status === 'proposed')
      .map((proposal) => ({ id: proposal.id, status, edited_text: null })),
  })
}

</script>

<template>
  <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div v-if="isCreating" class="py-16 text-center" role="status" aria-live="polite">
      Preparing your tailored CV…
    </div>

    <section
      v-else-if="isResumeError"
      role="alert"
      class="rounded-xl border border-[var(--color-error-200)] p-4"
    >
      <h1 class="font-semibold">Failed to load resume</h1>
      <p class="mt-1 text-sm">{{ workspace.loadErrorDetail.value }}</p>
      <Button class="mt-3" @click="resumeQuery.refetch()">Try Again</Button>
    </section>

    <section v-else-if="!resume" class="py-16 text-center">
      <h1 class="text-xl font-semibold">Generate Your Tailored CV</h1>
      <p class="mt-2 text-sm text-[var(--text-muted)]">
        CareerPilot uses only your trusted profile and this opportunity's completed match analysis.
      </p>
      <Button
        class="mt-5"
        :loading="createMutation.isPending.value"
        @click="createMutation.mutate()"
      >
        Generate Tailored CV
      </Button>
      <p v-if="createMutation.isPending.value" class="mt-3 text-sm" role="status" aria-live="polite">
        Selecting and tailoring your trusted CV content…
      </p>
    </section>

    <template v-else>
      <StalenessBanner v-if="resume.stale" :reason="resume.stale_reason" />

      <header class="mb-8">
        <h1 class="text-2xl font-semibold">{{ resume.title }}</h1>
        <p class="mt-1 text-sm text-[var(--text-muted)]">
          Version {{ resume.version_no }} · {{ resume.status }}
        </p>
      </header>

      <StepNavigation
        :steps="steps"
        :active-index="currentStepIndex"
        :enabled-through="enabledThrough"
        @navigate="workspace.goToStep"
      />

      <p
        v-if="requestError"
        class="mt-4 rounded-lg bg-[var(--color-error-50)] p-3 text-sm text-[var(--color-error-700)]"
        role="alert"
        aria-live="polite"
      >
        {{ requestError }}
      </p>

      <section
        class="mt-6 rounded-xl border border-[var(--color-neutral-200)] bg-[var(--surface-primary)] p-6"
      >
        <div v-if="currentStep === 'generate'" class="space-y-5">
          <div>
            <h2 class="text-lg font-semibold">Generate Tailored CV</h2>
            <p class="text-sm text-[var(--text-muted)]">
              Generate a persisted version selected and ordered for this opportunity from trusted profile data.
            </p>
          </div>
          <Button
            v-if="resume.status === 'draft'"
            :loading="tailorMutation.isPending.value"
            @click="tailorMutation.mutate(resume.id)"
          >
            {{ hasGeneratedVersion ? 'Regenerate Tailored CV' : 'Generate Tailored CV' }}
          </Button>
          <p v-if="tailorMutation.isPending.value" role="status" aria-live="polite" class="text-sm">
            Generating and validating truthful content…
          </p>
        </div>

        <div v-else-if="currentStep === 'review'" class="space-y-5">
          <div>
            <h2 class="text-lg font-semibold">Review Final Tailored CV</h2>
            <p class="text-sm text-[var(--text-muted)]">
              Review the complete CV below before saving the final immutable version.
            </p>
          </div>

          <div
            v-if="unresolvedCount > 0"
            class="rounded-lg border border-[var(--color-neutral-200)] p-4"
          >
            <p class="text-sm">
              {{ unresolvedCount }} validated wording suggestion(s) are ready. Choose whether to use the tailored wording or preserve your original wording.
            </p>
            <div class="mt-3 flex flex-wrap gap-3">
              <Button
                :loading="updateMutation.isPending.value"
                @click="resolveGeneratedWording('accepted')"
              >
                Use Tailored Wording
              </Button>
              <Button
                variant="outline"
                :disabled="updateMutation.isPending.value"
                @click="resolveGeneratedWording('rejected')"
              >
                Keep Original Wording
              </Button>
            </div>
          </div>

          <ResumeDocumentPreview :resume-id="resume.id" />

          <Button
            v-if="resume.status === 'draft'"
            :loading="approveMutation.isPending.value"
            :disabled="!hasGeneratedVersion || unresolvedCount > 0"
            @click="approveMutation.mutate(resume.id)"
          >
            Save Final Tailored CV
          </Button>
        </div>

        <div v-else class="space-y-5">
          <div>
            <h2 class="text-lg font-semibold">Saved Tailored CV</h2>
            <p class="text-sm text-[var(--text-muted)]">
              This approved version is saved in CareerPilot and preserved after refresh.
            </p>
          </div>
          <div class="cv-print-actions flex flex-wrap gap-3">
            <a
              :href="`/api/v1/resumes/${resume.id}/download/pdf`"
              class="inline-flex items-center rounded-xl bg-[var(--color-primary-600)] px-4 py-2 text-sm font-medium text-white shadow-[var(--shadow-neo-button)] hover:bg-[var(--color-primary-700)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary-500)]"
            >Download PDF</a>
            <Button variant="outline" @click="workspace.goToStep('review')">View CV</Button>
          </div>
          <div class="cv-print-area">
            <ResumeDocumentPreview :resume-id="resume.id" />
          </div>
          <RouterLink
            :to="`/opportunities/${opportunityId}`"
            class="cv-print-actions inline-flex text-sm font-medium text-[var(--color-primary-700)]"
          >Return to Opportunity</RouterLink>
        </div>
      </section>
    </template>
  </main>
</template>

<style>
@media print {
  body * {
    visibility: hidden;
  }

  .cv-print-area,
  .cv-print-area * {
    visibility: visible;
  }

  .cv-print-area {
    position: absolute;
    inset: 0;
    width: 100%;
    background: white;
    color: black;
  }

  .cv-print-actions {
    display: none !important;
  }
}
</style>
