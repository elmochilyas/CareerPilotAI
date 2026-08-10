<script setup lang="ts">
import { ref, computed } from 'vue'
import type { TailoringProposal, TailoringProposalStatus, TailoringChangeType } from '../types'

const props = defineProps<{
  proposals: TailoringProposal[]
}>()

const emit = defineEmits<{
  'update:status': [proposalId: number, status: TailoringProposalStatus]
  edit: [proposalId: number, editedText: string]
}>()
const editingId = ref<number | null>(null)
const editedText = ref('')

function startEditing(proposal: TailoringProposal): void {
  editingId.value = proposal.id
  editedText.value = proposal.edited_text ?? proposal.proposed_text
}

function saveEdit(proposalId: number): void {
  if (!editedText.value.trim()) return
  emit('edit', proposalId, editedText.value.trim())
  editingId.value = null
}

const filter = ref<'all' | TailoringChangeType>('all')

const filteredProposals = computed(() => {
  if (filter.value === 'all') return props.proposals
  return props.proposals.filter((p) => p.change_type === filter.value)
})

const stats = computed(() => {
  const total = props.proposals.length
  const accepted = props.proposals.filter((p) => p.status === 'accepted').length
  const rejected = props.proposals.filter((p) => p.status === 'rejected').length
  const pending = total - accepted - rejected
  return { total, accepted, rejected, pending }
})

function changeTypeLabel(type: TailoringChangeType): string {
  const labels: Record<TailoringChangeType, string> = {
    reword: 'Reworded',
    reorder: 'Reordered',
    include: 'Included',
    exclude: 'Excluded',
  }
  return labels[type]
}

function changeTypeColor(type: TailoringChangeType): string {
  const colors: Record<TailoringChangeType, string> = {
    reword: 'bg-[var(--color-primary-50)] text-[var(--color-primary-700)]',
    reorder: 'bg-[var(--color-warning-50)] text-[var(--color-warning-700)]',
    include: 'bg-[var(--color-success-50)] text-[var(--color-success-700)]',
    exclude: 'bg-[var(--color-error-50)] text-[var(--color-error-700)]',
  }
  return colors[type]
}

function statusColor(status: TailoringProposalStatus): string {
  const colors: Record<TailoringProposalStatus, string> = {
    proposed: 'bg-[var(--color-neutral-100)] text-[var(--text-muted)]',
    accepted: 'bg-[var(--color-success-100)] text-[var(--color-success-700)]',
    rejected: 'bg-[var(--color-error-100)] text-[var(--color-error-700)]',
  }
  return colors[status]
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="text-sm font-semibold text-[var(--text-primary)]">AI Proposals</h3>
      <div class="flex gap-1">
        <button
          v-for="f in ['all', 'reword', 'reorder', 'include', 'exclude'] as const"
          :key="f"
          :class="[
            'rounded-lg px-2.5 py-1 text-xs font-medium transition-colors',
            filter === f
              ? 'bg-[var(--color-primary-100)] text-[var(--color-primary-700)]'
              : 'text-[var(--text-muted)] hover:bg-[var(--surface-secondary)]',
          ]"
          @click="filter = f"
        >
          {{ f === 'all' ? 'All' : changeTypeLabel(f) }}
        </button>
      </div>
    </div>

    <div class="flex gap-3 text-xs">
      <span class="text-[var(--text-muted)]"> {{ stats.accepted }} accepted </span>
      <span class="text-[var(--text-muted)]"> {{ stats.rejected }} rejected </span>
      <span class="text-[var(--text-muted)]"> {{ stats.pending }} pending </span>
    </div>

    <ul class="space-y-3" role="list">
      <li
        v-for="proposal in filteredProposals"
        :key="proposal.id"
        :class="[
          'rounded-xl border p-4 transition-all duration-150',
          proposal.status === 'accepted'
            ? 'border-[var(--color-success-200)] bg-[var(--color-success-50)]/50'
            : proposal.status === 'rejected'
              ? 'border-[var(--color-error-200)] bg-[var(--color-error-50)]/50'
              : 'border-[var(--color-neutral-100)] bg-white',
        ]"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <span
                :class="[
                  'rounded-md px-1.5 py-0.5 text-[10px] font-semibold',
                  changeTypeColor(proposal.change_type),
                ]"
              >
                {{ changeTypeLabel(proposal.change_type) }}
              </span>
              <span
                :class="[
                  'rounded-md px-1.5 py-0.5 text-[10px] font-semibold',
                  statusColor(proposal.status),
                ]"
              >
                {{ proposal.status }}
              </span>
            </div>

            <div class="space-y-1">
              <p class="text-xs text-[var(--text-muted)]">
                <span class="font-medium">Original:</span>
                <span class="ml-1 line-through">{{ proposal.original_text }}</span>
              </p>
              <p class="text-sm text-[var(--text-primary)]">
                <span class="font-medium">Proposed:</span>
                <span class="ml-1">{{ proposal.proposed_text }}</span>
              </p>
              <p v-if="proposal.edited_text" class="text-sm text-[var(--color-primary-700)]">
                <span class="font-medium">Edited:</span>
                <span class="ml-1">{{ proposal.edited_text }}</span>
              </p>
              <div v-if="editingId === proposal.id" class="space-y-2">
                <label :for="`proposal-edit-${proposal.id}`" class="text-xs font-medium">Your wording</label>
                <textarea :id="`proposal-edit-${proposal.id}`" v-model="editedText" rows="3" class="w-full rounded-lg border border-[var(--color-neutral-200)] p-2 text-sm" />
                <button class="rounded-lg bg-[var(--color-primary-600)] px-3 py-1.5 text-xs font-medium text-white" @click="saveEdit(proposal.id)">Save and accept</button>
              </div>
            </div>
          </div>

          <div class="flex shrink-0 gap-1.5">
            <button class="rounded-lg border border-[var(--color-neutral-200)] bg-white px-2.5 py-1.5 text-xs font-medium" @click="startEditing(proposal)">Edit</button>
            <button
              :class="[
                'rounded-lg px-2.5 py-1.5 text-xs font-medium transition-all',
                proposal.status === 'accepted'
                  ? 'bg-[var(--color-success-600)] text-white'
                  : 'border border-[var(--color-success-200)] bg-white text-[var(--color-success-700)] hover:bg-[var(--color-success-50)]',
              ]"
              :aria-label="'Accept proposal ' + proposal.id"
              @click="emit('update:status', proposal.id, 'accepted')"
            >
              Accept
            </button>
            <button
              :class="[
                'rounded-lg px-2.5 py-1.5 text-xs font-medium transition-all',
                proposal.status === 'rejected'
                  ? 'bg-[var(--color-error-600)] text-white'
                  : 'border border-[var(--color-error-200)] bg-white text-[var(--color-error-700)] hover:bg-[var(--color-error-50)]',
              ]"
              :aria-label="'Reject proposal ' + proposal.id"
              @click="emit('update:status', proposal.id, 'rejected')"
            >
              Reject
            </button>
            <button v-if="proposal.status !== 'proposed'" class="rounded-lg border border-[var(--color-neutral-200)] bg-white px-2.5 py-1.5 text-xs font-medium" @click="emit('update:status', proposal.id, 'proposed')">Revert</button>
          </div>
        </div>
      </li>
    </ul>

    <p
      v-if="filteredProposals.length === 0"
      class="py-8 text-center text-sm text-[var(--text-muted)]"
    >
      No proposals{{ filter !== 'all' ? ' for this filter' : '' }}.
    </p>
  </div>
</template>
