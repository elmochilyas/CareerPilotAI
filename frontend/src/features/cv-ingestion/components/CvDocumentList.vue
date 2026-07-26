<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { Trash2, AlertTriangle, Eye, ArrowRight } from '@lucide/vue'
import { cvKeys, fetchCvDocuments, deleteCvDocument } from '../api'
import type { CvDocument, CvDocumentStatus } from '../types'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

const props = defineProps<{
  highlightId?: number | null
}>()

const emit = defineEmits<{
  deleted: [id: number]
  review: [id: number]
  view: [id: number]
}>()

const listRef = ref<HTMLElement | null>(null)
const deleteTarget = ref<CvDocument | null>(null)

const queryClient = useQueryClient()

const query = useQuery({
  queryKey: cvKeys.list(),
  queryFn: fetchCvDocuments,
  retry: 1,
})

const deleteMutation = useMutation({
  mutationFn: (id: number) => deleteCvDocument(id),
  onSuccess: (_, id) => {
    queryClient.invalidateQueries({ queryKey: cvKeys.list() })
    deleteTarget.value = null
    emit('deleted', id)
  },
})

const documents = computed(() => {
  const all = query.data.value ?? []
  return all.filter((d) => d.status !== 'deleted')
})

const statusConfig: Record<
  CvDocumentStatus,
  { variant: 'default' | 'success' | 'warning' | 'error' | 'info'; label: string }
> = {
  pending: { variant: 'default', label: 'Pending' },
  queued: { variant: 'info', label: 'Queued' },
  validating: { variant: 'info', label: 'Validating' },
  extracting: { variant: 'info', label: 'Extracting' },
  analyzing: { variant: 'info', label: 'Analyzing' },
  ready_for_review: { variant: 'success', label: 'Ready' },
  importing: { variant: 'info', label: 'Importing' },
  imported: { variant: 'success', label: 'Imported' },
  failed: { variant: 'error', label: 'Failed' },
  deleted: { variant: 'default', label: 'Deleted' },
}

const isDeletable = (status: CvDocumentStatus): boolean => {
  return ['pending', 'failed', 'ready_for_review', 'imported'].includes(status)
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

function confirmDelete(doc: CvDocument): void {
  deleteTarget.value = doc
}

function cancelDelete(): void {
  deleteTarget.value = null
}

function executeDelete(): void {
  if (deleteTarget.value) {
    deleteMutation.mutate(deleteTarget.value.id)
  }
}

function scrollToHighlight(): void {
  if (props.highlightId && listRef.value) {
    nextTick(() => {
      const el = listRef.value?.querySelector(`[data-cv-id="${props.highlightId}"]`)
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' })
      }
    })
  }
}

watch(
  () => props.highlightId,
  (id) => {
    if (id) scrollToHighlight()
  },
)

watch(
  () => query.data.value,
  (docs) => {
    if (props.highlightId && docs && docs.length > 0) {
      nextTick(scrollToHighlight)
    }
  },
)
</script>

<template>
  <section aria-labelledby="cv-list-heading">
    <h2 id="cv-list-heading" class="text-lg font-semibold text-slate-900">Your CV Documents</h2>

    <div v-if="query.isLoading.value" class="mt-4 space-y-3">
      <Skeleton classes="h-16 w-full" />
      <Skeleton classes="h-16 w-full" />
      <Skeleton classes="h-16 w-full" />
    </div>

    <div
      v-else-if="query.isError.value"
      class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
      role="alert"
    >
      Failed to load CV documents. Please try again.
    </div>

    <div v-else-if="documents.length === 0" class="mt-4">
      <EmptyState
        title="No CVs uploaded yet"
        description="Upload your first CV to get started with profile suggestions."
      />
    </div>

    <div v-else ref="listRef" class="mt-4 space-y-2">
      <div
        v-for="doc in documents"
        :key="doc.id"
        :data-cv-id="doc.id"
        :class="[
          'flex items-center gap-4 rounded-lg border p-4 transition-colors',
          doc.id === highlightId
            ? 'border-amber-300 bg-amber-50 ring-1 ring-amber-300'
            : 'border-slate-200 bg-white hover:border-slate-300',
        ]"
      >
        <div
          v-if="doc.id === highlightId"
          class="mr-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-200"
        >
          <AlertTriangle class="size-4 text-amber-700" aria-hidden="true" />
        </div>

        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-slate-900" :title="doc.original_name">
            {{ doc.original_name }}
          </p>
          <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
            <span>{{ formatSize(doc.size) }}</span>
            <span aria-hidden="true">&middot;</span>
            <span>{{ formatDate(doc.created_at) }}</span>
            <span aria-hidden="true">&middot;</span>
            <Badge :variant="statusConfig[doc.status].variant">
              {{ statusConfig[doc.status].label }}
            </Badge>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
          <Button
            v-if="doc.status === 'ready_for_review'"
            variant="primary"
            size="sm"
            @click="emit('review', doc.id)"
          >
            <ArrowRight class="mr-1 size-3.5" aria-hidden="true" />
            Review
          </Button>
          <Button
            v-if="doc.status === 'imported'"
            variant="outline"
            size="sm"
            @click="emit('view', doc.id)"
          >
            <Eye class="mr-1 size-3.5" aria-hidden="true" />
            View
          </Button>
          <Button
            variant="ghost"
            size="sm"
            :disabled="!isDeletable(doc.status)"
            :title="isDeletable(doc.status) ? 'Delete this CV' : 'Cannot delete while processing'"
            @click="confirmDelete(doc)"
          >
            <Trash2 class="size-3.5 text-slate-400 hover:text-red-600" aria-hidden="true" />
            <span class="sr-only">Delete {{ doc.original_name }}</span>
          </Button>
        </div>
      </div>
    </div>

    <ConfirmDialog
      :open="deleteTarget !== null"
      :busy="deleteMutation.isPending.value"
      title="Delete CV document?"
      :description="`Are you sure you want to delete \u201c${deleteTarget?.original_name ?? ''}\u201d? This action cannot be undone.`"
      @confirm="executeDelete"
      @cancel="cancelDelete"
    />
  </section>
</template>
