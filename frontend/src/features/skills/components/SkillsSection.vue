<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { Plus, AlertCircle, RefreshCw, Wrench } from '@lucide/vue'
import { fetchProfile } from '@/features/profile/api'
import { useSkills } from '../composables/useSkills'
import SkillCard from './SkillCard.vue'
import AddSkillFlow from './AddSkillFlow.vue'
import EvidenceSelector from './EvidenceSelector.vue'
import type { CandidateSkill, EvidenceInput } from '../types'
import type { ProfileItemOption } from './EvidenceSelector.vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Modal from '@/components/ui/Modal.vue'

const {
  skills,
  isPending,
  isError,
  announcement,
  refreshCandidate,
  createMutation,
  archiveMutation,
  restoreMutation,
  deleteMutation,
  addEvidenceMutation,
  removeEvidenceMutation,
} = useSkills()

const profileQuery = useQuery({
  queryKey: ['profile', 'detail'],
  queryFn: fetchProfile,
})

const profileItems = computed<Record<string, ProfileItemOption[]>>(() => {
  const items = profileQuery.data.value?.items
  if (!items) {
    return {}
  }
  return Object.fromEntries(
    Object.entries(items).map(([type, entries]) => [
      type,
      entries.map((item) => ({
        id: item.id,
        type: item.type,
        title: item.title,
        organization: item.organization,
        start_date: item.start_date,
        end_date: item.end_date,
      })),
    ]),
  )
})

const showAddFlow = ref(false)
const addFlowRef = ref<InstanceType<typeof AddSkillFlow> | null>(null)
const selectedSkill = ref<CandidateSkill | null>(null)
const showEvidenceSelector = ref(false)

const sortedSkills = computed(() => {
  const order: Record<string, number> = {
    verified: 0,
    claimed: 1,
    learning: 2,
    archived: 3,
    rejected: 4,
  }
  return [...skills.value].sort((a, b) => (order[a.state] ?? 9) - (order[b.state] ?? 9))
})

function onAddSkill(event: {
  skillId?: number | null
  customName?: string | null
  state: string
  proficiency: string
}) {
  createMutation.mutate(
    {
      skill_id: event.skillId ?? undefined,
      custom_skill_name: event.customName ?? undefined,
      state: event.state as 'claimed' | 'verified' | 'learning' | 'rejected' | 'archived',
      proficiency_level: event.proficiency as
        | 'beginner'
        | 'elementary'
        | 'intermediate'
        | 'advanced'
        | 'expert',
    },
    {
      onSuccess: () => {
        showAddFlow.value = false
        addFlowRef.value?.reset()
      },
    },
  )
}

function onArchive(skill: CandidateSkill) {
  archiveMutation.mutate({ id: skill.id, updatedAt: skill.updated_at })
}

function onEdit(skill: CandidateSkill) {
  selectedSkill.value = skill
  showEvidenceSelector.value = true
}

function onRestore(skill: CandidateSkill) {
  restoreMutation.mutate({ id: skill.id, state: 'claimed', updatedAt: skill.updated_at })
}

function onDelete(skill: CandidateSkill) {
  deleteMutation.mutate(skill.id)
}

function onAddEvidence(evidence: EvidenceInput) {
  if (!selectedSkill.value) return
  addEvidenceMutation.mutate({ id: selectedSkill.value.id, input: evidence })
}

function onRemoveEvidence(key: string) {
  if (!selectedSkill.value) return
  removeEvidenceMutation.mutate({ id: selectedSkill.value.id, key })
}

function isSaving(skill: CandidateSkill): boolean {
  return (
    createMutation.isPending.value ||
    archiveMutation.isPending.value ||
    restoreMutation.isPending.value ||
    deleteMutation.isPending.value ||
    (addEvidenceMutation.isPending.value && skill.id === selectedSkill.value?.id)
  )
}
</script>

<template>
  <Card>
    <template #header>
      <div class="flex items-center gap-3">
        <div
          class="flex size-9 items-center justify-center rounded-[var(--radius-md)] bg-[var(--color-primary-100)] text-[var(--color-primary-600)] shadow-[var(--shadow-neo-inset)]"
        >
          <Wrench :size="16" stroke-width="1.5" />
        </div>
        <div>
          <h2 class="text-base font-semibold text-slate-900">Skills</h2>
          <p class="text-xs text-slate-500">
            Manage your technical skills, tools, and competencies.
          </p>
        </div>
      </div>
      <Button v-if="!showAddFlow" variant="outline" size="sm" @click="showAddFlow = true">
        <Plus :size="14" aria-hidden="true" />
        Add Skill
      </Button>
    </template>

    <div
      v-if="announcement"
      class="mb-4 rounded-[var(--radius-md)] bg-[var(--color-primary-50)] px-4 py-3 text-sm text-[var(--color-primary-700)] shadow-[var(--shadow-neo-raised-sm)]"
      role="status"
    >
      {{ announcement }}
    </div>

    <div v-if="isPending" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="i in 3"
        :key="i"
        class="rounded-[var(--radius-xl)] bg-[var(--surface-primary)] p-4 shadow-[var(--shadow-neo-raised)]"
      >
        <Skeleton classes="h-28" />
      </div>
    </div>

    <div
      v-else-if="isError"
      class="flex flex-col items-center rounded-[var(--radius-xl)] bg-red-50 p-8 text-center shadow-[var(--shadow-neo-raised)]"
    >
      <AlertCircle class="size-8 text-red-400" aria-hidden="true" />
      <p class="mt-2 text-sm text-red-700">Could not load your skills.</p>
      <div class="mt-3">
        <Button variant="danger" size="sm" @click="refreshCandidate()">
          <RefreshCw :size="14" aria-hidden="true" />
          Retry
        </Button>
      </div>
    </div>

    <EmptyState v-else-if="skills.length === 0 && !showAddFlow" title="No skills added yet">
      <Button size="sm" @click="showAddFlow = true">
        <Plus :size="14" aria-hidden="true" />
        Add Your First Skill
      </Button>
    </EmptyState>

    <div v-else>
      <AddSkillFlow
        v-if="showAddFlow"
        ref="addFlowRef"
        :saving="createMutation.isPending.value"
        :existing-skills="skills"
        @save="onAddSkill"
        @cancel="showAddFlow = false"
      />

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <SkillCard
          v-for="skill in sortedSkills"
          :key="skill.id"
          :skill="skill"
          :saving="isSaving(skill)"
          @edit="onEdit"
          @archive="onArchive"
          @restore="onRestore"
          @delete="onDelete"
        />
      </div>
    </div>

    <Modal
      :open="showEvidenceSelector && !!selectedSkill"
      title="Manage Evidence"
      size="md"
      @close="showEvidenceSelector = false"
    >
      <div class="p-6">
        <p v-if="selectedSkill" class="mb-4 text-sm text-slate-500">
          {{ selectedSkill.skill?.name ?? 'Custom Skill' }}
        </p>
        <EvidenceSelector
          v-if="selectedSkill"
          :evidence="selectedSkill.evidence"
          :saving="addEvidenceMutation.isPending.value"
          :profile-items="profileItems"
          :profile-items-loading="profileQuery.isPending.value"
          :profile-items-error="profileQuery.isError.value"
          @add="onAddEvidence"
          @remove="onRemoveEvidence"
        />
      </div>
    </Modal>
  </Card>
</template>
