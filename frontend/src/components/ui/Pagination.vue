<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    currentPage: number
    totalPages: number
    siblingCount?: number
  }>(),
  { siblingCount: 1 },
)

const emit = defineEmits<{
  'update:currentPage': [page: number]
}>()

const pages = computed(() => {
  const totalNumbers = props.siblingCount * 2 + 5
  if (props.totalPages <= totalNumbers) {
    return Array.from({ length: props.totalPages }, (_, i) => i + 1)
  }

  const leftSiblingIndex = Math.max(props.currentPage - props.siblingCount, 1)
  const rightSiblingIndex = Math.min(props.currentPage + props.siblingCount, props.totalPages)
  const showLeftEllipsis = leftSiblingIndex > 2
  const showRightEllipsis = rightSiblingIndex < props.totalPages - 1

  if (!showLeftEllipsis && showRightEllipsis) {
    const leftCount = 3 + 2 * props.siblingCount
    const leftPages = Array.from({ length: leftCount }, (_, i) => i + 1)
    return [...leftPages, 'ellipsis-right', props.totalPages]
  }

  if (showLeftEllipsis && !showRightEllipsis) {
    const rightCount = 3 + 2 * props.siblingCount
    const rightPages = Array.from(
      { length: rightCount },
      (_, i) => props.totalPages - rightCount + 1 + i,
    )
    return [1, 'ellipsis-left', ...rightPages]
  }

  const middlePages = Array.from(
    { length: rightSiblingIndex - leftSiblingIndex + 1 },
    (_, i) => leftSiblingIndex + i,
  )
  return [1, 'ellipsis-left', ...middlePages, 'ellipsis-right', props.totalPages]
})

function goTo(page: number) {
  if (page >= 1 && page <= props.totalPages && page !== props.currentPage) {
    emit('update:currentPage', page)
  }
}
</script>

<template>
  <nav v-if="totalPages > 1" aria-label="Pagination" class="flex items-center gap-1">
    <button
      type="button"
      :disabled="currentPage <= 1"
      class="inline-flex size-9 items-center justify-center rounded-lg text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:cursor-not-allowed disabled:opacity-50"
      :class="currentPage <= 1 ? 'text-slate-300' : 'text-slate-600 hover:bg-slate-100'"
      aria-label="Previous page"
      @click="goTo(currentPage - 1)"
    >
      <ChevronLeft :size="16" stroke-width="2" />
    </button>
    <template v-for="(page, index) in pages" :key="index">
      <span
        v-if="typeof page === 'string'"
        class="flex size-9 items-center justify-center text-sm text-slate-400"
        aria-hidden="true"
      >
        ...
      </span>
      <button
        v-else
        type="button"
        class="inline-flex size-9 items-center justify-center rounded-lg text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40"
        :class="
          page === currentPage
            ? 'bg-primary-600 text-white shadow-sm'
            : 'text-slate-600 hover:bg-slate-100'
        "
        :aria-current="page === currentPage ? 'page' : undefined"
        @click="goTo(page)"
      >
        {{ page }}
      </button>
    </template>
    <button
      type="button"
      :disabled="currentPage >= totalPages"
      class="inline-flex size-9 items-center justify-center rounded-lg text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/40 disabled:cursor-not-allowed disabled:opacity-50"
      :class="currentPage >= totalPages ? 'text-slate-300' : 'text-slate-600 hover:bg-slate-100'"
      aria-label="Next page"
      @click="goTo(currentPage + 1)"
    >
      <ChevronRight :size="16" stroke-width="2" />
    </button>
  </nav>
</template>
