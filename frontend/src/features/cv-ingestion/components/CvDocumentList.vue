<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { Trash2, AlertTriangle, Eye, ArrowRight, FileText } from '@lucide/vue'
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
  { variant: 'default' | 'success' | 'warning' | 'error' | 'info'; label: string; dot: string }
> = {
  pending: { variant: 'default', label: 'Pending', dot: 'bg-slate-400' },
  queued: { variant: 'info', label: 'Queued', dot: 'bg-blue-400' },
  validating: { variant: 'info', label: 'Validating', dot: 'bg-blue-400' },
  extracting: { variant: 'info', label: 'Extracting', dot: 'bg-blue-400' },
  analyzing: { variant: 'info', label: 'Analyzing', dot: 'bg-primary-400' },
  ready_for_review: { variant: 'success', label: 'Ready', dot: 'bg-emerald-500' },
  importing: { variant: 'info', label: 'Importing', dot: 'bg-primary-400' },
  imported: { variant: 'success', label: 'Imported', dot: 'bg-emerald-500' },
  failed: { variant: 'error', label: 'Failed', dot: 'bg-red-500' },
  deleted: { variant: 'default', label: 'Deleted', dot: 'bg-slate-400' },
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
  const d = new Date(iso)
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  const hours = diff / (1000 * 60 * 60)

  if (hours < 24) {
    if (hours < 1) return 'Just now'
    const h = Math.floor(hours)
    return `${h}h ago`
  }
  if (hours < 48) return 'Yesterday'
  if (hours < 168) {
    const days = Math.floor(hours / 24)
    return `${days}d ago`
  }
  return d.toLocaleDateString(undefined, {
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
    <div class="flex items-center gap-2">
      <h2 id="cv-list-heading" class="text-base font-semibold text-slate-900">Your CV Documents</h2>
      <span
        v-if="documents.length > 0"
        class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-500"
      >
        {{ documents.length }}
      </span>
    </div>

    <div v-if="query.isLoading.value" class="mt-4 space-y-3">
      <Skeleton classes="h-16 w-full rounded-xl" />
      <Skeleton classes="h-16 w-full rounded-xl" />
      <Skeleton classes="h-16 w-full rounded-xl" />
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
          'group flex items-center gap-4 rounded-xl border p-4 transition-all',
          doc.id === highlightId
            ? 'border-amber-300 bg-amber-50 ring-1 ring-amber-300 shadow-sm'
            : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm',
        ]"
      >
        <!-- Status dot -->
        <div
          class="flex h-2.5 w-2.5 shrink-0 rounded-full"
          :class="statusConfig[doc.status].dot"
          :title="statusConfig[doc.status].label"
          aria-hidden="true"
        />

        <!-- Highlight icon -->
        <div
          v-if="doc.id === highlightId"
          class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-200/80"
        >
          <AlertTriangle class="h-4 w-4 text-amber-700" aria-hidden="true" />
        </div>

        <!-- File icon -->
        <div
          v-else
          class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-50 transition-colors group-hover:bg-primary-50"
        >
          <FileText
            class="h-4 w-4 text-slate-400 group-hover:text-primary-500"
            aria-hidden="true"
          />
        </div>

        <!-- Info -->
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-slate-900" :title="doc.original_name">
            {{ doc.original_name }}
          </p>
          <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
            <span>{{ formatSize(doc.size) }}</span>
            <span aria-hidden="true">&middot;</span>
            <span :title="new Date(doc.created_at).toLocaleString()">{{
              formatDate(doc.created_at)
            }}</span>
            <span aria-hidden="true">&middot;</span>
            <Badge :variant="statusConfig[doc.status].variant">
              {{ statusConfig[doc.status].label }}
            </Badge>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex shrink-0 items-center gap-1.5">
          <Button
            v-if="doc.status === 'ready_for_review'"
            variant="primary"
            size="sm"
            class="shadow-xs"
            @click="emit('review', doc.id)"
          >
            <ArrowRight class="mr-1 h-3.5 w-3.5" aria-hidden="true" />
            Review
          </Button>
          <Button
            v-if="doc.status === 'imported'"
            variant="outline"
            size="sm"
            @click="emit('view', doc.id)"
          >
            <Eye class="mr-1 h-3.5 w-3.5" aria-hidden="true" />
            View
          </Button>
          <button
            v-if="isDeletable(doc.status)"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            :title="'Delete ' + doc.original_name"
            :aria-label="'Delete ' + doc.original_name"
            @click="confirmDelete(doc)"
          >
            <Trash2 class="h-4 w-4" aria-hidden="true" />
          </button>
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
